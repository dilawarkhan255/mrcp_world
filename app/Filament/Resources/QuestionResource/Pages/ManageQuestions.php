<?php

namespace App\Filament\Resources\QuestionResource\Pages;

use App\Filament\Concerns\UsesYajraTable;
use App\Filament\Resources\QuestionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\MaxWidth;

class ManageQuestions extends ManageRecords
{
    use UsesYajraTable;

    protected static string $resource = QuestionResource::class;

    /** @return array<Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('New MCQ')->slideOver()->modalWidth(MaxWidth::FiveExtraLarge),
            Actions\Action::make('bulkImport')->label('Bulk upload MCQs')->icon('heroicon-m-arrow-up-tray')->color('gray')->url('/admin/question-imports'),
        ];
    }
}
