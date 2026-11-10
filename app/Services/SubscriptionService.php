<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Qbank;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Who may open the content of a Qbank, and the manual subscription flow:
 * a student requests access, an admin activates or cancels it.
 *
 * Content staff (anyone who may view questions in the admin panel) always
 * have access, so editors can check what students see.
 */
class SubscriptionService
{
    public function hasAccess(?User $user, ?int $qbankId): bool
    {
        if ($user === null) {
            return false;
        }
        if ($qbankId === null) {
            return true; // An exam outside any Qbank is not sold, so it is not locked.
        }

        return $user->can('content.questions.view')
            || Subscription::where('user_id', $user->id)->where('qbank_id', $qbankId)->current()->exists();
    }

    /** The subscription that gives access now, if any. */
    public function current(User $user, int $qbankId): ?Subscription
    {
        return Subscription::where('user_id', $user->id)->where('qbank_id', $qbankId)->current()->orderByDesc('ends_at')->first();
    }

    /** A student's request for a Qbank. One open request per Qbank at a time. */
    public function request(User $user, Qbank $qbank, Plan $plan, ?string $reference, ?string $note): Subscription
    {
        if ((int) $plan->qbank_id !== $qbank->id || ! $plan->is_active) {
            throw ValidationException::withMessages(['plan_id' => 'Choose a plan of this Qbank.']);
        }

        return DB::transaction(function () use ($user, $qbank, $plan, $reference, $note) {
            // Lock the user's rows so two quick clicks cannot create two requests.
            Subscription::where('user_id', $user->id)->lockForUpdate()->get(['id']);
            if (Subscription::where('user_id', $user->id)->where('qbank_id', $qbank->id)->where('status', Subscription::STATUS_PENDING)->exists()) {
                throw ValidationException::withMessages(['plan_id' => 'Your request for '.$qbank->name.' is already waiting for activation.']);
            }

            $subscription = Subscription::create([
                'user_id' => $user->id, 'qbank_id' => $qbank->id, 'plan_id' => $plan->id, 'status' => Subscription::STATUS_PENDING,
                'amount' => $plan->price, 'currency' => $plan->currency, 'payment_reference' => $reference, 'student_note' => $note,
            ]);
            Notify::staffWith('billing.subscriptions.manage', 'New subscription request',
                $user->name.' requested '.$qbank->name.' ('.$plan->priceLabel().'). Payment reference: '.($reference ?: 'none').'.',
                '/admin/subscriptions?tableFilters[status][value]=pending', 'heroicon-o-credit-card', 'warning');

            return $subscription;
        }, 3);
    }

    /**
     * Activate for the plan's period. Renewing while still active extends the
     * current period instead of losing the remaining days. Without an admin,
     * the activation comes from a confirmed card payment.
     */
    public function activate(Subscription $subscription, ?User $admin, ?int $days = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $admin, $days) {
            $locked = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $days ??= (int) ($locked->plan->duration_days ?? 0);
            if ($days < 1) {
                throw ValidationException::withMessages(['days' => 'Choose the number of days of access.']);
            }
            $running = $this->current($locked->user, $locked->qbank_id);
            $start = $running !== null && $running->id !== $locked->id ? $running->ends_at : now();
            $locked->update([
                'status' => Subscription::STATUS_ACTIVE,
                'starts_at' => $start, 'ends_at' => $start->copy()->addDays($days),
                'activated_by' => $admin?->id, 'activated_at' => now(), 'reminded_at' => null,
            ]);
            Notify::student($locked->user, 'Your subscription is active',
                ($locked->qbank->name ?? 'Your Qbank').' is open until '.$locked->ends_at?->toFormattedDateString().'. Happy studying!',
                '/dashboard', 'CheckCircle2', 'success');

            return $locked;
        }, 3);
    }

    /**
     * Remind students whose access ends within the given days. Each
     * subscription period is reminded once.
     */
    public function remindExpiring(int $days = 7): int
    {
        $count = 0;
        Subscription::query()->current()->whereNull('reminded_at')->where('ends_at', '<=', now()->addDays($days))
            ->with(['user', 'qbank:id,name'])
            ->chunkById(200, function ($subscriptions) use (&$count): void {
                foreach ($subscriptions as $subscription) {
                    if ($subscription->user === null) {
                        continue;
                    }
                    // A renewal that is already waiting or active after this one needs no reminder.
                    $renewed = Subscription::where('user_id', $subscription->user_id)->where('qbank_id', $subscription->qbank_id)
                        ->whereKeyNot($subscription->id)
                        ->where(fn ($query) => $query->where('status', Subscription::STATUS_PENDING)
                            ->orWhere(fn ($query) => $query->where('status', Subscription::STATUS_ACTIVE)->where('ends_at', '>', $subscription->ends_at)))
                        ->exists();
                    if (! $renewed) {
                        Notify::student($subscription->user, 'Your subscription ends soon',
                            ($subscription->qbank->name ?? 'Your Qbank').' closes on '.$subscription->ends_at?->toFormattedDateString().'. Renew to keep your access and history.',
                            '/subscription', 'CalendarClock', 'warning');
                        $count++;
                    }
                    $subscription->update(['reminded_at' => now()]);
                }
            });

        return $count;
    }

    public function cancel(Subscription $subscription, ?string $note = null): void
    {
        $subscription->update(['status' => Subscription::STATUS_CANCELLED, 'admin_note' => $note ?? $subscription->admin_note]);
    }
}
