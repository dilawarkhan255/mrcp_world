<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use App\Support\CurrentExam;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The student app is for paying students: a student with no running
 * subscription to any Qbank is sent to the subscription page to buy a package
 * first. Staff (anyone with an admin permission) always get in. Which Qbank's
 * pages a subscriber may open is checked separately by EnsureQbankAccess. An
 * exam outside every Qbank is not sold, so it stays open (as in CurrentExam).
 */
class EnsureSubscribed
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->subscriptions->hasAnyAccess($request->user()) || CurrentExam::hasAccess($request)) {
            return $next($request);
        }
        $message = 'Choose a package to start. Your account opens as soon as the payment is confirmed.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('subscription.index')->with('status', $message);
    }
}
