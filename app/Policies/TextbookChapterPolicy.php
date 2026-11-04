<?php

namespace App\Policies;

use App\Models\TextbookChapter;
use App\Models\User;

class TextbookChapterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.textbooks.manage');
    }

    public function view(User $user, TextbookChapter $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, TextbookChapter $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, TextbookChapter $record): bool
    {
        return $this->viewAny($user);
    }
}
