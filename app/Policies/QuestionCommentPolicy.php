<?php

namespace App\Policies;

use App\Models\QuestionComment;
use App\Models\User;

class QuestionCommentPolicy
{
    /** Any verified student may start a thread or reply. */
    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    /** Editors and admins may moderate the threads. */
    public function moderate(User $user): bool
    {
        return $user->can('community.comments.moderate');
    }

    public function viewAny(User $user): bool
    {
        return $user->can('community.comments.moderate');
    }

    public function view(User $user, QuestionComment $record): bool
    {
        return $this->viewAny($user);
    }
}
