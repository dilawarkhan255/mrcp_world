<?php

namespace App\Filament\Resources\PastPaperResource\Pages;

use App\Filament\Resources\PastPaperResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePastPapers extends ManageRecords
{
    protected static string $resource = PastPaperResource::class;

    /** @return array<Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
