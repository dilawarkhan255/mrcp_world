<?php

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

/**
 * Contact messages hold visitors' names, emails and phone numbers: only
 * admins and the staff who answer students (comment moderators) may read
 * them; only admins delete them. Nobody edits a message.
 */
class ContactMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->can('community.comments.moderate');
    }

    public function view(User $user, ContactMessage $message): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ContactMessage $message): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, ContactMessage $message): bool
    {
        return $user->hasRole('admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
