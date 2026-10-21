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

    /** Only editors and admins may hide or pin a comment. */
    public function moderate(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->hasAnyRole(['editor', 'admin']);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function view(User $user, QuestionComment $record): bool
    {
        return $this->viewAny($user);
    }
}
