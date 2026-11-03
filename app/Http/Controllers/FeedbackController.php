<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    private function exam(Request $request): Exam
    {
        return Exam::where('is_active', true)->find($request->session()->get('exam_id')) ?? Exam::where('is_active', true)->orderBy('id')->firstOrFail();
    }

    public function index(Request $request): Response
    {
        $exam = $this->exam($request);

        return Inertia::render('Feedback', [
            'exam' => $exam->only(['id', 'name']),
            'types' => collect(Feedback::TYPES)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values()->all(),
            'items' => Feedback::where('user_id', $request->user()?->id)->orderByDesc('id')->limit(10)
                ->get(['id', 'type', 'message', 'status', 'created_at'])
                ->map(fn (Feedback $item): array => [
                    'id' => $item->id,
                    'type' => Feedback::TYPES[$item->type] ?? $item->type,
                    'message' => $item->message,
                    'status' => $item->status,
                    'created_at' => $item->created_at?->toIso8601String(),
                ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $exam = $this->exam($request);
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(Feedback::TYPES))],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
        ]);
        /** @var User $user */
        $user = $request->user();
        Feedback::create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'type' => $data['type'],
            'message' => $data['message'],
            'status' => Feedback::STATUS_NEW,
        ]);

        return back()->with('status', 'Thank you. Your feedback has been sent to the MRCPWorld team.');
    }
}
