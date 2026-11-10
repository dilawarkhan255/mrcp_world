<?php

namespace App\Filament\Resources\QbankResource\Pages;

use App\Filament\Concerns\UsesYajraTable;
use App\Filament\Resources\QbankResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageQbanks extends ManageRecords
{
    use UsesYajraTable;

    protected static string $resource = QbankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
