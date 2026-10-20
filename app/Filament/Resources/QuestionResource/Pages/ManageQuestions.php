<?php

namespace App\Filament\Resources\QuestionResource\Pages;

use App\Filament\Resources\QuestionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageQuestions extends ManageRecords
{
    protected static string $resource = QuestionResource::class;

    /** @return array<Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make(), Actions\Action::make('bulkImport')->label('Bulk upload MCQs')->url('/admin/question-imports')];
    }
}
