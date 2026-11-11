<?php

namespace App\Policies;

use App\Models\QuestionImport;
use App\Models\User;

class QuestionImportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.imports.view');
    }

    public function view(User $user, QuestionImport $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, QuestionImport $record): bool
    {
        return false;
    }

    public function delete(User $user, QuestionImport $record): bool
    {
        return $user->hasRole('admin');
    }
}
