<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Exam;
use App\Models\PastPaper;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\PracticeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PracticeController extends Controller
{
    private function exam(Request $request): Exam
    {
        return Exam::where('is_active', true)->find($request->session()->get('exam_id')) ?? Exam::where('is_active', true)->orderBy('id')->firstOrFail();
    }

    public function bank(Request $request, PracticeService $practice): Response
    {
        $exam = $this->exam($request);

        return Inertia::render('questions/Bank', [
            'exam' => $exam->only(['id', 'name']),
            'categories' => Category::where('exam_id', $exam->id)->withCount(['questions' => fn ($query) => $query->published()])->orderBy('name')->simplePaginate(20)->withQueryString(),
            'availableQuestions' => $practice->questions($exam->id)->count(),
        ]);
    }

    public function papers(Request $request): Response
    {
        $exam = $this->exam($request);

        return Inertia::render('questions/Papers', [
            'exam' => $exam->only(['id', 'name']),
            'papers' => PastPaper::where('exam_id', $exam->id)->where('is_published', true)->withCount(['questions' => fn ($query) => $query->published()])->orderByDesc('year')->orderByDesc('id')->simplePaginate(20)->withQueryString(),
        ]);
    }

    public function store(Request $request, PracticeService $practice): RedirectResponse
    {
        $exam = $this->exam($request);
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:100'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('exam_id', $exam->id)],
            'past_paper_id' => ['nullable', 'integer', Rule::exists('past_papers', 'id')->where('exam_id', $exam->id)->where('is_published', true)],
        ]);
        /** @var User $user */
        $user = $request->user();
        $session = $practice->start($user, $exam, (int) $data['count'], isset($data['category_id']) ? (int) $data['category_id'] : null, isset($data['past_paper_id']) ? (int) $data['past_paper_id'] : null);

        return redirect()->route('practice.show', $session);
    }

    public function index(Request $request): Response
    {
        $exam = $this->exam($request);

        return Inertia::render('questions/Sessions', [
            'sessions' => PracticeSession::where('user_id', $request->user()?->id)->where('exam_id', $exam->id)->orderByDesc('id')
                ->simplePaginate(20, ['id', 'total_questions', 'answered_count', 'correct_count', 'completed_at', 'created_at'])->withQueryString(),
        ]);
    }

    public function show(Request $request, PracticeSession $session): Response
    {
        abort_unless($session->user_id === $request->user()?->id, 403);
        $data = $request->validate(['position' => ['nullable', 'integer', 'min:1', 'max:'.$session->total_questions]]);
        $position = isset($data['position']) ? (int) $data['position'] : ($session->items()->whereNull('answered_at')->orderBy('position')->value('position') ?? $session->total_questions);
        $item = $session->items()->where('position', $position)->firstOrFail();
        $snapshot = $item->snapshot;
        $question = ['position' => $item->position, 'body' => $snapshot['body'], 'options' => $snapshot['options'], 'selected_option' => $item->selected_option];
        if ($item->answered_at !== null) {
            $question['result'] = ['is_correct' => $item->is_correct, 'correct_option' => $snapshot['correct_option'], 'explanation' => $snapshot['explanation']];
        }

        return Inertia::render('questions/Practice', [
            'session' => $session->only(['id', 'total_questions', 'answered_count', 'correct_count', 'completed_at']),
            'question' => $question,
        ]);
    }

    public function answer(Request $request, PracticeSession $session, int $position, PracticeService $practice): RedirectResponse
    {
        abort_unless($session->user_id === $request->user()?->id, 403);
        $data = $request->validate(['option' => ['required', Rule::in(['A', 'B', 'C', 'D', 'E'])]]);
        $practice->answer($session, $position, $data['option']);

        return redirect()->route('practice.show', ['session' => $session, 'position' => $position]);
    }
}
