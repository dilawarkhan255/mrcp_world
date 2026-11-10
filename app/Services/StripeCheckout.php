<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Card payments for the Qbank plans with Stripe Checkout (a page hosted by
 * Stripe, so card details never reach this server). Students in any country
 * Stripe accepts cards from, Pakistan included, pay the plan price in its
 * currency (GBP); their bank converts it.
 *
 * Flow: start() saves a subscription in the "checkout" state and returns the
 * Checkout URL. Stripe confirms the payment with a signed webhook, and the
 * student's return to the success page checks it again; fulfil() then
 * activates the subscription once, whichever arrives first.
 */
class StripeCheckout
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function enabled(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    /**
     * Saves the purchase and creates its Checkout Session.
     *
     * @return string The Stripe Checkout URL to send the student to.
     *
     * @throws ValidationException
     */
    public function start(User $user, Plan $plan): string
    {
        $plan->loadMissing('qbank');
        if ($plan->qbank === null || ! $plan->qbank->is_active || ! $plan->is_active) {
            throw ValidationException::withMessages(['plan_id' => 'This plan is not on sale at the moment.']);
        }

        // One unfinished card payment per Qbank: a new attempt reuses it with the plan chosen now.
        $subscription = DB::transaction(function () use ($user, $plan): Subscription {
            Subscription::where('user_id', $user->id)->lockForUpdate()->get(['id']);
            $attributes = ['plan_id' => $plan->id, 'amount' => $plan->price, 'currency' => $plan->currency, 'payment_provider' => Subscription::PROVIDER_STRIPE];
            $open = Subscription::where('user_id', $user->id)->where('qbank_id', $plan->qbank_id)->where('status', Subscription::STATUS_CHECKOUT)->first();
            if ($open !== null) {
                $open->update($attributes);

                return $open;
            }

            return Subscription::create(['user_id' => $user->id, 'qbank_id' => $plan->qbank_id, 'status' => Subscription::STATUS_CHECKOUT, ...$attributes]);
        });

        try {
            $session = $this->createSession([
                'mode' => 'payment',
                'customer_email' => $user->email,
                'client_reference_id' => (string) $subscription->id,
                'metadata' => ['subscription_id' => (string) $subscription->id, 'user_id' => (string) $user->id],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower((string) $plan->currency),
                        'unit_amount' => $this->minorUnits((string) $plan->price),
                        'product_data' => [
                            'name' => $plan->qbank->name.' — '.$plan->durationLabel(),
                            'description' => 'Access to '.$plan->qbank->name.' for '.$plan->durationLabel().'.',
                        ],
                    ],
                ]],
                'success_url' => route('subscription.checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('subscription.index').'?checkout=cancelled',
            ]);
        } catch (ApiErrorException $exception) {
            report($exception);
            throw ValidationException::withMessages(['plan_id' => 'The card payment could not be started. Please try again in a moment.']);
        }

        $subscription->update(['stripe_session_id' => $session['id']]);

        return $session['url'];
    }

    /**
     * The student came back from Checkout: ask Stripe about the session (the
     * query string alone proves nothing) and fulfil it when it is paid.
     */
    public function complete(User $user, string $sessionId): ?Subscription
    {
        $subscription = Subscription::where('stripe_session_id', $sessionId)->where('user_id', $user->id)->first();
        if ($subscription === null) {
            return null;
        }
        try {
            $session = $this->retrieveSession($sessionId);
        } catch (ApiErrorException $exception) {
            report($exception);

            return $subscription;
        }

        return $this->fulfil($session) ?? $subscription->refresh();
    }

    /**
     * Activates the subscription a paid Checkout Session belongs to, once.
     * The amount and the currency must match what the plan cost.
     *
     * @param  array<string, mixed>  $session  The Checkout Session, as Stripe sends it.
     */
    public function fulfil(array $session): ?Subscription
    {
        if (($session['payment_status'] ?? null) !== 'paid' || ! is_string($session['id'] ?? null)) {
            return null;
        }

        return DB::transaction(function () use ($session): ?Subscription {
            $subscription = Subscription::where('stripe_session_id', $session['id'])->lockForUpdate()->first();
            if ($subscription === null || $subscription->status !== Subscription::STATUS_CHECKOUT) {
                return $subscription;
            }
            $expected = $this->minorUnits((string) $subscription->amount);
            if ((int) ($session['amount_total'] ?? -1) !== $expected || strtolower((string) ($session['currency'] ?? '')) !== strtolower((string) $subscription->currency)) {
                report(new \RuntimeException('Stripe session '.$session['id'].' paid '.($session['amount_total'] ?? '?').' '.($session['currency'] ?? '?').', expected '.$expected.' '.$subscription->currency.'.'));
                $subscription->update(['admin_note' => 'Card payment amount did not match the plan. Check it in Stripe before activating.']);

                return $subscription;
            }

            $intent = is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : null;
            $subscription->update(['paid_at' => now(), 'stripe_payment_intent' => $intent, 'payment_reference' => $intent ?? $session['id']]);

            // A refund may have arrived before this confirmation (see refund()).
            // Read with a lock: a refund being saved right now is either seen
            // here or, once this commits, finds the subscription itself.
            $refund = $intent === null ? null : DB::table('stripe_refunds')->where('payment_intent', $intent)->lockForUpdate()->first();
            if ($refund !== null && (bool) $refund->fully_refunded) {
                return $this->applyRefund($subscription, (int) $refund->amount_refunded, $refund->currency, true, true);
            }

            $active = $this->subscriptions->activate($subscription, null);
            Notify::staffWith('billing.subscriptions.manage', 'Card payment received',
                ($active->user->name ?? 'A student').' paid '.($active->qbank->name ?? 'a Qbank').' by card. Access is open.',
                '/admin/subscriptions', 'heroicon-o-credit-card', 'success');

            return $refund === null ? $active : $this->applyRefund($active, (int) $refund->amount_refunded, $refund->currency, false);
        }, 3);
    }

    /**
     * A card payment was refunded in Stripe (charge.refunded). A full refund
     * closes the access straight away; a partial refund keeps it and leaves
     * a note for an admin. Repeated events change nothing.
     *
     * The refund is always recorded first, so that one arriving before the
     * payment is confirmed is applied by fulfil() instead of being lost.
     *
     * @param  array<string, mixed>  $charge  The Charge, as Stripe sends it.
     */
    public function refund(array $charge): ?Subscription
    {
        $intent = is_string($charge['payment_intent'] ?? null) ? $charge['payment_intent'] : null;
        if ($intent === null) {
            return null;
        }
        $amount = (int) ($charge['amount_refunded'] ?? 0);
        $currency = is_string($charge['currency'] ?? null) ? strtolower($charge['currency']) : null;
        $full = ($charge['refunded'] ?? false) === true;

        return DB::transaction(function () use ($charge, $intent, $amount, $currency, $full): ?Subscription {
            DB::table('stripe_refunds')->upsert(
                [[
                    'payment_intent' => $intent, 'charge_id' => is_string($charge['id'] ?? null) ? $charge['id'] : null,
                    'amount_refunded' => $amount, 'currency' => $currency, 'fully_refunded' => $full,
                    'created_at' => now(), 'updated_at' => now(),
                ]],
                ['payment_intent'],
                ['charge_id', 'amount_refunded', 'currency', 'fully_refunded', 'updated_at'],
            );
            // Not confirmed yet: fulfil() will find the refund when the payment is.
            $subscription = Subscription::where('stripe_payment_intent', $intent)->lockForUpdate()->first();

            return $subscription === null ? null : $this->applyRefund($subscription, $amount, $currency, $full);
        }, 3);
    }

    /**
     * Applies a refund to a subscription (inside the caller's transaction).
     * $beforeAccess: the payment was refunded before access was ever opened.
     */
    private function applyRefund(Subscription $subscription, int $amount, ?string $currency, bool $full, bool $beforeAccess = false): Subscription
    {
        if ($subscription->status === Subscription::STATUS_CANCELLED) {
            return $subscription;
        }
        $qbank = $subscription->qbank->name ?? 'your Qbank';
        $student = $subscription->user->name ?? 'A student';
        $refunded = number_format($amount / 100, 2).' '.strtoupper($currency ?? (string) $subscription->currency);

        if (! $full) {
            $subscription->update(['admin_note' => 'Partly refunded in Stripe ('.$refunded.'). Access was kept; deactivate it here if it should end.']);
            Notify::staffWith('billing.subscriptions.manage', 'Card payment partly refunded',
                $student.': '.$refunded.' refunded for '.$qbank.'. Access was kept.',
                '/admin/subscriptions', 'heroicon-o-receipt-refund', 'warning');

            return $subscription;
        }

        $this->subscriptions->cancel($subscription, $beforeAccess
            ? 'Refunded in Stripe ('.$refunded.') before the payment was confirmed. Access was never opened.'
            : 'Refunded in Stripe ('.$refunded.') on '.now()->toFormattedDateString().'. Access closed automatically.');
        if ($subscription->user !== null) {
            Notify::student($subscription->user, 'Your payment was refunded', $beforeAccess
                ? 'Your card payment for '.$qbank.' was refunded, so the subscription was not opened.'
                : 'Your card payment for '.$qbank.' was refunded, so this access has been closed.', '/subscription', 'CreditCard', 'warning');
        }
        Notify::staffWith('billing.subscriptions.manage', 'Card payment refunded',
            $student.': '.$refunded.' refunded for '.$qbank.'. '.($beforeAccess ? 'Access was never opened.' : 'Access was closed automatically.'),
            '/admin/subscriptions', 'heroicon-o-receipt-refund', 'warning');

        return $subscription->refresh();
    }

    /** An abandoned Checkout Session: the unfinished purchase is closed. */
    public function expire(string $sessionId): void
    {
        Subscription::where('stripe_session_id', $sessionId)->where('status', Subscription::STATUS_CHECKOUT)
            ->update(['status' => Subscription::STATUS_CANCELLED, 'admin_note' => 'Card payment not completed (Checkout expired).']);
    }

    /** "8.00" → 800: Stripe wants the amount in the smallest unit (pence). */
    private function minorUnits(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{id: string, url: string}
     *
     * @throws ApiErrorException
     */
    protected function createSession(array $params): array
    {
        $session = $this->client()->checkout->sessions->create($params);

        return ['id' => (string) $session->id, 'url' => (string) $session->url];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ApiErrorException
     */
    protected function retrieveSession(string $sessionId): array
    {
        return $this->client()->checkout->sessions->retrieve($sessionId)->toArray();
    }

    private function client(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }
}
