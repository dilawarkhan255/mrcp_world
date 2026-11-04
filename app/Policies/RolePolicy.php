<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Roles are managed by users with system.roles.manage (admins). The roles the
 * code relies on by name (admin, editor, student) cannot be deleted, and a
 * role that still has users cannot be deleted either.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('system.roles.manage');
    }

    public function view(User $user, Role $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Role $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Role $record): bool
    {
        return $this->viewAny($user)
            && ! in_array($record->name, (array) config('permissions.protected_roles'), true)
            && ! $record->users()->exists();
    }
}
