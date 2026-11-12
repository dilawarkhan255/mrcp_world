<?php

namespace App\Filament\Resources\TextbookChapterResource\Pages;

use App\Filament\Resources\TextbookChapterResource;
use App\Filament\Support\CrudActions;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\MaxWidth;

/** A chapter's own page: the whole chapter as students read it. */
class ViewTextbookChapter extends ViewRecord
{
    protected static string $resource = TextbookChapterResource::class;

    /** @return array<Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            CrudActions::backToList(TextbookChapterResource::class),
            Actions\EditAction::make()->modalWidth(MaxWidth::FiveExtraLarge),
        ];
    }
}
