<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('billing.plans.manage');
    }

    public function view(User $user, Plan $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Plan $record): bool
    {
        return $this->viewAny($user);
    }

    /** A plan that was sold stays, so old subscriptions keep their plan; switch it off instead. */
    public function delete(User $user, Plan $record): bool
    {
        return $this->viewAny($user) && ! Subscription::where('plan_id', $record->id)->exists();
    }
}
