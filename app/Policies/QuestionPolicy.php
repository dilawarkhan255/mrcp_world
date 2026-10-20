<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

class QuestionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->hasAnyRole(['editor', 'admin']);
    }

    public function view(User $user, Question $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Question $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Question $record): bool
    {
        return $this->viewAny($user);
    }
}
