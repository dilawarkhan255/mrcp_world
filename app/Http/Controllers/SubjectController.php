<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Exam;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\User;
use App\Services\PracticeService;
use App\Support\CurrentExam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A subject is a category of the selected exam. Category slugs are unique per
 * exam, so the same slug may exist in another exam.
 */
class SubjectController extends Controller
{
    public function main(Request $request, string $slug, PracticeService $practice): Response|RedirectResponse
    {
        return $this->show($request, $slug, PracticeService::SOURCE_MAIN, $practice);
    }

    public function past(Request $request, string $slug, PracticeService $practice): Response|RedirectResponse
    {
        return $this->show($request, $slug, PracticeService::SOURCE_PAST, $practice);
    }

    /** Practise one MCQ from the subject's list. */
    public function practiseOne(Request $request, string $slug, PracticeService $practice): RedirectResponse
    {
        $exam = CurrentExam::content($request);
        $category = Category::where('exam_id', $exam->id)->where('slug', $slug)->firstOrFail();
        $data = $request->validate(['question_id' => ['required', 'integer'], 'source' => ['nullable', Rule::in([PracticeService::SOURCE_MAIN, PracticeService::SOURCE_PAST])]]);
        $source = (string) ($data['source'] ?? PracticeService::SOURCE_MAIN);
        // Past paper MCQs are for subscribers of this Qbank only (like the past paper pages).
        abort_if($source === PracticeService::SOURCE_PAST && ! CurrentExam::hasAccess($request), 403);
        // Only a question this student may see in this list (published, this subject, demo-only without access).
        $question = $practice->questions($exam->id, $category->id, null, $source, $source === PracticeService::SOURCE_MAIN && ! CurrentExam::hasAccess($request))
            ->whereKey((int) $data['question_id'])->firstOrFail();
        /** @var User $user */
        $user = $request->user();
        $session = $practice->startFromQuestions($user, $exam, [$question->id], PracticeSession::MODE_PRACTICE);

        return redirect()->route('practice.show', $session);
    }

    private function show(Request $request, string $slug, string $source, PracticeService $practice): Response|RedirectResponse
    {
        $exam = CurrentExam::content($request);

        $category = Category::where('exam_id', $exam->id)->where('slug', $slug)->first();
        if ($category === null) {
            // The slug belongs to another exam (for example, after switching exams): send the student to the bank.
            abort_unless(Category::where('slug', $slug)->exists(), 404);

            return redirect('/questions/bank');
        }

        $demoOnly = $source === PracticeService::SOURCE_MAIN && ! CurrentExam::hasAccess($request);
        // The MCQs of this tab, 20 a page: the stem only. Never the topic (it often names the diagnosis),
        // the answer or the explanation: those stay hidden until the MCQ is answered.
        $list = $practice->questions($exam->id, $category->id, null, $source, $demoOnly)
            ->with('pastPaper:id,title')
            ->orderBy('code')->orderBy('id')
            ->simplePaginate(20, ['id', 'code', 'body', 'past_paper_id'])->withQueryString()
            ->through(fn (Question $question): array => [
                'id' => $question->id,
                'code' => $question->code,
                'paper' => $question->pastPaper?->title,
                ...self::excerpt((string) $question->body),
            ]);

        return Inertia::render('questions/Subject', [
            'exam' => CurrentExam::props($request),
            'questions' => $list,
            'subject' => $category->only(['id', 'name', 'slug']),
            'source' => $source,
            'counts' => [
                'main' => $practice->questions($exam->id, $category->id, null, PracticeService::SOURCE_MAIN, ! CurrentExam::hasAccess($request))->count(),
                'past' => $practice->questions($exam->id, $category->id, null, PracticeService::SOURCE_PAST)->count(),
            ],
        ]);
    }

    /**
     * The opening of the case and the question asked, as plain text: "A
     * 24-year-old man… (220 characters)" and "Which of the following…?".
     *
     * @return array{excerpt: string, prompt: string|null}
     */
    private static function excerpt(string $body): array
    {
        $plain = fn (string $text): string => trim((string) preg_replace(['/[*_#>`]+/', '/\s+/'], ['', ' '], $text));
        // Paragraphs without the Investigations heading and table rows.
        $paragraphs = array_values(array_filter(array_map('trim', preg_split('/
\s*
/', $body) ?: []),
            fn (string $part): bool => $part !== '' && ! str_starts_with($part, '|') && ! preg_match('/^\*\*Investigations\*\*$/', $part)));
        $first = $paragraphs[0] ?? '';
        $last = count($paragraphs) > 1 ? $paragraphs[count($paragraphs) - 1] : null;

        return [
            'excerpt' => Str::limit($plain($first), 220),
            'prompt' => $last !== null && str_contains($last, '?') ? Str::limit($plain($last), 160) : null,
        ];
    }
}
