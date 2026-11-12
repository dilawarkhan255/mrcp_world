<?php

namespace App\Filament\Resources\AtlasImageResource\Pages;

use App\Filament\Resources\AtlasImageResource;
use App\Filament\Support\CrudActions;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

/** An atlas picture's own page: the large image, findings, copyright and linked MCQs. */
class ViewAtlasImage extends ViewRecord
{
    protected static string $resource = AtlasImageResource::class;

    /** @return array<Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            CrudActions::backToList(AtlasImageResource::class),
            Actions\EditAction::make(),
        ];
    }
}
