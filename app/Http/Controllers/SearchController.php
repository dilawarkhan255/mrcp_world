<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\User;
use App\Services\PracticeService;
use App\Services\QuestionSearchService;
use App\Support\CurrentExam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    private function exam(Request $request): Exam
    {
        return CurrentExam::content($request);
    }

    /**
     * The trimmed search term. The length is checked after trimming, so "  " can never
     * become an empty LIKE pattern that matches every question.
     */
    private function term(Request $request, bool $required): string
    {
        $term = trim((string) $request->input('q', ''));
        if ($term === '' && ! $required) {
            return '';
        }
        $length = mb_strlen($term);
        if ($length < QuestionSearchService::MIN_LENGTH || $length > QuestionSearchService::MAX_LENGTH) {
            throw ValidationException::withMessages(['q' => 'Search for '.QuestionSearchService::MIN_LENGTH.' to '.QuestionSearchService::MAX_LENGTH.' characters.']);
        }

        return $term;
    }

    public function index(Request $request, QuestionSearchService $search): Response
    {
        $exam = $this->exam($request);
        $term = $this->term($request, required: false);

        $results = null;
        if ($term !== '') {
            $results = $search->matches($exam, $term)->paginate(QuestionSearchService::PAGE_SIZE)->withQueryString();
        }

        return Inertia::render('questions/Search', [
            'exam' => CurrentExam::props($request),
            'q' => $term,
            'total' => $results?->total() ?? 0,
            'practiceCount' => $results === null ? 0 : min($results->total(), QuestionSearchService::PRACTICE_LIMIT),
            'results' => $results === null ? null : [
                'data' => $results->getCollection()->map(fn (Question $question): array => [
                    'id' => $question->id,
                    'code' => $question->code,
                    'excerpt' => Str::limit($question->body, 220),
                    'subject' => $question->category?->name,
                    'source' => $question->past_paper_id === null ? 'Main MCQ Bank' : 'Past paper',
                ])->all(),
                'current_page' => $results->currentPage(),
                'prev_page_url' => $results->previousPageUrl(),
                'next_page_url' => $results->nextPageUrl(),
            ],
        ]);
    }

    public function practice(Request $request, QuestionSearchService $search, PracticeService $practice): RedirectResponse
    {
        $exam = $this->exam($request);
        $term = $this->term($request, required: true);
        $ids = $search->matches($exam, $term)->limit(QuestionSearchService::PRACTICE_LIMIT)->pluck('id')->all();
        if ($ids === []) {
            throw ValidationException::withMessages(['q' => 'No published questions match this search.']);
        }
        /** @var User $user */
        $user = $request->user();
        $session = $practice->startFromQuestions($user, $exam, $ids, PracticeSession::MODE_PRACTICE);

        return redirect()->route('practice.show', $session);
    }
}
