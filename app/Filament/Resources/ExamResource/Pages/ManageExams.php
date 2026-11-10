<?php

namespace App\Filament\Resources\ExamResource\Pages;

use App\Filament\Concerns\UsesYajraTable;
use App\Filament\Resources\ExamResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageExams extends ManageRecords
{
    use UsesYajraTable;

    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
