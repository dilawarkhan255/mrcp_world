<?php

namespace App\Services;

use App\Models\Bookmark;
use App\Models\Exam;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PracticeService
{
    public const MAX_QUESTIONS = 100;

    public const MIN_DURATION_SECONDS = 60;

    public const MAX_DURATION_SECONDS = 10800;

    /** Main MCQ bank: questions that do not belong to a past paper. */
    public const SOURCE_MAIN = 'main';

    /** Past paper MCQs: questions that belong to a past paper. */
    public const SOURCE_PAST = 'past';

    /** @return Builder<Question> */
    public function questions(int $examId, ?int $categoryId = null, ?int $paperId = null, ?string $source = null): Builder
    {
        return Question::published()->where('exam_id', $examId)
            ->when($categoryId, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->when($paperId, fn (Builder $query) => $query->where('past_paper_id', $paperId))
            ->when($source === self::SOURCE_MAIN, fn (Builder $query) => $query->whereNull('past_paper_id'))
            ->when($source === self::SOURCE_PAST, fn (Builder $query) => $query->whereNotNull('past_paper_id'));
    }

    /**
     * @param  list<int>|null  $questionIds  Restrict the session to these question ids, in the given order.
     */
    public function start(
        User $user,
        Exam $exam,
        int $limit,
        ?int $categoryId,
        ?int $paperId,
        string $mode = PracticeSession::MODE_PRACTICE,
        ?int $durationSeconds = null,
        ?array $questionIds = null,
        ?string $source = null,
    ): PracticeSession {
        if ($limit < 1 || $limit > self::MAX_QUESTIONS) {
            throw ValidationException::withMessages(['count' => 'Choose 1–100 questions.']);
        }
        if ($durationSeconds !== null && ($durationSeconds < self::MIN_DURATION_SECONDS || $durationSeconds > self::MAX_DURATION_SECONDS)) {
            throw ValidationException::withMessages(['minutes' => 'Choose a duration between 1 and 180 minutes.']);
        }

        return DB::transaction(function () use ($user, $exam, $limit, $categoryId, $paperId, $mode, $durationSeconds, $questionIds, $source) {
            $base = $this->questions($exam->id, $categoryId, $paperId, $source);
            if ($questionIds !== null) {
                $base = $base->whereIn('id', $questionIds);
            }
            $min = (clone $base)->min('id');
            $max = (clone $base)->max('id');
            if ($min === null || $max === null) {
                throw ValidationException::withMessages(['count' => 'No published questions match these filters yet.']);
            }
            // Indexed seek with wraparound avoids ORDER BY RAND() on the entire question bank.
            $pivot = random_int((int) $min, (int) $max);
            $questions = (clone $base)->where('id', '>=', $pivot)->orderBy('id')->limit($limit)->get();
            if ($questions->count() < $limit) {
                $questions = $questions->concat((clone $base)->where('id', '<', $pivot)->orderBy('id')->limit($limit - $questions->count())->get());
            }
            $startedAt = now();
            $session = PracticeSession::create([
                'user_id' => $user->id, 'exam_id' => $exam->id, 'category_id' => $categoryId, 'past_paper_id' => $paperId,
                'mode' => $mode, 'total_questions' => $questions->count(),
                'duration_seconds' => $durationSeconds, 'started_at' => $startedAt,
                'expires_at' => $durationSeconds === null ? null : $startedAt->copy()->addSeconds($durationSeconds),
            ]);
            $items = [];
            foreach ($questions as $index => $question) {
                $items[] = [
                    'practice_session_id' => $session->id, 'question_id' => $question->id, 'position' => $index + 1,
                    'snapshot' => json_encode(['body' => $question->body, 'options' => $question->options, 'correct_option' => $question->correct_option, 'explanation' => $question->explanation, 'code' => $question->code], JSON_THROW_ON_ERROR),
                ];
            }
            DB::table('practice_items')->insert($items);

            return $session;
        }, 3);
    }

    /**
     * Start a session from a chosen set of questions, for example a review
     * queue or the bookmarks of a student.
     *
     * @param  list<int>  $questionIds
     */
    public function startFromQuestions(User $user, Exam $exam, array $questionIds, string $mode = PracticeSession::MODE_REVIEW): PracticeSession
    {
        $questionIds = array_values(array_unique(array_map('intval', $questionIds)));
        if ($questionIds === []) {
            throw ValidationException::withMessages(['question_ids' => 'Choose at least one question.']);
        }
        if (count($questionIds) > self::MAX_QUESTIONS) {
            throw ValidationException::withMessages(['question_ids' => 'Choose at most 100 questions.']);
        }
        $available = Question::published()->where('exam_id', $exam->id)->whereIn('id', $questionIds)->pluck('id')->all();
        if ($available === []) {
            throw ValidationException::withMessages(['question_ids' => 'None of these questions are available.']);
        }

        return $this->start($user, $exam, count($available), null, null, $mode, null, $available);
    }

    public function answer(PracticeSession $session, int $position, string $option): void
    {
        DB::transaction(function () use ($session, $position, $option) {
            $locked = PracticeSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($locked->expires_at !== null && $locked->expires_at->isPast()) {
                throw ValidationException::withMessages(['option' => 'Time is up for this mock. Unanswered questions stay unanswered.']);
            }
            $item = $locked->items()->where('position', $position)->firstOrFail();
            if ($item->answered_at !== null) {
                if ($item->selected_option !== $option) {
                    throw ValidationException::withMessages(['option' => 'This answer is already saved and cannot be changed.']);
                }

                return; // A retried HTTP submission must never count twice.
            }
            $snapshot = $item->snapshot;
            if (! isset($snapshot['options'][$option])) {
                throw ValidationException::withMessages(['option' => 'Choose an available answer.']);
            }
            $correct = $snapshot['correct_option'] === $option;
            $item->update(['selected_option' => $option, 'is_correct' => $correct, 'answered_at' => now()]);
            $answered = $locked->answered_count + 1;
            $locked->update([
                'answered_count' => $answered, 'correct_count' => $locked->correct_count + (int) $correct,
                'completed_at' => $answered === $locked->total_questions ? now() : null,
            ]);
        }, 3);
    }

    /**
     * Close a session that is no longer running. Answers already saved are kept;
     * unanswered questions stay unanswered and are never scored.
     */
    public function close(PracticeSession $session): void
    {
        DB::transaction(function () use ($session) {
            $locked = PracticeSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($locked->completed_at === null) {
                $locked->update(['completed_at' => now()]);
            }
        }, 3);
    }

    /**
     * The question shown to the student. A mock only reveals the correct answer
     * once the mock is closed, so the student is not given feedback mid-exam.
     *
     * @return array{position: int, body: string, options: array<string, string>, selected_option: string|null, question_id?: int, is_bookmarked?: bool, bookmark_id?: int|null, result?: array{is_correct: bool, correct_option: string, explanation: string|null}}
     */
    public function question(PracticeSession $session, int $position, ?User $user = null): array
    {
        $item = $session->items()->where('position', $position)->firstOrFail();
        $snapshot = $item->snapshot;
        $payload = [
            'position' => $item->position,
            'body' => $snapshot['body'],
            'options' => $snapshot['options'],
            'selected_option' => $item->selected_option,
        ];
        if ($item->question_id !== null) {
            $bookmark = $user === null ? null : Bookmark::where('user_id', $user->id)->where('question_id', $item->question_id)->first();
            $payload['question_id'] = $item->question_id;
            $payload['is_bookmarked'] = $bookmark !== null;
            $payload['bookmark_id'] = $bookmark?->id;
        }
        $reveal = $session->mode === PracticeSession::MODE_MOCK ? $session->completed_at !== null : true;
        if ($reveal && $item->answered_at !== null) {
            $payload['result'] = [
                'is_correct' => $item->is_correct,
                'correct_option' => $snapshot['correct_option'],
                'explanation' => $snapshot['explanation'],
            ];
        }

        return $payload;
    }

    /** The first unanswered position, or the last position when everything is answered. */
    public function nextPosition(PracticeSession $session, ?int $after = null): int
    {
        $unanswered = $session->items()->whereNull('answered_at')->orderBy('position');
        if ($after !== null) {
            $next = (clone $unanswered)->where('position', '>', $after)->value('position');
            if ($next !== null) {
                return (int) $next;
            }
        }
        $first = (clone $unanswered)->value('position');

        return $first !== null ? (int) $first : $session->total_questions;
    }

    /**
     * Per-question progress for the mock navigator.
     *
     * @return list<array{position: int, answered: bool}>
     */
    public function navigator(PracticeSession $session): array
    {
        return $session->items()->orderBy('position')->get(['position', 'answered_at'])
            ->map(fn ($item): array => ['position' => $item->position, 'answered' => $item->answered_at !== null])
            ->all();
    }
}
