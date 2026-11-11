<?php

namespace App\Filament\Resources\ContactMessageResource\Pages;

use App\Filament\Concerns\UsesYajraTable;
use App\Filament\Resources\ContactMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContactMessages extends ListRecords
{
    use UsesYajraTable;

    protected static string $resource = ContactMessageResource::class;

    /** @return array<Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
