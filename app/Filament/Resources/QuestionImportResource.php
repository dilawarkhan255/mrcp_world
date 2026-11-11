<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuestionImportResource\Pages\ManageQuestionImports;
use App\Filament\Support\CrudActions;
use App\Models\Category;
use App\Models\Exam;
use App\Models\PastPaper;
use App\Models\QuestionImport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class QuestionImportResource extends Resource
{
    protected static ?string $model = QuestionImport::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Bulk MCQ uploads';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('exam_id')->relationship('exam', 'name', modifyQueryUsing: fn (Builder $query) => $query->whereNull('content_exam_id'))->required()->live(),
            Forms\Components\Select::make('past_paper_id')->relationship('pastPaper', 'title', modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('exam_id', $get('exam_id')))->searchable()->optionsLimit(50)
                ->rules(fn (Get $get): array => [Rule::exists('past_papers', 'id')->where('exam_id', $get('exam_id'))]),
            Forms\Components\FileUpload::make('path')->label('CSV or XLSX file (first sheet only)')->disk('local')->directory('question-imports')->visibility('private')->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->maxSize(20480)->rules(['extensions:csv,xlsx'])->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => Str::uuid().'.'.strtolower($file->getClientOriginalExtension()))->required(),
            Forms\Components\Toggle::make('publish')->label('Publish imported questions')->default(false),
            Forms\Components\Placeholder::make('instructions')->content('Use the downloadable template. Category slugs must exist in this exam. Matching question codes are updated, including archived questions. Files are private; processing runs in the background. Maximum 20 MB / 50,000 rows per file; split larger datasets into files.'),
        ]);
    }

    /** @return array<Forms\Components\Component> */
    public static function pdfForm(): array
    {
        return [
            Forms\Components\Select::make('exam_id')->label('Exam')->options(fn (): array => Exam::query()->whereNull('content_exam_id')->orderBy('sort_order')->pluck('name', 'id')->all())->required()->live(),
            Forms\Components\Select::make('past_paper_id')->label('Past paper (leave empty for the main MCQ bank)')
                ->options(fn (Get $get): array => PastPaper::query()->where('exam_id', $get('exam_id'))->orderByDesc('year')->pluck('title', 'id')->all())
                ->searchable()->rules(fn (Get $get): array => [Rule::exists('past_papers', 'id')->where('exam_id', $get('exam_id'))]),
            Forms\Components\Select::make('category_id')->label('Subject (for MCQs without a "Subject:" line)')
                ->options(fn (Get $get): array => Category::query()->where('exam_id', $get('exam_id'))->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()->helperText('For a PDF of one subject, e.g. Cardiology past papers.')
                ->rules(fn (Get $get): array => [Rule::exists('categories', 'id')->where('exam_id', $get('exam_id'))]),
            Forms\Components\FileUpload::make('path')->label('PDF of MCQs')->disk('local')->directory('question-imports')->visibility('private')
                ->acceptedFileTypes(['application/pdf'])->maxSize(20480)->rules(['extensions:pdf'])
                ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => Str::uuid().'.pdf')->required(),
            Forms\Components\Toggle::make('publish')->label('Publish the MCQs straight away')->default(false)->helperText('Off: they are saved as drafts to check first.'),
            Forms\Components\Placeholder::make('how')->label('PDF format')->content(new HtmlString(
                '<p>The PDF is read as text (no AI). Write each MCQ like this:</p>'
                .'<pre style="margin:.5rem 0;padding:.75rem;border-radius:.5rem;background:rgba(148,163,184,.12);white-space:pre-wrap;font-size:.8rem">'
                .'1. A 30-year-old woman has tremor, weight loss and a goitre with a bruit. What is the most likely diagnosis?
A. Toxic multinodular goitre
B. Graves disease
C. Hashimoto thyroiditis
D. De Quervain thyroiditis
E. Thyroid lymphoma
Answer: B
Explanation: Graves disease is the commonest cause of hyperthyroidism in young women… (optional)
Subject: Endocrinology (optional)'
                .'</pre>'
                .'<p>Questions can be numbered 1. / 1) / Q1. Options A–E as A. or A). Answers can also be in an answer key at the end: a line "Answers" then "1. B", "2. C"… The subject must match a subject of this exam, otherwise the MCQ goes to "Unsorted (PDF import)". Uploading the same PDF again updates those MCQs instead of duplicating them. Scanned PDFs (pictures of pages) cannot be read. Up to 20 MB.</p>'
            )),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('id')->label('Upload'),
            Tables\Columns\TextColumn::make('source')->label('Type')->badge()
                ->formatStateUsing(fn (?string $state): string => $state === 'pdf' ? 'PDF' : 'CSV / XLSX')
                ->color(fn (?string $state): string => $state === 'pdf' ? 'info' : 'gray'),
            Tables\Columns\TextColumn::make('exam.name'),
            Tables\Columns\TextColumn::make('pastPaper.title')->label('Past paper')->placeholder('Main bank'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('processed'),
            Tables\Columns\TextColumn::make('imported')->label('Accepted rows'),
            Tables\Columns\TextColumn::make('rejected'),
            Tables\Columns\TextColumn::make('created_at')->dateTime(),
        ])->actions([
            // The report is the view of an upload. Admins may delete an upload record (its MCQs stay).
            Tables\Actions\Action::make('report')->label('View report')->iconButton()->icon('heroicon-o-eye')->color('gray')->modalHeading('Import report')->modalSubmitAction(false)->modalCancelActionLabel('Close')
                ->modalContent(fn (QuestionImport $record) => view('imports.report', ['record' => $record])),
            Tables\Actions\DeleteAction::make()->modalDescription('Deletes this upload record and its private file. The MCQs it created stay.'),
        ])->bulkActions([CrudActions::bulkDelete()])->poll('10s')->defaultSort('id', 'desc')->paginationPageOptions([25, 50, 100]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageQuestionImports::route('/')];
    }
}
