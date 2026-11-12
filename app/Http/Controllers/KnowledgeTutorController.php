<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Flashcard;
use App\Models\TutorRun;
use App\Models\User;
use App\Services\FlashcardService;
use App\Services\KnowledgeTutorService;
use App\Support\CurrentExam;
use App\Support\QuestionContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Knowledge Tutor: choose subjects, high-yield rating, difficulty and
 * how facts are picked; answer one fact at a time; see progress and the
 * facts answered so far (Review facts).
 */
class KnowledgeTutorController extends Controller
{
    public function __construct(private KnowledgeTutorService $tutor) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $exam = CurrentExam::content($request);
        $runs = TutorRun::where('user_id', $user->id)->where('exam_id', $exam->id)->latest('updated_at')->limit(5)->get();
        // Start with the filters of the last run, so "Start" carries on where the student was.
        $filters = $this->tutor->filters($runs->first()->filters ?? [], $exam);

        return Inertia::render('tutor/Index', [
            'exam' => CurrentExam::props($request),
            'overview' => $this->tutor->overview($user, $exam),
            'filters' => $filters,
            'count' => $this->tutor->count($user, $exam, $filters),
            'ratings' => KnowledgeTutorService::RATINGS,
            'modes' => KnowledgeTutorService::MODES,
            'runs' => $runs->map(fn (TutorRun $run): array => [
                'id' => $run->id, 'label' => $run->label, 'answered' => $run->answered, 'updated' => $run->updated_at?->diffForHumans(),
            ])->all(),
        ]);
    }

    /** "N facts found" while the student changes the filters. */
    public function count(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $exam = CurrentExam::content($request);

        return response()->json($this->tutor->count($user, $exam, $this->tutor->filters($this->validated($request), $exam)));
    }

    public function start(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $exam = CurrentExam::content($request);
        $filters = $this->tutor->filters($this->validated($request), $exam);
        $run = TutorRun::create(['user_id' => $user->id, 'exam_id' => $exam->id, 'filters' => $filters, 'label' => $this->tutor->label($filters, $exam)]);

        return redirect()->route('tutor.study', $run);
    }

    public function study(Request $request, TutorRun $run, FlashcardService $cards): Response
    {
        $this->authorizeRun($request, $run);
        /** @var User $user */
        $user = $request->user();
        $exam = CurrentExam::content($request);
        $card = $this->tutor->next($user, $run, $exam);
        $review = $card === null ? null : DB::table('flashcard_reviews')->where('user_id', $user->id)->where('flashcard_id', $card->id)->first();

        return Inertia::render('tutor/Study', [
            'exam' => CurrentExam::props($request),
            'run' => ['id' => $run->id, 'label' => $run->label, 'answered' => $run->answered, 'remaining' => $this->tutor->remaining($user, $run, $exam)],
            'card' => $card === null ? null : [
                'id' => $card->id,
                'front_html' => QuestionContent::html($card->front),
                'back_html' => QuestionContent::html($card->back),
                'previews' => $cards->previews($user, $card),
                'high_yield' => $card->high_yield,
                'difficulty' => $card->difficulty,
                'subject' => $card->category_id === null ? 'General' : (string) Category::whereKey($card->category_id)->value('name'),
                'status' => KnowledgeTutorService::status($review),
                'question_id' => $card->question_id,
            ],
        ]);
    }

    public function answer(Request $request, TutorRun $run, Flashcard $flashcard, FlashcardService $cards): RedirectResponse
    {
        $this->authorizeRun($request, $run);
        abort_unless($flashcard->is_published && $flashcard->exam_id === CurrentExam::content($request)->id, 404);
        $data = $request->validate(['rating' => ['required', Rule::in(FlashcardService::RATINGS)]]);
        /** @var User $user */
        $user = $request->user();
        $cards->review($user, $flashcard, (string) $data['rating']);
        $run->increment('answered');

        return redirect()->route('tutor.study', $run);
    }

    /** Review facts: what the student has answered, with each fact's status. */
    public function facts(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $exam = CurrentExam::content($request);
        $status = $request->query('status');
        $status = is_string($status) && in_array($status, ['weak', 'learning', 'recalled', 'mastered', 'due'], true) ? $status : null;
        $subject = $request->query('subject');
        $categoryId = is_numeric($subject) ? (int) $subject : null;
        $search = is_string($request->query('search')) ? Str::limit(trim((string) $request->query('search')), 100, '') : null;
        $page = $this->tutor->answered($user, $exam, $status, $categoryId, $search);

        return Inertia::render('tutor/Facts', [
            'exam' => CurrentExam::props($request),
            'filters' => ['status' => $status, 'subject' => $categoryId, 'search' => $search],
            'subjects' => collect($this->tutor->overview($user, $exam)['subjects'])->map(fn (array $row): array => ['id' => $row['id'], 'name' => $row['name']])->all(),
            'facts' => [
                'data' => collect($page->items())->map(fn (Flashcard $card): array => [
                    'id' => $card->id,
                    'front_html' => QuestionContent::html($card->front),
                    'back_html' => QuestionContent::html($card->back),
                    'subject' => (string) ($card->getAttribute('subject_name') ?? 'General'),
                    'high_yield' => $card->high_yield,
                    'status' => KnowledgeTutorService::status((object) $card->only(['repetitions', 'interval_days', 'lapses'])),
                    'answers' => (int) $card->getAttribute('answers'),
                    'last_rating' => $card->getAttribute('last_rating'),
                    'due' => $card->getAttribute('due_at') === null ? null : Carbon::parse((string) $card->getAttribute('due_at'))->diffForHumans(),
                ])->all(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'prev' => $page->previousPageUrl(),
                'next' => $page->nextPageUrl(),
            ],
        ]);
    }

    /** Forget a fact: it is asked again as a new one. */
    public function reset(Request $request, Flashcard $flashcard): RedirectResponse
    {
        abort_unless($flashcard->exam_id === CurrentExam::content($request)->id, 404);
        /** @var User $user */
        $user = $request->user();
        $this->tutor->reset($user, $flashcard);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'categories' => ['nullable', 'array', 'max:100'],
            'categories.*' => ['integer', 'min:0'],
            'rating' => ['nullable', 'string', Rule::in(array_keys(KnowledgeTutorService::RATINGS))],
            'difficulty' => ['nullable', 'array', 'size:2'],
            'difficulty.*' => ['integer', 'min:0', 'max:100'],
            'mode' => ['nullable', 'string', Rule::in(array_keys(KnowledgeTutorService::MODES))],
        ]);
    }

    /** A run belongs to its student and to the exam they have selected. */
    private function authorizeRun(Request $request, TutorRun $run): void
    {
        abort_unless($run->user_id === $request->user()?->id && $run->exam_id === CurrentExam::content($request)->id, 404);
    }
}
