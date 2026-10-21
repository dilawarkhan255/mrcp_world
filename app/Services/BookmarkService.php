<?php

namespace App\Services;

use App\Models\Bookmark;
use App\Models\Exam;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class BookmarkService
{
    public const MAX_BOOKMARK_QUESTIONS = 50;

    /**
     * The bookmarks of a student for one exam, with the question and its category.
     *
     * @return Builder<Bookmark>
     */
    public function bookmarks(int $userId, int $examId): Builder
    {
        return Bookmark::where('user_id', $userId)
            ->whereHas('question', fn (Builder $query) => $query->where('questions.exam_id', $examId))
            ->with(['question:id,exam_id,code,body,category_id', 'question.category:id,name'])
            ->orderByDesc('id');
    }

    /** Save a question as a bookmark and keep the note of an existing bookmark. */
    public function add(User $user, int $questionId, ?string $note): Bookmark
    {
        $question = Question::published()->whereKey($questionId)->firstOrFail();

        return Bookmark::updateOrCreate(
            ['user_id' => $user->id, 'question_id' => $question->id],
            ['note' => $note],
        );
    }

    /** Practise the bookmarked questions of the selected exam. */
    public function startSession(User $user, Exam $exam): PracticeSession
    {
        $questionIds = $this->bookmarks($user->id, $exam->id)->limit(self::MAX_BOOKMARK_QUESTIONS)->pluck('question_id')->all();
        if ($questionIds === []) {
            throw ValidationException::withMessages(['question_ids' => 'There are no bookmarks for this exam yet.']);
        }

        return app(PracticeService::class)->startFromQuestions($user, $exam, $questionIds, PracticeSession::MODE_REVIEW);
    }
}
