<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Spatie\Permission\PermissionRegistrar;

class ManageRoles extends ManageRecords
{
    protected static string $resource = RoleResource::class;

    /** @return array<Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('New role')
                ->mutateFormDataUsing(fn (array $data): array => [...$data, 'guard_name' => 'web'])
                ->after(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions()),
        ];
    }
}
