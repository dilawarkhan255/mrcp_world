<?php

namespace App\Filament\Resources\QuestionImportResource\Pages;

use App\Filament\Concerns\UsesYajraTable;
use App\Filament\Resources\QuestionImportResource;
use App\Jobs\ImportQuestions;
use App\Jobs\ImportQuestionsFromPdf;
use App\Models\QuestionImport;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageQuestionImports extends ManageRecords
{
    use UsesYajraTable;

    protected static string $resource = QuestionImportResource::class;

    /** @return array<Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('template')->label('Download CSV template')->url('/admin/mcq-template'),
            Actions\Action::make('uploadPdf')->label('Upload PDF')->icon('heroicon-o-document-text')->color('primary')
                ->modalHeading('Create MCQs from a PDF')->modalSubmitActionLabel('Upload PDF')
                ->form(QuestionImportResource::pdfForm())
                ->authorize(fn (): bool => (bool) auth()->user()?->can('create', QuestionImport::class))
                ->action(function (array $data) {
                    $import = QuestionImport::create([
                        'user_id' => auth()->id(), 'exam_id' => $data['exam_id'], 'past_paper_id' => $data['past_paper_id'] ?? null, 'category_id' => $data['category_id'] ?? null,
                        'path' => $data['path'], 'source' => 'pdf', 'publish' => (bool) ($data['publish'] ?? false), 'last_row' => 0,
                    ]);
                    ImportQuestionsFromPdf::dispatch($import->id)->afterCommit();
                    Notification::make()->title('PDF queued')->body('The PDF is read in the background. Progress appears in this table.')->success()->send();
                }),
            Actions\CreateAction::make()->label('Upload CSV / XLSX')
                ->mutateFormDataUsing(function (array $data): array {
                    $data['user_id'] = auth()->id();

                    return $data;
                })
                ->after(function (QuestionImport $record) {
                    ImportQuestions::dispatch($record->id)->afterCommit();
                    Notification::make()->title('Upload queued')->body('The import worker processes this file in the background. Progress appears in this table.')->success()->send();
                }),
        ];
    }
}
