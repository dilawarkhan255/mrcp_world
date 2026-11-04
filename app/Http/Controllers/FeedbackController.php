<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Feedback;
use App\Models\User;
use App\Support\CurrentExam;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    private function exam(Request $request): Exam
    {
        return CurrentExam::content($request);
    }

    public function index(Request $request): Response
    {
        $exam = $this->exam($request);

        return Inertia::render('Feedback', [
            'exam' => CurrentExam::props($request),
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
            'question_id' => ['nullable', 'integer', Rule::exists('questions', 'id')->where('exam_id', $exam->id)->whereNull('deleted_at')],
        ]);
        /** @var User $user */
        $user = $request->user();
        Feedback::create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'question_id' => isset($data['question_id']) ? (int) $data['question_id'] : null,
            'type' => $data['type'],
            'message' => $data['message'],
            'status' => Feedback::STATUS_NEW,
        ]);

        $isReport = isset($data['question_id']);
        Notify::staffWith('community.comments.moderate', $isReport ? 'New MCQ report' : 'New student feedback',
            $user->name.': '.$data['message'], '/admin/feedback', $isReport ? 'heroicon-o-flag' : 'heroicon-o-chat-bubble-left-ellipsis', 'warning');

        return back()->with('status', isset($data['question_id'])
            ? 'Thank you. The MCQ report has been sent to the editors.'
            : 'Thank you. Your feedback has been sent to the '.config('app.name').' team.');
    }
}
