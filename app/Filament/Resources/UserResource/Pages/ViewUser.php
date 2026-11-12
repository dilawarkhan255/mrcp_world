<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Filament\Support\CrudActions;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

/** A user's own page: account, roles, subscriptions and activity. */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    /** @return array<Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            CrudActions::backToList(UserResource::class),
            Actions\EditAction::make()
                ->using(fn (User $record, array $data): User => UserResource::saveAccount($record, $data)),
        ];
    }
}
