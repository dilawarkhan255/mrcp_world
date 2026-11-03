<?php

namespace App\Policies;

use App\Models\Feedback;
use App\Models\User;

/**
 * Students send feedback through the site, never through the admin panel.
 * Editors and admins (community.comments.moderate) read and review it; only admins delete.
 */
class FeedbackPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('community.comments.moderate');
    }

    public function view(User $user, Feedback $feedback): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Feedback $feedback): bool
    {
        return $user->can('community.comments.moderate');
    }

    public function delete(User $user, Feedback $feedback): bool
    {
        return $user->hasRole('admin');
    }
}
