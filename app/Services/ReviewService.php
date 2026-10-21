<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    public const MAX_REVIEW_QUESTIONS = 50;

    /**
     * Questions whose most recent saved answer was wrong. A question leaves the
     * queue as soon as the student answers it correctly in a later session.
     *
     * @return Builder<Question>
     */
    public function queue(int $userId, int $examId): Builder
    {
        return Question::query()
            ->published()
            ->where('questions.exam_id', $examId)
            ->joinSub($this->lastAnswersQuery($userId, $examId), 'last_review', fn (JoinClause $join) => $join->on('questions.id', '=', 'last_review.question_id'))
            ->where('last_review.is_correct', false)
            ->orderByDesc('last_review.answered_at')
            ->select('questions.*');
    }

    /**
     * The most recent saved answer per question.
     *
     * @return array<int, array{selected_option: string|null, answered_at: string|null}>
     */
    public function lastAnswers(int $userId, int $examId): array
    {
        return $this->lastAnswersQuery($userId, $examId)->get()
            ->mapWithKeys(fn ($answer): array => [
                (int) $answer->question_id => ['selected_option' => $answer->selected_option, 'answered_at' => $answer->answered_at],
            ])
            ->all();
    }

    /**
     * The most recent saved answer per question.
     */
    private function lastAnswersQuery(int $userId, int $examId): QueryBuilder
    {
        $lastItem = DB::table('practice_items as pi')
            ->join('practice_sessions as ps', 'ps.id', '=', 'pi.practice_session_id')
            ->where('ps.user_id', $userId)
            ->where('ps.exam_id', $examId)
            ->whereNotNull('pi.question_id')
            ->whereNotNull('pi.answered_at')
            ->groupBy('pi.question_id')
            ->select('pi.question_id')
            ->selectRaw('MAX(pi.id) as last_item_id');

        return DB::table('practice_items as li')
            ->joinSub($lastItem, 'm', fn (JoinClause $join) => $join->on('li.id', '=', 'm.last_item_id'))
            ->select('li.question_id', 'li.is_correct', 'li.selected_option', 'li.answered_at');
    }

    /** How many questions are currently waiting for review. */
    public function queueCount(int $userId, int $examId): int
    {
        return (clone $this->queue($userId, $examId))->reorder()->count('questions.id');
    }

    /**
     * How many times each question was answered wrongly.
     *
     * @return array<int, int>
     */
    public function wrongCounts(int $userId, int $examId): array
    {
        return DB::table('practice_items as pi')
            ->join('practice_sessions as ps', 'ps.id', '=', 'pi.practice_session_id')
            ->where('ps.user_id', $userId)
            ->where('ps.exam_id', $examId)
            ->whereNotNull('pi.question_id')
            ->where('pi.is_correct', false)
            ->groupBy('pi.question_id')
            ->select('pi.question_id')
            ->selectRaw('COUNT(*) as wrong_count')
            ->pluck('wrong_count', 'question_id')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /** Distinct questions the student has already answered in this exam. */
    public function answeredQuestionCount(int $userId, int $examId): int
    {
        return DB::table('practice_items as pi')
            ->join('practice_sessions as ps', 'ps.id', '=', 'pi.practice_session_id')
            ->where('ps.user_id', $userId)
            ->where('ps.exam_id', $examId)
            ->whereNotNull('pi.question_id')
            ->whereNotNull('pi.answered_at')
            ->distinct()
            ->count('pi.question_id');
    }

    /**
     * Start a review session. Without explicit ids the whole queue is used,
     * capped at the review limit.
     *
     * @param  list<int>  $questionIds
     */
    public function start(User $user, Exam $exam, array $questionIds = []): PracticeSession
    {
        $questionIds = array_values(array_unique(array_map('intval', $questionIds)));
        if ($questionIds === []) {
            $questionIds = $this->queue($user->id, $exam->id)->limit(self::MAX_REVIEW_QUESTIONS)->pluck('questions.id')->all();
        }
        if ($questionIds === []) {
            throw ValidationException::withMessages(['question_ids' => 'There is nothing to review yet. Answer some questions first.']);
        }
        if (count($questionIds) > self::MAX_REVIEW_QUESTIONS) {
            throw ValidationException::withMessages(['question_ids' => 'Review at most 50 questions at a time.']);
        }
        $available = Question::published()->where('exam_id', $exam->id)->whereIn('id', $questionIds)->pluck('id')->all();
        if ($available === []) {
            throw ValidationException::withMessages(['question_ids' => 'None of these questions are available for review.']);
        }

        return app(PracticeService::class)->start($user, $exam, count($available), null, null, PracticeSession::MODE_REVIEW, null, $available);
    }
}
