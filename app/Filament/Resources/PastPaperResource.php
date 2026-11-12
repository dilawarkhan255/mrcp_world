<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PastPaperResource\Pages\ManagePastPapers;
use App\Filament\Resources\PastPaperResource\Pages\ViewPastPaper;
use App\Filament\Support\CrudActions;
use App\Models\PastPaper;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PastPaperResource extends Resource
{
    protected static ?string $model = PastPaper::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Paper')->icon('heroicon-o-document-text')->columns(2)->schema([
                Forms\Components\Select::make('exam_id')->label('Exam')->relationship('exam', 'name', modifyQueryUsing: fn (Builder $query) => $query->whereNull('content_exam_id'))->required()->live(),
                Forms\Components\TextInput::make('title')->required()->maxLength(255)->placeholder('e.g. MRCP 1 — September 2024')
                    ->live(onBlur: true)->afterStateUpdated(fn (Forms\Set $set, Get $get, ?string $state) => blank($get('slug')) ? $set('slug', Str::slug((string) $state)) : null),
                Forms\Components\TextInput::make('slug')->required()->maxLength(255)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('exam_id', $get('exam_id')))
                    ->helperText('Filled from the title; used in links.'),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('year')->numeric()->required()->minValue(1900)->maxValue((int) date('Y') + 1),
                    Forms\Components\Select::make('sitting')->options(array_combine(self::SITTINGS, self::SITTINGS))->placeholder('Not set'),
                ]),
                Forms\Components\Textarea::make('description')->maxLength(1000)->rows(3)->columnSpanFull()
                    ->placeholder('What this paper covers, how many questions, any notes for students.'),
            ]),
            Forms\Components\Section::make('Cover image')->icon('heroicon-o-photo')->description('Shown on the student Past papers page. JPEG, PNG or WebP, up to 3 MB.')->schema([
                Forms\Components\FileUpload::make('image_path')->label('')
                    ->disk(PastPaper::IMAGE_DISK)->directory(PastPaper::IMAGE_DIRECTORY)->visibility('public')
                    ->image()->imagePreviewHeight('140')->imageEditor()->imageEditorAspectRatios(['16:9', '4:3', '1:1'])
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(3072)
                    ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => Str::uuid().'.'.strtolower($file->getClientOriginalExtension())),
            ]),
            Forms\Components\Section::make('Publishing')->icon('heroicon-o-shield-check')->schema([
                Forms\Components\Toggle::make('is_published')->label('Published — visible to students')->default(false)
                    ->helperText('Its MCQs are shown only while the paper is published.'),
            ]),
        ]);
    }

    /** The paper's own page (View). */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Grid::make(3)->schema([
                Infolists\Components\Section::make('Paper')->icon('heroicon-o-document-text')->columns(2)->columnSpan(2)->schema([
                    Infolists\Components\TextEntry::make('title')->weight('bold')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('exam.name')->label('Exam')->badge()->color('gray'),
                    Infolists\Components\TextEntry::make('slug')->copyable(),
                    Infolists\Components\TextEntry::make('year'),
                    Infolists\Components\TextEntry::make('sitting')->placeholder('Not set'),
                    Infolists\Components\IconEntry::make('is_published')->label('Published')->boolean(),
                    Infolists\Components\TextEntry::make('updated_at')->label('Last edited')->since(),
                    Infolists\Components\TextEntry::make('description')->columnSpanFull()->placeholder('No description.'),
                ]),
                Infolists\Components\Section::make('Cover image')->icon('heroicon-o-photo')->columnSpan(1)->schema([
                    Infolists\Components\ImageEntry::make('image_path')->hiddenLabel()->disk(PastPaper::IMAGE_DISK)->height(180)->placeholder('No cover image.'),
                ]),
            ]),
            Infolists\Components\Section::make('MCQs')->icon('heroicon-o-queue-list')->columns(3)->schema([
                Infolists\Components\TextEntry::make('mcq_total')->label('MCQs in the paper')
                    ->state(fn (PastPaper $record): int => $record->questions()->count()),
                Infolists\Components\TextEntry::make('mcq_live')->label('Published (students see them)')
                    ->state(fn (PastPaper $record): int => $record->questions()->where('is_published', true)->count()),
                Infolists\Components\TextEntry::make('mcq_link')->label('List')->state('Open the MCQs of this paper')->color('primary')
                    ->url(fn (PastPaper $record): string => QuestionResource::getUrl('index', ['tableFilters' => ['past_paper' => ['value' => $record->id]]])),
                Infolists\Components\TextEntry::make('mcq_subjects')->label('Subjects')->columnSpanFull()->badge()->color('info')
                    ->state(fn (PastPaper $record): array => DB::table('questions')->join('categories', 'categories.id', '=', 'questions.category_id')
                        ->where('questions.past_paper_id', $record->id)->whereNull('questions.deleted_at')
                        ->groupBy('categories.name')->orderBy('categories.name')
                        ->selectRaw('categories.name, count(*) as total')->get()
                        ->map(fn (object $row): string => $row->name.' ('.$row->total.')')->all())
                    ->placeholder('No MCQs yet.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\ImageColumn::make('image_path')->label('')->disk(PastPaper::IMAGE_DISK)->height(40)->width(56)
                ->defaultImageUrl(asset('brand/mrcp-world-mark.trim.png'))->extraImgAttributes(['loading' => 'lazy', 'class' => 'mw-thumb']),
            Tables\Columns\TextColumn::make('title')->searchable()->sortable()->weight('bold')
                ->description(fn (PastPaper $record): ?string => trim(($record->sitting ? $record->sitting.' ' : '').$record->year) ?: null),
            Tables\Columns\TextColumn::make('exam.name')->badge()->color('gray'),
            Tables\Columns\TextColumn::make('questions_count')->label('MCQs')->counts('questions')->sortable()->alignCenter(),
            Tables\Columns\TextColumn::make('published_count')->label('Live MCQs')->alignCenter()
                ->state(fn (PastPaper $record): int => (int) $record->getAttribute('published_questions_count')),
            Tables\Columns\IconColumn::make('is_published')->label('Live')->boolean(),
            Tables\Columns\TextColumn::make('updated_at')->since()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            Tables\Filters\SelectFilter::make('exam')->relationship('exam', 'name', fn (Builder $query) => $query->whereNull('content_exam_id')),
            Tables\Filters\SelectFilter::make('year')->options(fn (): array => PastPaper::query()->distinct()->orderByDesc('year')->pluck('year', 'year')->all()),
            Tables\Filters\TernaryFilter::make('is_published')->label('Published'),
        ])->actions([
            Tables\Actions\Action::make('mcqs')->label('View MCQs')->iconButton()->icon('heroicon-o-queue-list')->color('gray')
                ->url(fn (PastPaper $record): string => QuestionResource::getUrl('index', ['tableFilters' => ['past_paper' => ['value' => $record->id]]])),
            Tables\Actions\Action::make('upload')->label('Add MCQs (bulk upload)')->iconButton()->icon('heroicon-o-arrow-up-tray')->color('gray')
                ->url('/admin/question-imports'),
            CrudActions::viewPage(self::class),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make()->visible(self::deletable()),
            CrudActions::deleteWithContents(self::deletable(), 'title'),
        ])->bulkActions([CrudActions::bulkDelete(self::deletable()), CrudActions::bulkDeleteWithContents()])->modifyQueryUsing(fn (Builder $query) => $query->with('exam:id,name')
            ->withCount(['questions as published_questions_count' => fn (Builder $query) => $query->where('is_published', true)]))
            ->defaultSort('year', 'desc')->paginationPageOptions([25, 50, 100]);
    }

    /** Exam sittings a paper can belong to. */
    public const SITTINGS = ['January', 'March', 'May', 'June', 'August', 'September', 'October', 'December'];

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManagePastPapers::route('/'), 'view' => ViewPastPaper::route('/{record}')];
    }

    /** @return \Closure(Model): bool Only a paper without MCQs (archived too) or uploads may go. */
    public static function deletable(): \Closure
    {
        return fn (Model $record): bool => ! DB::table('questions')->where('past_paper_id', $record->getKey())->exists()
            && ! DB::table('question_imports')->where('past_paper_id', $record->getKey())->exists();
    }
}
