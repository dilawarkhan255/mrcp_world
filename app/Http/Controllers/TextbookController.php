<?php

namespace App\Http\Controllers;

use App\Models\TextbookChapter;
use App\Support\CurrentExam;
use App\Support\QuestionContent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The extended textbook of the selected exam: published chapters grouped by
 * subject. Chapters belong to the exam that owns the content, so IMM reads
 * the MRCP 1 textbook.
 */
class TextbookController extends Controller
{
    public function index(Request $request): Response
    {
        $exam = CurrentExam::content($request);
        $chapters = TextbookChapter::published()->where('textbook_chapters.exam_id', $exam->id)
            ->leftJoin('categories', 'categories.id', '=', 'textbook_chapters.category_id')
            ->orderByRaw('CASE WHEN categories.name IS NULL THEN 1 ELSE 0 END')
            ->orderBy('categories.name')->orderBy('textbook_chapters.sort_order')->orderBy('textbook_chapters.id')
            ->get(['textbook_chapters.id', 'textbook_chapters.title', 'textbook_chapters.slug', 'textbook_chapters.summary', 'categories.name as subject']);

        return Inertia::render('textbooks/Index', [
            'exam' => CurrentExam::props($request),
            'subjects' => $chapters->groupBy(fn (TextbookChapter $chapter): string => (string) ($chapter->getAttribute('subject') ?? 'General'))
                ->map(fn ($group, string $subject): array => [
                    'name' => $subject,
                    'chapters' => $group->map(fn (TextbookChapter $chapter): array => $chapter->only(['id', 'title', 'slug', 'summary']))->values()->all(),
                ])->values()->all(),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $exam = CurrentExam::content($request);
        $chapter = TextbookChapter::published()->where('exam_id', $exam->id)->where('slug', $slug)->with('category:id,name')->firstOrFail();
        // Neighbours in reading order within the same subject.
        $siblings = TextbookChapter::published()->where('exam_id', $exam->id)->where('category_id', $chapter->category_id)
            ->orderBy('sort_order')->orderBy('id')->get(['id', 'title', 'slug']);
        $index = $siblings->search(fn (TextbookChapter $item): bool => $item->id === $chapter->id);
        $previous = $index !== false && $index > 0 ? $siblings[$index - 1] : null;
        $next = $index !== false ? ($siblings[$index + 1] ?? null) : null;

        return Inertia::render('textbooks/Show', [
            'exam' => CurrentExam::props($request),
            'chapter' => [
                'title' => $chapter->title,
                'summary' => $chapter->summary,
                'subject' => $chapter->category?->name,
                'body_html' => QuestionContent::html($chapter->body),
                'key_points_html' => QuestionContent::html($chapter->key_points),
                'updated_at' => $chapter->updated_at?->toIso8601String(),
            ],
            'previous' => $previous?->only(['title', 'slug']),
            'next' => $next?->only(['title', 'slug']),
        ]);
    }
}
