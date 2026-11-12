<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Flashcard;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Flashcards with spaced repetition (a simple SM-2). Each answer moves the
 * card's next review: "Again" brings it back in ten minutes, "Good" spaces
 * it out more each time, "Easy" even more. Cards never seen are "new".
 */
class FlashcardService
{
    /** @var list<string> */
    public const RATINGS = ['again', 'hard', 'good', 'easy'];

    /**
     * The decks of an exam: one per subject, with the student's counts.
     *
     * @return list<array{category_id: int|null, subject: string, slug: string, total: int, new: int, due: int}>
     */
    public function decks(User $user, Exam $exam): array
    {
        $now = now();

        return DB::table('flashcards')
            ->leftJoin('categories', 'categories.id', '=', 'flashcards.category_id')
            ->leftJoin('flashcard_reviews as r', fn ($join) => $join->on('r.flashcard_id', '=', 'flashcards.id')->where('r.user_id', '=', $user->id))
            ->where('flashcards.exam_id', $exam->id)->where('flashcards.is_published', true)
            ->groupBy('flashcards.category_id', 'categories.name', 'categories.slug')
            ->orderByRaw('CASE WHEN categories.name IS NULL THEN 1 ELSE 0 END')->orderBy('categories.name')
            ->selectRaw('flashcards.category_id, categories.name AS subject, categories.slug AS slug, COUNT(*) AS total,
                SUM(CASE WHEN r.flashcard_id IS NULL THEN 1 ELSE 0 END) AS new_count,
                SUM(CASE WHEN r.due_at <= ? THEN 1 ELSE 0 END) AS due_count', [$now])
            ->get()
            ->map(fn (object $row): array => [
                'category_id' => $row->category_id === null ? null : (int) $row->category_id,
                'subject' => (string) ($row->subject ?? 'General'),
                // "general" is the deck of the cards without a subject (FlashcardController::study).
                'slug' => (string) ($row->slug ?? 'general'),
                'total' => (int) $row->total,
                'new' => (int) $row->new_count,
                'due' => (int) $row->due_count,
            ])->all();
    }

    /**
     * The next card to study: due cards first (oldest first), then new ones.
     * $categoryId: one subject; 0 for cards without a subject; null for all.
     */
    public function next(User $user, Exam $exam, ?int $categoryId): ?Flashcard
    {
        $scope = fn (Builder $query) => $query->where('flashcards.is_published', true)->where('flashcards.exam_id', $exam->id)
            ->when($categoryId === 0, fn (Builder $query) => $query->whereNull('flashcards.category_id'))
            ->when($categoryId !== null && $categoryId > 0, fn (Builder $query) => $query->where('flashcards.category_id', $categoryId));

        $due = Flashcard::query()->tap($scope)
            ->join('flashcard_reviews as r', fn ($join) => $join->on('r.flashcard_id', '=', 'flashcards.id')->where('r.user_id', '=', $user->id))
            ->where('r.due_at', '<=', now())->orderBy('r.due_at')->orderBy('flashcards.id')
            ->first(['flashcards.*']);

        return $due ?? Flashcard::query()->tap($scope)
            ->whereNotExists(fn ($query) => $query->from('flashcard_reviews')->whereColumn('flashcard_reviews.flashcard_id', 'flashcards.id')->where('flashcard_reviews.user_id', $user->id))
            ->orderBy('flashcards.sort_order')->orderBy('flashcards.id')
            ->first();
    }

    /** Records the student's answer and schedules the next review. */
    public function review(User $user, Flashcard $card, string $rating): void
    {
        DB::transaction(function () use ($user, $card, $rating): void {
            $current = DB::table('flashcard_reviews')->where('user_id', $user->id)->where('flashcard_id', $card->id)->lockForUpdate()->first();
            $next = self::schedule($current === null ? null : (array) $current, $rating, now());
            DB::table('flashcard_reviews')->updateOrInsert(
                ['user_id' => $user->id, 'flashcard_id' => $card->id],
                [...$next, 'answers' => (int) ($current->answers ?? 0) + 1, 'last_rating' => $rating, 'reviewed_at' => now()],
            );
        });
    }

    /**
     * SM-2, simplified. Pure, so the buttons can show what each rating does.
     *
     * @param  array<string, mixed>|null  $state  ease, interval_days, repetitions, lapses (null for a new card)
     * @return array{ease: float, interval_days: int, repetitions: int, lapses: int, due_at: Carbon}
     */
    public static function schedule(?array $state, string $rating, Carbon $now): array
    {
        $ease = (float) ($state['ease'] ?? 2.5);
        $interval = (int) ($state['interval_days'] ?? 0);
        $repetitions = (int) ($state['repetitions'] ?? 0);
        $lapses = (int) ($state['lapses'] ?? 0);

        if ($rating === 'again') {
            return ['ease' => max(1.3, $ease - 0.2), 'interval_days' => 0, 'repetitions' => 0, 'lapses' => $lapses + 1, 'due_at' => $now->copy()->addMinutes(10)];
        }
        if ($rating === 'hard') {
            $days = max(1, (int) round(max($interval, 1) * 1.2));
            $ease = max(1.3, $ease - 0.15);
        } else {
            $days = match (true) {
                $repetitions === 0 => 1,
                $repetitions === 1 => 3,
                default => max(1, (int) round($interval * $ease)),
            };
            if ($rating === 'easy') {
                $days = max($days + 1, (int) round($days * 1.3));
                $ease += 0.15;
            }
        }

        return ['ease' => round($ease, 2), 'interval_days' => $days, 'repetitions' => $repetitions + 1, 'lapses' => $lapses, 'due_at' => $now->copy()->addDays($days)];
    }

    /**
     * What each rating would do to this card, for the buttons ("10 min", "3 days").
     *
     * @return array<string, string>
     */
    public function previews(User $user, Flashcard $card): array
    {
        $state = DB::table('flashcard_reviews')->where('user_id', $user->id)->where('flashcard_id', $card->id)->first();
        $labels = [];
        foreach (self::RATINGS as $rating) {
            $next = self::schedule($state === null ? null : (array) $state, $rating, now());
            $labels[$rating] = $next['interval_days'] === 0 ? '10 min' : $next['interval_days'].' '.Str::plural('day', $next['interval_days']);
        }

        return $labels;
    }

    /**
     * Makes (or finds) the flashcard of an MCQ: the stem on the front, the
     * correct answer and the key points (or the start of the explanation)
     * on the back. Only the MCQ's own content is used.
     */
    public function fromQuestion(Question $question): Flashcard
    {
        $answer = $question->options[$question->correct_option] ?? '';
        $more = trim((string) ($question->key_points ?: Str::limit(trim(strtok((string) $question->explanation, "\n") ?: ''), 1200)));

        return Flashcard::firstOrCreate(['question_id' => $question->id], [
            'exam_id' => $question->exam_id,
            'category_id' => $question->category_id,
            'front' => $question->body,
            'back' => '**Answer: '.$question->correct_option.'. '.$answer.'**'.($more !== '' ? "\n\n".$more : ''),
            // A topic examiners repeat is more high-yield.
            'high_yield' => match (true) {
                $question->repeat_count >= 3 => 'A++',
                $question->repeat_count === 2 || $question->past_paper_id !== null => 'A+',
                default => 'A',
            },
            'is_published' => $question->is_published,
        ]);
    }
}
