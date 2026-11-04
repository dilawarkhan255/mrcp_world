<?php

namespace App\Http\Middleware;

use App\Support\CurrentExam;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages that need a subscription to the Qbank of the selected exam. A student
 * without one is sent to the subscription page; the free demo questions stay
 * available from the question bank.
 */
class EnsureQbankAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (CurrentExam::hasAccess($request)) {
            return $next($request);
        }
        $qbank = CurrentExam::selected($request)->qbank->name ?? 'this Qbank';
        $message = 'Subscribe to '.$qbank.' to open this page. The free demo questions are in the Main MCQ Bank.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('subscription.index')->with('status', $message);
    }
}
