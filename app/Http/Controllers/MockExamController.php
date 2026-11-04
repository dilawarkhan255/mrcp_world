<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\PracticeSession;
use App\Services\CommentService;
use App\Services\PracticeService;
use App\Support\CurrentExam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MockExamController extends Controller
{
    private function exam(Request $request): Exam
    {
        return CurrentExam::content($request);
    }

    public function index(Request $request, PracticeService $practice): Response
    {
        $exam = $this->exam($request);

        return Inertia::render('questions/Mocks', [
            'exam' => CurrentExam::props($request),
            'presets' => config('student.mocks.presets'),
            'availableQuestions' => $practice->questions($exam->id)->count(),
            'limits' => [
                'maxQuestions' => (int) config('student.mocks.max_questions'),
                'maxMinutes' => (int) config('student.mocks.max_minutes'),
            ],
            'attempts' => PracticeSession::where('user_id', $request->user()?->id)
                ->where('exam_id', $exam->id)
                ->where('mode', PracticeSession::MODE_MOCK)
                ->orderByDesc('id')
                ->simplePaginate(10, ['id', 'total_questions', 'answered_count', 'correct_count', 'completed_at', 'started_at', 'duration_seconds'])
                ->withQueryString(),
        ]);
    }

    public function store(Request $request, PracticeService $practice): RedirectResponse
    {
        $exam = $this->exam($request);
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:'.(int) config('student.mocks.max_questions')],
            'minutes' => ['required', 'integer', 'min:1', 'max:'.(int) config('student.mocks.max_minutes')],
        ]);
        $session = $practice->start(
            $request->user(),
            $exam,
            (int) $data['count'],
            null,
            null,
            PracticeSession::MODE_MOCK,
            (int) $data['minutes'] * 60,
        );

        return redirect()->route('mocks.show', $session);
    }

    public function show(Request $request, PracticeSession $session, PracticeService $practice, CommentService $comments): Response
    {
        abort_unless($session->user_id === $request->user()?->id, 403);
        abort_unless($session->mode === PracticeSession::MODE_MOCK, 404);

        if ($session->completed_at === null && $session->isExpired()) {
            $practice->close($session);
            $session->refresh();
        }

        $position = max(1, min((int) ($request->integer('position') ?: $practice->nextPosition($session)), $session->total_questions));

        return Inertia::render('questions/Mock', [
            'session' => [
                'id' => $session->id,
                'total_questions' => $session->total_questions,
                'answered_count' => $session->answered_count,
                'correct_count' => $session->correct_count,
                'completed_at' => $session->completed_at?->toIso8601String(),
                'started_at' => $session->started_at?->toIso8601String(),
                'expires_at' => $session->expires_at?->toIso8601String(),
                'duration_seconds' => $session->duration_seconds,
            ],
            'question' => $question = $practice->question($session, $position, $request->user()),
            ...PracticeController::discussion($question, $request, $comments),
            'navigator' => $practice->navigator($session),
            'serverTime' => now()->toIso8601String(),
        ]);
    }

    public function answer(Request $request, PracticeSession $session, int $position, PracticeService $practice): RedirectResponse
    {
        abort_unless($session->user_id === $request->user()?->id, 403);
        abort_unless($session->mode === PracticeSession::MODE_MOCK, 404);
        $data = $request->validate([
            'option' => ['required', Rule::in(['A', 'B', 'C', 'D', 'E'])],
            'seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);
        $practice->answer($session, $position, $data['option'], isset($data['seconds']) ? (int) $data['seconds'] : null);
        $session->refresh();
        $next = $session->completed_at !== null ? $position : $practice->nextPosition($session, $position);

        return redirect()->route('mocks.show', ['session' => $session, 'position' => $next]);
    }

    public function finish(Request $request, PracticeSession $session, PracticeService $practice): RedirectResponse
    {
        abort_unless($session->user_id === $request->user()?->id, 403);
        abort_unless($session->mode === PracticeSession::MODE_MOCK, 404);
        $practice->close($session);

        return redirect()->route('mocks.show', $session);
    }
}
