<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the permissions defined in config/permissions.php and the starting
 * roles. Safe to run again: a role an admin edited in the panel keeps its
 * permissions; it only receives permissions that are new since the last run.
 * The admin role always has every permission.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permissions = (array) config('permissions.list');
        $existing = Permission::where('guard_name', 'web')->pluck('name')->all();
        $added = array_values(array_diff($permissions, $existing));
        foreach ($added as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach ((array) config('permissions.roles') as $name => $granted) {
            $granted = in_array('*', $granted, true) ? $permissions : $granted;
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            if ($role === null) {
                Role::findOrCreate($name, 'web')->syncPermissions($granted);

                continue;
            }
            $new = array_values(array_intersect($granted, $added));
            if ($new !== []) {
                $role->givePermissionTo($new);
            }
        }

        // The admin role is never left without a permission, whatever was changed.
        Role::findOrCreate('admin', 'web')->syncPermissions($permissions);
        $registrar->forgetCachedPermissions();
    }
}
