<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('billing.users.view');
    }

    public function view(User $user, User $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('billing.users.manage');
    }

    public function update(User $user, User $record): bool
    {
        return $user->can('billing.users.manage');
    }

    /** An administrator account is never removed from the panel. */
    public function delete(User $user, User $record): bool
    {
        return $user->can('billing.users.manage') && ! $record->hasRole('admin');
    }
}
