<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Exam;
use App\Models\PastPaper;
use App\Models\PracticeSession;
use App\Models\QuestionComment;
use App\Models\User;
use App\Services\CommentService;
use App\Services\PracticeService;
use App\Services\SubscriptionService;
use App\Support\CurrentExam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PracticeController extends Controller
{
    private function exam(Request $request): Exam
    {
        return CurrentExam::content($request);
    }

    public function bank(Request $request, PracticeService $practice): Response
    {
        $exam = $this->exam($request);
        $demoOnly = ! CurrentExam::hasAccess($request);

        return Inertia::render('questions/Bank', [
            'exam' => CurrentExam::props($request),
            'categories' => Category::where('exam_id', $exam->id)
                ->withCount(['questions' => fn ($query) => $query->published()->whereNull('past_paper_id')->when($demoOnly, fn ($query) => $query->where('is_demo', true))])
                ->orderBy('name')->simplePaginate(20)->withQueryString(),
            'availableQuestions' => $practice->questions($exam->id, source: PracticeService::SOURCE_MAIN, demoOnly: $demoOnly)->count(),
            'demoOnly' => $demoOnly,
            'maxQuestions' => PracticeService::MAX_QUESTIONS,
        ]);
    }

    public function papers(Request $request): Response
    {
        $exam = $this->exam($request);

        return Inertia::render('questions/Papers', [
            'exam' => CurrentExam::props($request),
            'subjects' => Category::where('exam_id', $exam->id)
                ->withCount(['questions' => fn ($query) => $query->published()->whereNotNull('past_paper_id')])
                ->orderBy('name')->simplePaginate(20, ['*'], 'subjects')->withQueryString(),
            'papers' => PastPaper::where('exam_id', $exam->id)->where('is_published', true)->withCount(['questions' => fn ($query) => $query->published()])->orderByDesc('year')->orderByDesc('id')
                ->simplePaginate(20, ['id', 'title', 'year', 'sitting', 'description', 'image_path'])->withQueryString()
                ->through(fn (PastPaper $paper): array => [
                    'id' => $paper->id, 'title' => $paper->title, 'year' => $paper->year, 'sitting' => $paper->sitting,
                    'description' => $paper->description, 'image_url' => $paper->imageUrl(), 'questions_count' => (int) $paper->getAttribute('questions_count'),
                ]),
        ]);
    }

    public function store(Request $request, PracticeService $practice): RedirectResponse
    {
        $exam = $this->exam($request);
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:'.PracticeService::MAX_QUESTIONS],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('exam_id', $exam->id)],
            'past_paper_id' => ['nullable', 'integer', Rule::exists('past_papers', 'id')->where('exam_id', $exam->id)->where('is_published', true)],
            'source' => ['nullable', 'string', Rule::in([PracticeService::SOURCE_MAIN, PracticeService::SOURCE_PAST])],
        ]);
        $demoOnly = ! CurrentExam::hasAccess($request);
        if ($demoOnly && (isset($data['past_paper_id']) || ($data['source'] ?? null) === PracticeService::SOURCE_PAST)) {
            return redirect()->route('subscription.index')->with('status', 'Past paper MCQs need a subscription. The free demo questions are in the Main MCQ Bank.');
        }
        /** @var User $user */
        $user = $request->user();
        $session = $practice->start(
            $user,
            $exam,
            (int) $data['count'],
            isset($data['category_id']) ? (int) $data['category_id'] : null,
            isset($data['past_paper_id']) ? (int) $data['past_paper_id'] : null,
            source: $demoOnly ? PracticeService::SOURCE_MAIN : ($data['source'] ?? null),
            demoOnly: $demoOnly,
        );

        return redirect()->route('practice.show', $session);
    }

    public function index(Request $request): Response
    {
        // Rows come from the Yajra endpoint (StudentTableController::sessions).
        return Inertia::render('questions/Sessions', ['exam' => CurrentExam::props($request)]);
    }

    public function show(Request $request, PracticeSession $session, PracticeService $practice, CommentService $comments): Response|RedirectResponse
    {
        abort_unless($session->user_id === $request->user()?->id, 403);
        if ($locked = $this->locked($request, $session, $practice)) {
            return $locked;
        }
        $data = $request->validate(['position' => ['nullable', 'integer', 'min:1', 'max:'.$session->total_questions]]);
        $position = isset($data['position']) ? (int) $data['position'] : $practice->nextPosition($session);
        $question = $practice->question($session, $position, $request->user());

        return Inertia::render('questions/Practice', [
            'session' => $session->only(['id', 'total_questions', 'answered_count', 'correct_count', 'completed_at']),
            'question' => $question,
            ...$this->discussion($question, $request, $comments),
            'navigator' => $practice->navigator($session),
            'maxQuestions' => PracticeService::MAX_QUESTIONS,
        ]);
    }

    public function answer(Request $request, PracticeSession $session, int $position, PracticeService $practice): RedirectResponse
    {
        abort_unless($session->user_id === $request->user()?->id, 403);
        if ($locked = $this->locked($request, $session, $practice)) {
            return $locked;
        }
        $data = $request->validate([
            'option' => ['required', Rule::in(['A', 'B', 'C', 'D', 'E'])],
            'seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);
        $practice->answer($session, $position, $data['option'], isset($data['seconds']) ? (int) $data['seconds'] : null);

        return redirect()->route('practice.show', ['session' => $session, 'position' => $position]);
    }

    /**
     * Comment threads of the question. They are only sent once the answer is
     * revealed, because a discussion can give the answer away.
     *
     * @param  array<string, mixed>  $question
     * @return array{comments: list<array<string, mixed>>|null, canModerate: bool}
     */
    public static function discussion(array $question, Request $request, CommentService $comments): array
    {
        $user = $request->user();
        // The discussion is part of the subscription, and opens once the answer is revealed.
        $visible = isset($question['result'], $question['question_id']) && CurrentExam::hasAccess($request);

        return [
            'comments' => $visible ? $comments->forQuestion((int) $question['question_id'], $user) : null,
            'canModerate' => $user !== null && $user->can('moderate', QuestionComment::class),
        ];
    }

    /**
     * A session with questions outside the free demo needs a subscription to the
     * Qbank of the session's exam, for example after a subscription expired.
     */
    private function locked(Request $request, PracticeSession $session, PracticeService $practice): ?RedirectResponse
    {
        $qbankId = Exam::whereKey($session->exam_id)->value('qbank_id');
        if (app(SubscriptionService::class)->hasAccess($request->user(), $qbankId === null ? null : (int) $qbankId) || $practice->isDemoSession($session)) {
            return null;
        }

        return redirect()->route('subscription.index')->with('status', 'This session needs an active subscription. Renew it to continue.');
    }

    public function read(Request $request, PracticeSession $session, int $position, PracticeService $practice): RedirectResponse
    {
        abort_unless($session->user_id === $request->user()?->id, 403);
        abort_if($position < 1 || $position > $session->total_questions, 404);
        $data = $request->validate(['read' => ['required', 'boolean']]);
        $practice->markRead($session, $position, (bool) $data['read']);

        return back();
    }
}
