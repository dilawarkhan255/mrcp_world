<?php

namespace App\Filament\Resources\QbankResource\Pages;

use App\Filament\Resources\QbankResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageQbanks extends ManageRecords
{
    protected static string $resource = QbankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
