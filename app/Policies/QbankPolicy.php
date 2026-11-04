<?php

namespace App\Policies;

use App\Models\Qbank;
use App\Models\User;

class QbankPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.exams.manage');
    }

    public function view(User $user, Qbank $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Qbank $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Qbank $record): bool
    {
        return $this->viewAny($user) && ! $record->exams()->exists() && ! $record->subscriptions()->exists();
    }
}
