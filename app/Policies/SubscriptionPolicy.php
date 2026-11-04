<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('billing.subscriptions.view');
    }

    public function view(User $user, Subscription $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('billing.subscriptions.manage');
    }

    public function update(User $user, Subscription $record): bool
    {
        return $user->can('billing.subscriptions.manage');
    }

    public function delete(User $user, Subscription $record): bool
    {
        return $user->can('billing.subscriptions.manage') && $record->status !== Subscription::STATUS_ACTIVE;
    }
}
