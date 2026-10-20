<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->hasAnyRole(['editor', 'admin']);
    }

    public function view(User $user, Category $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Category $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Category $record): bool
    {
        return $this->viewAny($user);
    }
}
