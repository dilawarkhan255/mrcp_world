<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PracticeService
{
    /** @return Builder<Question> */
    public function questions(int $examId, ?int $categoryId = null, ?int $paperId = null): Builder
    {
        return Question::published()->where('exam_id', $examId)
            ->when($categoryId, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->when($paperId, fn (Builder $query) => $query->where('past_paper_id', $paperId));
    }

    public function start(User $user, Exam $exam, int $limit, ?int $categoryId, ?int $paperId): PracticeSession
    {
        if ($limit < 1 || $limit > 100) {
            throw ValidationException::withMessages(['count' => 'Choose 1–100 questions.']);
        }

        return DB::transaction(function () use ($user, $exam, $limit, $categoryId, $paperId) {
            $base = $this->questions($exam->id, $categoryId, $paperId);
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
            $session = PracticeSession::create([
                'user_id' => $user->id, 'exam_id' => $exam->id, 'category_id' => $categoryId, 'past_paper_id' => $paperId,
                'total_questions' => $questions->count(),
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

    public function answer(PracticeSession $session, int $position, string $option): void
    {
        DB::transaction(function () use ($session, $position, $option) {
            $locked = PracticeSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
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
}
