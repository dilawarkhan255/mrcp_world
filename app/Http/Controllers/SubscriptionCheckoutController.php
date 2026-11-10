<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StripeCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pay for a Qbank plan by card on Stripe Checkout, and come back to the app
 * logged in. Activation itself happens in StripeCheckout::fulfil().
 */
class SubscriptionCheckoutController extends Controller
{
    public function store(Request $request, StripeCheckout $checkout): Response
    {
        abort_unless($checkout->enabled(), 404);
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)->whereNotNull('qbank_id')],
        ]);
        /** @var User $user */
        $user = $request->user();

        // Stripe's page is on another site: Inertia needs a full-page visit.
        return Inertia::location($checkout->start($user, Plan::findOrFail((int) $data['plan_id'])));
    }

    public function success(Request $request, StripeCheckout $checkout): RedirectResponse
    {
        $sessionId = $request->string('session_id')->toString();
        /** @var User $user */
        $user = $request->user();
        $subscription = $sessionId === '' ? null : $checkout->complete($user, $sessionId);

        if ($subscription?->status === Subscription::STATUS_ACTIVE) {
            return redirect()->route('dashboard')
                ->with('status', 'Thank you! Your payment was received and '.($subscription->qbank->name ?? 'your Qbank').' is open until '.$subscription->ends_at?->toFormattedDateString().'.');
        }

        // Some payment methods confirm a little later; the webhook will open access then.
        return redirect()->route('subscription.index')
            ->with('status', 'Thank you. We are waiting for Stripe to confirm your payment; your access opens automatically as soon as it does.');
    }
}
