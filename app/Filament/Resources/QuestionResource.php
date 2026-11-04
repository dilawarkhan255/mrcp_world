<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuestionResource\Pages\ManageQuestions;
use App\Models\Question;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
            Forms\Components\Section::make('Review and publishing')->icon('heroicon-o-shield-check')->columns(2)->schema([
                Forms\Components\Toggle::make('needs_review')->label('Flag for human review')->helperText('Use when a citation, guideline or fact is uncertain. Flagged MCQs are listed for review.')->live(),
                Forms\Components\Toggle::make('is_published')->label('Published — visible to students')->default(false),
                Forms\Components\TextInput::make('review_note')->label('What needs checking')->maxLength(500)->columnSpanFull()->visible(fn (Get $get): bool => (bool) $get('needs_review')),
                Forms\Components\Toggle::make('is_demo')->label('Free demo question on the landing page')->default(false),
            ]),
        ]);
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
        ])->actions([
            Tables\Actions\EditAction::make()->slideOver()->modalWidth(MaxWidth::FiveExtraLarge)
                ->mutateRecordDataUsing(fn (array $data, Question $record): array => [...$data, ...$record->only(['correct_option', 'explanation', 'key_points', 'mnemonics', 'references', 'youtube_url', 'image_links', 'image_search', 'image_search_sites', 'review_note'])]),
            Tables\Actions\Action::make('resolveReview')->iconButton()->label('Mark reviewed')->icon('heroicon-o-check')->color('success')
                ->visible(fn (Question $record): bool => $record->needs_review)
                ->authorize(fn (Question $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->requiresConfirmation()->modalDescription('Confirm that the citations and facts of this MCQ were checked by a person.')
                ->action(fn (Question $record) => $record->update(['needs_review' => false, 'review_note' => null])),
            Tables\Actions\DeleteAction::make()->label('Archive')->icon('heroicon-o-archive-box'),
        ])->defaultSort('id', 'desc')->paginationPageOptions([25, 50, 100]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageQuestions::route('/')];
    }
}
