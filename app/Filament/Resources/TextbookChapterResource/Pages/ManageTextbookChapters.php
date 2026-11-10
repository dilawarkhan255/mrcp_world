<?php

namespace App\Filament\Resources\TextbookChapterResource\Pages;

use App\Filament\Concerns\UsesYajraTable;
use App\Filament\Resources\TextbookChapterResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\MaxWidth;

class ManageTextbookChapters extends ManageRecords
{
    use UsesYajraTable;

    protected static string $resource = TextbookChapterResource::class;

    /** @return array<Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New chapter')->slideOver()->modalWidth(MaxWidth::FiveExtraLarge)];
    }
}
