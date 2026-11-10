<?php

namespace App\Http\Controllers;

use App\Services\StripeCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Stripe's webhook (POST /stripe/webhook, no CSRF token, no login). Every
 * event is checked against the signing secret before anything is read.
 * Set the endpoint in the Stripe dashboard with the events
 * checkout.session.completed, checkout.session.async_payment_succeeded,
 * checkout.session.expired and charge.refunded.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeCheckout $checkout): JsonResponse
    {
        $secret = (string) config('services.stripe.webhook_secret');
        abort_if($secret === '', 404);

        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        /** @var array<string, mixed> $object A Checkout Session, or a Charge for refunds. */
        $object = $event->data->object->toArray();
        match ($event->type) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $checkout->fulfil($object),
            'checkout.session.expired' => is_string($object['id'] ?? null) ? $checkout->expire($object['id']) : null,
            'charge.refunded' => $checkout->refund($object),
            default => null,
        };

        return response()->json(['received' => true]);
    }
}
