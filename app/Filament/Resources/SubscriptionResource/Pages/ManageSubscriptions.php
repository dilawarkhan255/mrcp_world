<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Resources\SubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSubscriptions extends ManageRecords
{
    protected static string $resource = SubscriptionResource::class;

    /** @return array<Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Grant access')
                ->mutateFormDataUsing(fn (array $data): array => SubscriptionResource::fillFromPlan($data)),
        ];
    }
}
