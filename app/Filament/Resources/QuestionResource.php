<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuestionResource\Pages\ManageQuestions;
use App\Filament\Resources\QuestionResource\Pages\ViewQuestion;
use App\Filament\Support\CrudActions;
use App\Models\AtlasImage;
use App\Models\Flashcard;
use App\Models\Question;
use App\Services\FlashcardService;
use App\Services\QuestionResources;
use App\Support\QuestionContent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;

class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'MCQs';

    protected static ?string $modelLabel = 'MCQ';

    protected static ?int $navigationSort = 4;

    /** Markdown toolbar: tables and images are needed for MCQ stems and explanations. */
    private const TOOLBAR = ['bold', 'italic', 'strike', 'link', 'heading', 'bulletList', 'orderedList', 'table', 'attachFiles', 'blockquote', 'undo', 'redo'];

    public static function getNavigationBadge(): ?string
    {
        $flagged = Question::where('needs_review', true)->count();

        return $flagged > 0 ? (string) $flagged : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'MCQs flagged for human review';
    }

    private static function markdown(string $name, string $label, int $max, bool $required = false): Forms\Components\MarkdownEditor
    {
        return Forms\Components\MarkdownEditor::make($name)->label($label)->required($required)->maxLength($max)
            ->toolbarButtons(self::TOOLBAR)
            ->fileAttachmentsDisk('public')->fileAttachmentsDirectory('question-media')->fileAttachmentsVisibility('public')
            ->columnSpanFull();
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['code'];
    }

    protected static int $globalSearchResultsLimit = 5;

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Question $record */
        return (string) ($record->code);
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Question $record */
        return ['Subject' => $record->category->name ?? '—', 'Exam' => $record->exam->name ?? '—'];
    }

    /** @return Builder<Question> */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['category:id,name', 'exam:id,name']);
    }

    /** These resources have no edit page of their own: open the list, searched for the record. */
    public static function getGlobalSearchResultUrl(Model $record): string
    {
        /** @var Question $record */
        return static::getUrl('index', ['tableSearch' => $record->code]);
    }

    public static function form(Form $form): Form
    {
        $options = [];
        foreach (['A', 'B', 'C', 'D', 'E'] as $key) {
            $options[] = Forms\Components\Textarea::make('options.'.$key)->label('Option '.$key)->required(in_array($key, ['A', 'B']))->maxLength(4000)->rows(2);
        }

        return $form->schema([
            Forms\Components\Section::make('Placement')->icon('heroicon-o-squares-2x2')->columns(3)->schema([
                Forms\Components\Select::make('exam_id')->relationship('exam', 'name', modifyQueryUsing: fn (Builder $query) => $query->whereNull('content_exam_id'))->required()->live(),
                Forms\Components\Select::make('category_id')->label('Subject')->relationship('category', 'name', modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('exam_id', $get('exam_id')))->required()->searchable()->optionsLimit(50),
                Forms\Components\Select::make('past_paper_id')->label('Past paper')->relationship('pastPaper', 'title', modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('exam_id', $get('exam_id')))->searchable()->optionsLimit(50)->placeholder('Main MCQ bank'),
                Forms\Components\TextInput::make('code')->required()->maxLength(100)->regex('/^[A-Za-z0-9._-]+$/')->helperText('Unique within this exam. Reimports update the matching code.')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('exam_id', $get('exam_id'))),
                Forms\Components\TextInput::make('system')->label('System')->maxLength(100)->placeholder('e.g. Cardiovascular')->datalist(['Cardiovascular', 'Respiratory', 'Gastrointestinal', 'Renal', 'Endocrine', 'Neurology', 'Haematology', 'Infectious diseases', 'Rheumatology', 'Dermatology']),
                Forms\Components\TextInput::make('repeat_count')->label('Exam repeats')->numeric()->minValue(0)->maxValue(999)->default(0)->helperText('Times this topic appeared in past exams.'),
                Forms\Components\TextInput::make('source_reference')->label('Source label')->maxLength(255)->placeholder('e.g. Sept 2023 recall')->columnSpan(3),
            ]),
            Forms\Components\Section::make('Question')->icon('heroicon-o-document-text')->schema([
                self::markdown('body', 'Question stem', 20000, true)->helperText('Markdown. Use the toolbar for tables, or attach images (lab reports, ECGs, radiographs).'),
            ]),
            Forms\Components\Section::make('Answer options')->icon('heroicon-o-list-bullet')->columns(2)->schema([
                ...$options,
                Forms\Components\Select::make('correct_option')->options(array_combine(['A', 'B', 'C', 'D', 'E'], ['A', 'B', 'C', 'D', 'E']))->required(),
            ]),
            Forms\Components\Section::make('Explanation')->icon('heroicon-o-light-bulb')->schema([
                self::markdown('explanation', 'Explanation', 20000)->helperText('Detailed explanation. Cite NICE and WHO guidance in References below where it applies.'),
                self::markdown('key_points', 'Key points', 8000),
                self::markdown('mnemonics', 'Mnemonics', 4000),
            ]),
            Forms\Components\Section::make('References and media')->icon('heroicon-o-link')->collapsible()->schema([
                Forms\Components\Repeater::make('references')->label('Guideline references')->schema([
                    Forms\Components\Select::make('source')->options(Question::REFERENCE_SOURCES)->default('NICE')->required(),
                    Forms\Components\TextInput::make('title')->label('Citation text')->required()->maxLength(500)->columnSpan(2),
                    Forms\Components\TextInput::make('year')->maxLength(4)->regex('/^(19|20)\d{2}$/'),
                    Forms\Components\TextInput::make('url')->label('Link')->url()->maxLength(2048)->columnSpan(4),
                ])->columns(4)->maxItems(20)->defaultItems(0)->addActionLabel('Add reference')->collapsible()
                    ->itemLabel(fn (array $state): ?string => trim(($state['source'] ?? '').' · '.($state['title'] ?? ''), ' ·') ?: null)
                    ->helperText('Write the citation exactly as published. Never guess a title, year or link: if unsure, leave it out and flag the MCQ for review.'),
                Forms\Components\TextInput::make('youtube_url')->label('YouTube video')->url()->maxLength(255)->placeholder('https://www.youtube.com/watch?v=…'),
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('image_search')->label('Live image search term')->maxLength(150)->placeholder('e.g. erythema nodosum')->columnSpan(1)
                        ->helperText('Students get search links for this term on the sites you tick.'),
                    Forms\Components\CheckboxList::make('image_search_sites')->label('Search on')->columnSpan(2)->columns(2)
                        ->options(fn (): array => collect((array) config('student.image_search'))->map(fn (array $provider): string => $provider['label'])->all())
                        ->default(array_keys((array) config('student.image_search')))
                        ->formatStateUsing(fn (?array $state): array => $state ?? array_keys((array) config('student.image_search'))),
                ]),
                Forms\Components\Repeater::make('image_links')->label('Searchable image links')->schema([
                    Forms\Components\TextInput::make('label')->maxLength(120)->placeholder('e.g. Erythema nodosum'),
                    Forms\Components\TextInput::make('url')->url()->required()->maxLength(2048)->placeholder('https://…'),
                ])->columns(2)->maxItems(20)->defaultItems(0)->addActionLabel('Add image link'),
            ]),
            Forms\Components\Section::make('Learning resources (right sidebar)')->icon('heroicon-o-book-open')->collapsible()
                ->description('Shown next to the MCQ once it is answered. Without a chapter, the subject\'s chapters are shown; without an image, atlas images matching the search term above.')
                ->columns(2)->schema([
                    Forms\Components\Select::make('textbookChapters')->label('Extended textbook chapters')
                        ->relationship('textbookChapters', 'title', modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('textbook_chapters.exam_id', $get('exam_id'))->orderBy('textbook_chapters.title'))
                        ->multiple()->searchable()->preload()->maxItems(QuestionResources::TEXTBOOK_LIMIT)
                        ->helperText('Chapters of this exam. Only published chapters are shown to students.'),
                    Forms\Components\Select::make('atlasImages')->label('Image Atlas images')
                        ->relationship('atlasImages', 'title', modifyQueryUsing: fn (Builder $query) => $query->orderBy('atlas_images.title'))
                        ->getOptionLabelFromRecordUsing(fn (AtlasImage $record): string => $record->title.($record->diagnosis ? ' — '.$record->diagnosis : ''))
                        ->multiple()->searchable(['title', 'diagnosis'])->preload()->maxItems(QuestionResources::ATLAS_LIMIT)
                        ->helperText('Only published images with confirmed copyright are shown to students.'),
                ]),
            Forms\Components\Section::make('Review and publishing')->icon('heroicon-o-shield-check')->columns(2)->schema([
                Forms\Components\Toggle::make('needs_review')->label('Flag for human review')->helperText('Use when a citation, guideline or fact is uncertain. Flagged MCQs are listed for review.')->live(),
                Forms\Components\Toggle::make('is_published')->label('Published — visible to students')->default(false),
                Forms\Components\TextInput::make('review_note')->label('What needs checking')->maxLength(500)->columnSpanFull()->visible(fn (Get $get): bool => (bool) $get('needs_review')),
                Forms\Components\Toggle::make('is_demo')->label('Free demo question on the landing page')->default(false),
            ]),
        ]);
    }

    /** The MCQ's own page (View): everything about it, as a student sees it after answering. */
    public static function infolist(Infolist $infolist): Infolist
    {
        $markdown = fn (string $field): \Closure => fn (Question $record): ?HtmlString => filled($record->getAttribute($field))
            ? new HtmlString((string) QuestionContent::html((string) $record->getAttribute($field))) : null;

        return $infolist->schema([
            Infolists\Components\Section::make('Overview')->icon('heroicon-o-information-circle')->columns(4)->schema([
                Infolists\Components\TextEntry::make('code')->copyable()->weight('bold'),
                Infolists\Components\TextEntry::make('exam.name')->label('Exam')->badge()->color('gray'),
                Infolists\Components\TextEntry::make('category.name')->label('Subject')->badge()->color('info'),
                Infolists\Components\TextEntry::make('pastPaper.title')->label('Past paper')->placeholder('Main bank only'),
                Infolists\Components\IconEntry::make('is_published')->label('Published')->boolean(),
                Infolists\Components\IconEntry::make('is_demo')->label('Free demo')->boolean(),
                Infolists\Components\TextEntry::make('needs_review')->label('Review')->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Needs review' : 'Checked')
                    ->color(fn ($state): string => $state ? 'warning' : 'success'),
                Infolists\Components\TextEntry::make('updated_at')->label('Last edited')->since(),
            ]),
            Infolists\Components\Section::make('Review note')->icon('heroicon-o-flag')
                ->visible(fn (Question $record): bool => filled($record->review_note))
                ->schema([Infolists\Components\TextEntry::make('review_note')->hiddenLabel()]),
            Infolists\Components\Section::make('Question')->icon('heroicon-o-question-mark-circle')->schema([
                Infolists\Components\TextEntry::make('body')->hiddenLabel()->state($markdown('body'))->prose(),
                Infolists\Components\TextEntry::make('options')->label('Options')->state(fn (Question $record): HtmlString => self::optionsHtml($record)),
            ]),
            Infolists\Components\Section::make('Answer and explanation')->icon('heroicon-o-check-badge')->schema([
                Infolists\Components\TextEntry::make('correct_option')->label('Correct answer')->badge()->color('success')
                    ->state(fn (Question $record): string => $record->correct_option.'. '.(((array) $record->options)[$record->correct_option] ?? '')),
                Infolists\Components\TextEntry::make('explanation')->state($markdown('explanation'))->prose()->placeholder('No explanation yet.'),
                Infolists\Components\Grid::make(2)->schema([
                    Infolists\Components\TextEntry::make('key_points')->label('Key points')->state($markdown('key_points'))->prose()->placeholder('—'),
                    Infolists\Components\TextEntry::make('mnemonics')->label('Mnemonic')->state($markdown('mnemonics'))->prose()->placeholder('—'),
                ]),
            ]),
            Infolists\Components\Section::make('References and learning resources')->icon('heroicon-o-book-open')->collapsible()->columns(2)->schema([
                Infolists\Components\TextEntry::make('references')->label('Guideline references')->columnSpanFull()
                    ->state(fn (Question $record): ?HtmlString => self::referencesHtml($record))->placeholder('No references yet.'),
                Infolists\Components\TextEntry::make('textbookChapters.title')->label('Linked textbook chapters')->badge()->color('info')
                    ->placeholder('None (the subject chapters are used)'),
                Infolists\Components\TextEntry::make('atlasImages.title')->label('Linked atlas images')->badge()->color('info')->placeholder('None'),
                Infolists\Components\TextEntry::make('image_search')->label('Image and video search term')->placeholder('—'),
                Infolists\Components\TextEntry::make('youtube_url')->label('YouTube video')->url(fn (Question $record): ?string => $record->youtube_url)
                    ->openUrlInNewTab()->placeholder('—'),
            ]),
            Infolists\Components\Section::make('Students')->icon('heroicon-o-chart-bar')->columns(3)->schema([
                Infolists\Components\TextEntry::make('stats_attempts')->label('Answers so far')
                    ->state(fn (Question $record): int => (int) self::stats($record)?->attempts),
                Infolists\Components\TextEntry::make('stats_correct')->label('Answered correctly')
                    ->state(function (Question $record): string {
                        $stats = self::stats($record);

                        return $stats !== null && $stats->attempts > 0 ? round($stats->correct / $stats->attempts * 100).'%' : '—';
                    }),
                Infolists\Components\TextEntry::make('source_reference')->label('Source')->placeholder('—'),
            ]),
        ]);
    }

    /** The stats row of an MCQ (refreshed by stats:refresh), read once per page. */
    private static function stats(Question $record): ?object
    {
        return once(fn (): ?object => DB::table('question_stats')->where('question_id', $record->id)->first(['attempts', 'correct']));
    }

    /** The options, with the correct one marked. */
    private static function optionsHtml(Question $record): HtmlString
    {
        $items = '';
        foreach ((array) $record->options as $key => $text) {
            $correct = (string) $key === (string) $record->correct_option;
            $items .= '<li style="display:flex;gap:.6rem;align-items:flex-start;padding:.55rem .75rem;border-radius:.6rem;margin-bottom:.4rem;'
                .($correct ? 'background:rgba(22,163,74,.10);border:1px solid rgba(22,163,74,.45);font-weight:600' : 'border:1px solid rgba(148,163,184,.35)').'">'
                .'<span style="min-width:1.4rem;font-weight:700">'.e((string) $key).'.</span><span>'.e((string) $text).'</span>'
                .($correct ? '<span style="margin-left:auto;color:#15803d;font-size:.8rem;white-space:nowrap">&#10003; Correct</span>' : '').'</li>';
        }

        return new HtmlString('<ul style="margin:0;padding:0;list-style:none">'.$items.'</ul>');
    }

    /** NICE / WHO / other references, linked when they have a link. */
    private static function referencesHtml(Question $record): ?HtmlString
    {
        $items = '';
        foreach ((array) $record->references as $reference) {
            $title = e($reference['title']);
            $url = (string) ($reference['url'] ?? '');
            $items .= '<li style="margin-bottom:.35rem"><strong>'.e($reference['source']).'</strong> · '
                .($url !== '' ? '<a href="'.e($url).'" target="_blank" rel="noopener" style="text-decoration:underline">'.$title.'</a>' : $title)
                .(! empty($reference['year']) ? ' ('.e((string) $reference['year']).')' : '').'</li>';
        }

        return $items === '' ? null : new HtmlString('<ul style="margin:0;padding-left:1rem;list-style:disc">'.$items.'</ul>');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->searchable(query: fn (Builder $query, string $search) => $query->where('code', 'like', $search.'%'))->sortable()->weight('bold'),
            Tables\Columns\TextColumn::make('body')->label('Question')->limit(65)->tooltip(fn (Question $record): string => mb_substr($record->body, 0, 300)),
            Tables\Columns\TextColumn::make('exam.name')->toggleable(),
            Tables\Columns\TextColumn::make('category.name')->label('Subject'),
            Tables\Columns\TextColumn::make('system')->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('pastPaper.title')->label('Paper')->placeholder('Question bank')->toggleable(),
            Tables\Columns\IconColumn::make('needs_review')->label('Review')->boolean()->trueIcon('heroicon-o-flag')->falseIcon('heroicon-o-minus')->trueColor('warning')->falseColor('gray'),
            Tables\Columns\IconColumn::make('is_published')->label('Live')->boolean(),
        ])->filters([
            Tables\Filters\SelectFilter::make('exam')->relationship('exam', 'name', fn (Builder $query) => $query->whereNull('content_exam_id')),
            Tables\Filters\SelectFilter::make('category')->label('Subject')->relationship('category', 'name')->searchable()->optionsLimit(50),
            Tables\Filters\SelectFilter::make('past_paper')->label('Past paper')->relationship('pastPaper', 'title')->searchable()->optionsLimit(50),
            Tables\Filters\TernaryFilter::make('is_published')->label('Published'),
            Tables\Filters\TernaryFilter::make('needs_review')->label('Flagged for review'),
            // Archived MCQs: show them to restore or delete them for good.
            Tables\Filters\TrashedFilter::make()->label('Archived'),
        ])->actions([
            CrudActions::viewPage(self::class),
            Tables\Actions\EditAction::make()->modalWidth(MaxWidth::FiveExtraLarge)
                ->mutateRecordDataUsing(fn (array $data, Question $record): array => [...$data, ...$record->only(['correct_option', 'explanation', 'key_points', 'mnemonics', 'references', 'youtube_url', 'image_links', 'image_search', 'image_search_sites', 'review_note'])]),
            Tables\Actions\Action::make('resolveReview')->iconButton()->label('Mark reviewed')->icon('heroicon-o-check')->color('success')
                ->visible(fn (Question $record): bool => $record->needs_review)
                ->authorize(fn (Question $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->requiresConfirmation()->modalDescription('Confirm that the citations and facts of this MCQ were checked by a person.')
                ->action(fn (Question $record) => $record->update(['needs_review' => false, 'review_note' => null])),
            Tables\Actions\DeleteAction::make()->label('Archive')->icon('heroicon-o-archive-box'),
            Tables\Actions\RestoreAction::make()->iconButton()->label('Restore'),
            CrudActions::deleteWithContents(fn (): bool => false, 'code', label: 'Delete permanently'),
        ])->bulkActions([
            // A flashcard from each selected MCQ: stem on the front, answer and key points on the back.
            Tables\Actions\BulkAction::make('makeFlashcards')->label('Make flashcards')->icon('heroicon-o-rectangle-stack')->color('warning')
                ->authorize(fn (): bool => auth()->user()?->can('create', Flashcard::class) ?? false)
                ->requiresConfirmation()->modalDescription('Each MCQ gets one flashcard (an MCQ that already has one is skipped). The cards are published like their MCQ; edit them in Flashcards.')
                ->action(function (Collection $records): void {
                    $service = app(FlashcardService::class);
                    $created = 0;
                    foreach ($records as $record) {
                        $created += $record instanceof Question && $service->fromQuestion($record)->wasRecentlyCreated ? 1 : 0;
                    }
                    Notification::make()->success()->title($created.' flashcards made'.($records->count() > $created ? ', '.($records->count() - $created).' already had one' : ''))->send();
                }),
            CrudActions::bulkDelete(label: 'Archive selected', done: 'archived'),
            Tables\Actions\RestoreBulkAction::make()->label('Restore selected'),
            CrudActions::bulkDeleteWithContents(label: 'Delete selected permanently'),
        ])->defaultSort('id', 'desc')->paginationPageOptions([25, 50, 100]);
    }

    /** @return Builder<Question> Archived MCQs too (the Archived filter decides what is listed). */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageQuestions::route('/'), 'view' => ViewQuestion::route('/{record}')];
    }
}
