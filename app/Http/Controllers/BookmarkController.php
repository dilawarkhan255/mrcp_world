<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Exam;
use App\Services\BookmarkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BookmarkController extends Controller
{
    private function exam(Request $request): Exam
    {
        return Exam::where('is_active', true)->find($request->session()->get('exam_id')) ?? Exam::where('is_active', true)->orderBy('id')->firstOrFail();
    }

    public function index(Request $request, BookmarkService $bookmarks): Response
    {
        $exam = $this->exam($request);
        $userId = (int) $request->user()?->id;
        $list = $bookmarks->bookmarks($userId, $exam->id)->simplePaginate(20)->withQueryString();

        return Inertia::render('revision/Bookmarks', [
            'exam' => $exam->only(['id', 'name']),
            'bookmarks' => [
                'data' => $list->getCollection()->map(fn (Bookmark $bookmark): array => [
                    'id' => $bookmark->id,
                    'question_id' => $bookmark->question_id,
                    'code' => $bookmark->question?->code,
                    'body' => $bookmark->question?->body,
                    'category' => $bookmark->question?->category?->name,
                    'note' => $bookmark->note,
                    'created_at' => $bookmark->created_at?->toIso8601String(),
                ])->all(),
                'current_page' => $list->currentPage(),
                'prev_page_url' => $list->previousPageUrl(),
                'next_page_url' => $list->nextPageUrl(),
            ],
            'stats' => [
                'total' => $bookmarks->bookmarks($userId, $exam->id)->count(),
                'withNotes' => $bookmarks->bookmarks($userId, $exam->id)->whereNotNull('note')->count(),
            ],
            'maxQuestions' => BookmarkService::MAX_BOOKMARK_QUESTIONS,
        ]);
    }

    public function store(Request $request, BookmarkService $bookmarks): RedirectResponse
    {
        $exam = $this->exam($request);
        $data = $request->validate([
            'question_id' => ['required', 'integer', Rule::exists('questions', 'id')
                ->where('exam_id', $exam->id)
                ->where('is_published', true)
                ->whereNull('deleted_at')],
            'note' => ['nullable', 'string', 'max:4000'],
        ]);
        $bookmarks->add($request->user(), (int) $data['question_id'], $data['note'] ?? null);

        return back();
    }

    public function update(Request $request, Bookmark $bookmark): RedirectResponse
    {
        abort_unless($bookmark->user_id === $request->user()?->id, 403);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:4000']]);
        $bookmark->update(['note' => $data['note'] ?? null]);

        return back();
    }

    public function destroy(Request $request, Bookmark $bookmark): RedirectResponse
    {
        abort_unless($bookmark->user_id === $request->user()?->id, 403);
        $bookmark->delete();

        return back();
    }

    public function practice(Request $request, BookmarkService $bookmarks): RedirectResponse
    {
        $exam = $this->exam($request);
        $session = $bookmarks->startSession($request->user(), $exam);

        return redirect()->route('practice.show', $session);
    }
}
