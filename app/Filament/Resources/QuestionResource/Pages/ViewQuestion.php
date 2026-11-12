<?php

namespace App\Filament\Resources\QuestionResource\Pages;

use App\Filament\Resources\QuestionResource;
use App\Filament\Support\CrudActions;
use App\Models\Question;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\MaxWidth;

/** An MCQ's own page: stem, options, answer, explanation, resources and student stats. */
class ViewQuestion extends ViewRecord
{
    protected static string $resource = QuestionResource::class;

    /** @return array<Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            CrudActions::backToList(QuestionResource::class),
            Actions\EditAction::make()->modalWidth(MaxWidth::FiveExtraLarge)
                // The answer fields are hidden from arrays (answer secrecy), so the form gets them explicitly.
                ->mutateRecordDataUsing(fn (array $data, Question $record): array => [...$data, ...$record->only(['correct_option', 'explanation', 'key_points', 'mnemonics', 'references', 'youtube_url', 'image_links', 'image_search', 'image_search_sites', 'review_note'])]),
        ];
    }
}
