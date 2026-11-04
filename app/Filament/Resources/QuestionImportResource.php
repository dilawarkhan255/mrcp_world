<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuestionImportResource\Pages\ManageQuestionImports;
use App\Models\QuestionImport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('id')->label('Upload'),
            Tables\Columns\TextColumn::make('exam.name'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('processed'),
            Tables\Columns\TextColumn::make('imported')->label('Accepted rows'),
            Tables\Columns\TextColumn::make('rejected'),
            Tables\Columns\TextColumn::make('created_at')->dateTime(),
        ])->actions([
            Tables\Actions\Action::make('report')->iconButton()->icon('heroicon-o-document-magnifying-glass')->modalHeading('Import report')->modalSubmitAction(false)->modalCancelActionLabel('Close')
                ->modalContent(fn (QuestionImport $record) => view('imports.report', ['record' => $record])),
        ])->poll('10s')->defaultSort('id', 'desc')->paginationPageOptions([25, 50, 100]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageQuestionImports::route('/')];
    }
}
