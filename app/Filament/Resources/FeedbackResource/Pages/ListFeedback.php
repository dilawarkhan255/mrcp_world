<?php

namespace App\Filament\Resources\FeedbackResource\Pages;

use App\Filament\Concerns\UsesYajraTable;
use App\Filament\Resources\FeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFeedback extends ListRecords
{
    use UsesYajraTable;

    protected static string $resource = FeedbackResource::class;

    /** @return array<Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
