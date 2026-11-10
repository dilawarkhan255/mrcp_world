<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Plan;
use App\Models\Qbank;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\CurrentExam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The student's subscription page: one card per Qbank with its plan and the
 * student's state, and a request form. Payment is manual for now, so a
 * request waits until an admin activates it.
 */
class SubscriptionController extends Controller
{
    public function index(Request $request, SubscriptionService $subscriptions): Response
    {
        /** @var User $user */
        $user = $request->user();
        $mine = Subscription::where('user_id', $user->id)->with('qbank:id,name')->orderByDesc('id')->limit(20)->get();
        $qbanks = Qbank::where('is_active', true)->orderBy('sort_order')->orderBy('id')
            ->with([
                'exams' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->select(['id', 'qbank_id', 'name']),
                'plans' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('duration_days'),
            ])
            ->get();
        $selected = CurrentExam::contentId($request) === null ? null : CurrentExam::selected($request)->qbank_id;

        return Inertia::render('Subscription', [
            'qbanks' => $qbanks->map(function (Qbank $qbank) use ($user, $subscriptions, $mine, $selected): array {
                $current = $subscriptions->current($user, $qbank->id);

                return [
                    'id' => $qbank->id,
                    'name' => $qbank->name,
                    'description' => $qbank->description,
                    'exams' => $qbank->exams->map(fn (Exam $exam): string => $exam->name)->all(),
                    'is_selected' => $selected === $qbank->id,
                    'has_access' => $subscriptions->hasAccess($user, $qbank->id),
                    'ends_at' => $current?->ends_at?->toIso8601String(),
                    'pending' => $mine->contains(fn (Subscription $row): bool => $row->qbank_id === $qbank->id && $row->status === Subscription::STATUS_PENDING),
                    'plans' => $qbank->plans->map(fn (Plan $plan): array => [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'price' => $plan->priceLabel(),
                        'duration_days' => $plan->duration_days,
                        'duration' => $plan->durationLabel(),
                    ])->all(),
                ];
            })->all(),
            'history' => $mine->map(fn (Subscription $row): array => [
                'id' => $row->id,
                'qbank' => $row->qbank?->name,
                'state' => $row->stateLabel(),
                'status' => $row->status,
                'starts_at' => $row->starts_at?->toIso8601String(),
                'ends_at' => $row->ends_at?->toIso8601String(),
                'created_at' => $row->created_at?->toIso8601String(),
            ])->all(),
            'paymentInstructions' => (string) config('student.subscription.payment_instructions'),
            'selectedPlan' => $this->chosenPlan($request),
            // Back from Stripe Checkout without paying.
            'checkoutCancelled' => $request->query('checkout') === 'cancelled',
        ]);
    }

    /** The ?plan= of the landing page, when it names a plan on sale. */
    private function chosenPlan(Request $request): ?int
    {
        $id = filter_var($request->query('plan'), FILTER_VALIDATE_INT);

        return $id !== false && Plan::whereKey($id)->where('is_active', true)->whereNotNull('qbank_id')->exists() ? (int) $id : null;
    }

    public function store(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)->whereNotNull('qbank_id')],
            'payment_reference' => ['required', 'string', 'min:3', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $plan = Plan::with('qbank')->findOrFail((int) $data['plan_id']);
        abort_if($plan->qbank === null || ! $plan->qbank->is_active, 404);
        /** @var User $user */
        $user = $request->user();
        $subscriptions->request($user, $plan->qbank, $plan, $data['payment_reference'], $data['note'] ?? null);

        return back()->with('status', 'Thank you. Your request for '.$plan->qbank->name.' was sent. Access opens as soon as the team confirms your payment.');
    }
}
