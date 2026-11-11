<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Exam;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        // Each exam has its own login link (/login?exam=mrcp-part-2-written): the page names the
        // exam, and the student lands in that exam after logging in.
        $exam = Exam::where('slug', $request->string('exam')->toString())->where('is_active', true)->first(['id', 'name', 'slug']);

        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'exam' => $exam?->only(['name', 'slug']),
            // A visitor sent here after choosing a plan sees why they must log in first.
            'subscribing' => str_contains((string) $request->session()->get('url.intended', ''), '/subscription'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $request->authenticate();

        $request->session()->regenerate();

        $examId = Exam::where('slug', (string) $request->input('exam', ''))->where('is_active', true)->value('id');
        if ($examId !== null) {
            $request->session()->put('exam_id', (int) $examId);
        }

        // Staff go to the panel; a student without a package buys one first.
        $default = match (true) {
            (bool) $request->user()?->isStaff() => '/admin',
            app(SubscriptionService::class)->hasAnyAccess($request->user()) => route('dashboard', absolute: false),
            default => route('subscription.index', absolute: false),
        };
        $destination = $request->session()->pull('url.intended', $default);

        // The admin panel is a plain HTML page, not an Inertia page. An Inertia request must make a full
        // browser visit to it; a normal redirect would render the panel inside the student app.
        if ($request->header('X-Inertia')) {
            return Inertia::location($destination);
        }

        return redirect($destination);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
