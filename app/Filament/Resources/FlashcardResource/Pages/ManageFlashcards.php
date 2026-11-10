<?php

namespace App\Filament\Resources\FlashcardResource\Pages;

use App\Filament\Concerns\UsesYajraTable;
use App\Filament\Resources\FlashcardResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\MaxWidth;

class ManageFlashcards extends ManageRecords
{
    use UsesYajraTable;

    protected static string $resource = FlashcardResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New flashcard')->icon('heroicon-o-plus')->slideOver()->modalWidth(MaxWidth::ThreeExtraLarge)];
    }
}
