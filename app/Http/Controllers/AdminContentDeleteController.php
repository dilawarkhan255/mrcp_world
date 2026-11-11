<?php

namespace App\Http\Controllers;

use App\Models\AtlasImage;
use App\Models\Flashcard;
use App\Models\Question;
use App\Models\QuestionComment;
use App\Models\TextbookChapter;
use App\Support\CurrentExam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The small Delete buttons an admin sees in the student app (MCQ, comment,
 * textbook chapter, flashcard, atlas picture). Admin role only. An MCQ is
 * archived (hidden everywhere; restore or delete it for good in the admin
 * panel), so students' old results keep working; the rest is deleted.
 */
class AdminContentDeleteController extends Controller
{
    public function question(Request $request, Question $question): RedirectResponse
    {
        $this->admin($request);
        $question->delete();

        return back()->with('status', 'MCQ deleted (archived). Restore or delete it permanently in the admin panel: MCQs → Archived.');
    }

    public function comment(Request $request, QuestionComment $comment): RedirectResponse
    {
        $this->admin($request);
        DB::transaction(function () use ($comment) {
            QuestionComment::where('parent_id', $comment->id)->delete();
            $comment->delete();
        });

        return back()->with('status', 'Comment deleted.');
    }

    public function chapter(Request $request, string $slug): RedirectResponse
    {
        $this->admin($request);
        TextbookChapter::where('exam_id', CurrentExam::content($request)->id)->where('slug', $slug)->firstOrFail()->delete();

        return redirect('/textbooks')->with('status', 'Chapter deleted.');
    }

    public function flashcard(Request $request, Flashcard $flashcard): RedirectResponse
    {
        $this->admin($request);
        $flashcard->delete();

        return back()->with('status', 'Flashcard deleted.');
    }

    public function atlas(Request $request, AtlasImage $image): RedirectResponse
    {
        $this->admin($request);
        $image->delete();

        return back()->with('status', 'Image deleted.');
    }

    private function admin(Request $request): void
    {
        abort_unless((bool) $request->user()?->hasRole('admin'), 403);
    }
}
