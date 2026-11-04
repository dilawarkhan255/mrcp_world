<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Keeps the panel reachable: the last admin can never lose the admin role,
 * and nobody can take the admin role away from themselves.
 */
final class RoleGuard
{
    /**
     * The reason a role change is refused, or null when it is allowed.
     *
     * @param  array<int|string, mixed>  $roleIds  The role ids the user will have.
     */
    public static function refusal(?User $record, array $roleIds, ?User $actor): ?string
    {
        $adminId = Role::where('name', 'admin')->where('guard_name', 'web')->value('id');
        if ($record === null || $adminId === null || ! $record->hasRole('admin')) {
            return null;
        }
        if (in_array((int) $adminId, array_map('intval', $roleIds), true)) {
            return null;
        }
        if ($actor !== null && $actor->is($record)) {
            return 'You cannot remove your own admin role. Ask another admin.';
        }
        if (User::role('admin')->count() <= 1) {
            return 'This is the last admin account. Give another account the admin role first.';
        }

        return null;
    }
}
