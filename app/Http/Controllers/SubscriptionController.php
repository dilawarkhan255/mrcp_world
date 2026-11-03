<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(): Response
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('duration_days')
            ->get()
            ->map(fn (Plan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => $plan->priceLabel(),
                'duration_days' => $plan->duration_days,
            ])
            ->all();

        return Inertia::render('Subscription', ['plans' => $plans]);
    }
}
