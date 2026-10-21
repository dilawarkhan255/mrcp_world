<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = (array) config('permissions.list');
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach ((array) config('permissions.roles') as $role => $granted) {
            $model = Role::findOrCreate($role, 'web');
            $model->syncPermissions(in_array('*', $granted, true) ? $permissions : $granted);
        }
    }
}
