<?php

namespace App\Policies;

use App\Models\Flashcard;
use App\Models\User;

class FlashcardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.flashcards.manage');
    }

    public function view(User $user, Flashcard $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Flashcard $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Flashcard $record): bool
    {
        return $this->viewAny($user);
    }
}
