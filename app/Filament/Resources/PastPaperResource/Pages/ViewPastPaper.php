<?php

namespace App\Filament\Resources\PastPaperResource\Pages;

use App\Filament\Resources\PastPaperResource;
use App\Filament\Support\CrudActions;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

/** A past paper's own page: details, cover and its MCQs. */
class ViewPastPaper extends ViewRecord
{
    protected static string $resource = PastPaperResource::class;

    /** @return array<Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            CrudActions::backToList(PastPaperResource::class),
            Actions\EditAction::make(),
        ];
    }
}
