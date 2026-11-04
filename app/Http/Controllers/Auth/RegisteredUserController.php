<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Plan;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * An account is created when a student buys a subscription: the sign-up form
 * asks for the plan and the payment reference, and the account and the
 * subscription request are saved together. An admin activates the request
 * once the payment is confirmed (payment is manual for now).
 */
class RegisteredUserController extends Controller
{
    public function create(Request $request): Response
    {
        $exam = Exam::where('slug', $request->string('exam')->toString())->where('is_active', true)->first();

        return Inertia::render('auth/Register', [
            'selectedExam' => $exam?->only(['name', 'slug']),
            'plans' => $this->plans(),
            'selectedPlan' => $this->chosenPlan($request, $exam),
            'paymentInstructions' => (string) config('student.subscription.payment_instructions'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)->whereNotNull('qbank_id')],
            'payment_reference' => ['required', 'string', 'min:3', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'plan_id.required' => 'Choose the Qbank you are subscribing to.',
            'payment_reference.required' => 'Enter the reference of your payment.',
        ]);
        $plan = Plan::with('qbank')->findOrFail($request->integer('plan_id'));
        if ($plan->qbank === null || ! $plan->qbank->is_active) {
            throw ValidationException::withMessages(['plan_id' => 'This Qbank is not on sale at the moment.']);
        }

        // The account and its subscription request are saved together or not at all.
        $user = DB::transaction(function () use ($request, $plan, $subscriptions): User {
            $user = User::create([
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->toString(),
                'password' => Hash::make($request->string('password')->toString()),
            ]);
            $user->assignRole(Role::findOrCreate('student', 'web'));
            $subscriptions->request($user, $plan->qbank, $plan, $request->string('payment_reference')->toString(), $request->filled('note') ? $request->string('note')->toString() : null);

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);
        $request->session()->forget('url.intended');

        return redirect()->route('subscription.index')
            ->with('status', 'Your account is ready and your request for '.$plan->qbank->name.' was sent. Access opens once the team confirms your payment.');
    }

    /**
     * Plans on sale, one or more per Qbank.
     *
     * @return list<array{id: int, qbank: string, exams: list<string>, price: string, duration: string}>
     */
    private function plans(): array
    {
        return Plan::where('is_active', true)->whereNotNull('qbank_id')
            ->whereHas('qbank', fn ($query) => $query->where('is_active', true))
            ->with(['qbank:id,name', 'qbank.exams' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->select(['id', 'qbank_id', 'name'])])
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (Plan $plan): array => [
                'id' => $plan->id,
                'qbank' => (string) $plan->qbank?->name,
                'exams' => $plan->qbank?->exams->pluck('name')->all() ?? [],
                'price' => $plan->priceLabel(),
                'duration' => $plan->durationLabel(),
            ])->all();
    }

    /** The plan from ?plan=, or the plan of the Qbank of ?exam=, when it is on sale. */
    private function chosenPlan(Request $request, ?Exam $exam): ?int
    {
        $query = Plan::where('is_active', true)->whereNotNull('qbank_id');
        $id = filter_var($request->query('plan'), FILTER_VALIDATE_INT);
        if ($id !== false) {
            return (clone $query)->whereKey($id)->value('id');
        }
        if ($exam?->qbank_id !== null) {
            return (clone $query)->where('qbank_id', $exam->qbank_id)->orderBy('sort_order')->value('id');
        }

        return null;
    }
}
