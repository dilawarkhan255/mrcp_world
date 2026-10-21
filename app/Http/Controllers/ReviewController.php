<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Question;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    private function exam(Request $request): Exam
    {
        return Exam::where('is_active', true)->find($request->session()->get('exam_id')) ?? Exam::where('is_active', true)->orderBy('id')->firstOrFail();
    }

    public function index(Request $request, ReviewService $review): Response
    {
        $exam = $this->exam($request);
        $userId = (int) $request->user()?->id;
        $counts = $review->wrongCounts($userId, $exam->id);
        $lastAnswers = $review->lastAnswers($userId, $exam->id);
        $queue = $review->queue($userId, $exam->id)->simplePaginate(20)->withQueryString();

        return Inertia::render('questions/Review', [
            'exam' => $exam->only(['id', 'name']),
            'items' => [
                'data' => $queue->getCollection()->map(fn (Question $question): array => [
                    'id' => $question->id,
                    'body' => $question->body,
                    'options' => $question->options,
                    'student_option' => $lastAnswers[$question->id]['selected_option'] ?? null,
                    'correct_option' => $question->correct_option,
                    'explanation' => $question->explanation,
                    'wrong_count' => $counts[$question->id] ?? 1,
                    'last_answered_at' => $lastAnswers[$question->id]['answered_at'] ?? null,
                ])->all(),
                'current_page' => $queue->currentPage(),
                'prev_page_url' => $queue->previousPageUrl(),
                'next_page_url' => $queue->nextPageUrl(),
            ],
            'stats' => [
                'queue' => $review->queueCount($userId, $exam->id),
                'answered' => $review->answeredQuestionCount($userId, $exam->id),
            ],
            'maxQuestions' => ReviewService::MAX_REVIEW_QUESTIONS,
        ]);
    }

    public function store(Request $request, ReviewService $review): RedirectResponse
    {
        $exam = $this->exam($request);
        $data = $request->validate([
            'question_ids' => ['nullable', 'array', 'max:'.ReviewService::MAX_REVIEW_QUESTIONS],
            'question_ids.*' => ['integer', 'min:1'],
        ]);
        $session = $review->start($request->user(), $exam, $data['question_ids'] ?? []);

        return redirect()->route('practice.show', $session);
    }
}
