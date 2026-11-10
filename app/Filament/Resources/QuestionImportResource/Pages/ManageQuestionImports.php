<?php

namespace App\Filament\Resources\QuestionImportResource\Pages;

use App\Filament\Concerns\UsesYajraTable;
use App\Filament\Resources\QuestionImportResource;
use App\Jobs\ImportQuestions;
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
            Actions\CreateAction::make()->label('Upload MCQs')
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
