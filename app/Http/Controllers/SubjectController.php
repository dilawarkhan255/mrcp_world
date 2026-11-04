<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Exam;
use App\Services\PracticeService;
use App\Support\CurrentExam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    private function show(Request $request, string $slug, string $source, PracticeService $practice): Response|RedirectResponse
    {
        $exam = CurrentExam::content($request);

        $category = Category::where('exam_id', $exam->id)->where('slug', $slug)->first();
        if ($category === null) {
            // The slug belongs to another exam (for example, after switching exams): send the student to the bank.
            abort_unless(Category::where('slug', $slug)->exists(), 404);

            return redirect('/questions/bank');
        }

        return Inertia::render('questions/Subject', [
            'exam' => CurrentExam::props($request),
            'subject' => $category->only(['id', 'name', 'slug']),
            'source' => $source,
            'counts' => [
                'main' => $practice->questions($exam->id, $category->id, null, PracticeService::SOURCE_MAIN, ! CurrentExam::hasAccess($request))->count(),
                'past' => $practice->questions($exam->id, $category->id, null, PracticeService::SOURCE_PAST)->count(),
            ],
        ]);
    }
}
