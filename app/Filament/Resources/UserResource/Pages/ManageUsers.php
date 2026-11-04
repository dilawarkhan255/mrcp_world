<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    /** @return array<Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Add user')->slideOver()
                ->using(fn (array $data): User => UserResource::saveAccount(new User, $data))
                // A user added by someone who may not give roles becomes a student.
                ->after(fn (User $record) => $record->roles()->exists() ? null : $record->assignRole('student')),
        ];
    }
}
