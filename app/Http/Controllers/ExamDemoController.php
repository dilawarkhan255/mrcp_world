<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Question;
use App\Support\QuestionContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExamDemoController extends Controller
{
    public function show(Request $request, string $slug): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('dashboard');
        }

        $exam = Exam::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $questions = Question::published()
            ->where('exam_id', $exam->contentId())
            ->where('is_demo', true)
            ->orderBy('id')
            ->limit(10)
            ->get(['id', 'body', 'options', 'correct_option', 'explanation']);

        return Inertia::render('ExamDemo', [
            'appName' => (string) config('app.name'),
            'exam' => [
                'name' => $exam->name,
                'slug' => $exam->slug,
                'description' => $exam->description,
            ],
            'questions' => $questions->map(fn (Question $question): array => [
                'id' => $question->id,
                'body' => $question->body,
                'body_html' => QuestionContent::html($question->body),
                'options' => $question->options,
                'correct_option' => $question->correct_option,
                'explanation' => $question->explanation,
                'explanation_html' => QuestionContent::html($question->explanation),
            ])->all(),
        ]);
    }
}
