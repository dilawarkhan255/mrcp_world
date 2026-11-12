<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Exam;
use App\Models\Flashcard;
use App\Models\TutorRun;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * The Knowledge Tutor: short exam facts (the flashcards) asked one at a time
 * and scheduled with spaced repetition (FlashcardService::schedule).
 *
 * A student chooses subjects, a minimum high-yield rating, a difficulty range
 * and how facts are picked. Among the facts that are ready, the most
 * high-yield come first. A fact's status for a student:
 *  - new: never answered;
 *  - weak: forgotten at least once and not yet recalled twice in a row;
 *  - learning: answered, not yet recalled twice in a row;
 *  - recalled: recalled at least twice in a row ("acceptable recall");
 *  - mastered: recalled and spaced out to three weeks or more.
 *
 * @phpstan-type Filters array{categories: list<int>, rating: string, difficulty: array{0: int, 1: int}, mode: string}
 */
class KnowledgeTutorService
{
    /** How facts are picked. */
    public const MODES = [
        'spaced' => 'Spaced repetition: facts due for review first, then new facts',
        'new' => 'New facts only',
        'due' => 'Facts due for review only',
        'weak' => 'My weak facts (ones I forgot)',
    ];

    /** Minimum high-yield rating. */
    public const RATINGS = [
        'all' => 'All facts',
        'A++' => 'A++ only',
        'A+' => 'A+ and above',
        'A' => 'A and above',
        'B' => 'B and above',
    ];

    /** The ratings the progress at the top counts ("A++ to A facts"). */
    public const TOP_RATINGS = ['A++', 'A+', 'A'];

    /** Recalled this many times in a row = acceptable recall. */
    public const RECALLED_AFTER = 2;

    /** Spaced out to this many days = mastered. */
    public const MASTERED_DAYS = 21;

    /** A fact without enough answers yet counts as medium difficulty. */
    public const UNKNOWN_DIFFICULTY = 50;

    /** Answers (all students) before a fact's difficulty is worked out. */
    public const DIFFICULTY_MIN_ANSWERS = 5;

    /**
     * Clean filters from the browser, with defaults.
     *
     * @param  array<string, mixed>  $input
     * @return Filters
     */
    public function filters(array $input, Exam $exam): array
    {
        $valid = Category::where('exam_id', $exam->id)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $categories = collect((array) ($input['categories'] ?? []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id === 0 || in_array($id, $valid, true))
            ->unique()->sort()->values()->all();
        $difficulty = array_values((array) ($input['difficulty'] ?? [0, 100]));
        $low = max(0, min(100, (int) ($difficulty[0] ?? 0)));
        $high = max($low, min(100, (int) ($difficulty[1] ?? 100)));
        $rating = (string) ($input['rating'] ?? 'all');
        $mode = (string) ($input['mode'] ?? 'spaced');

        return [
            'categories' => $categories,
            'rating' => array_key_exists($rating, self::RATINGS) ? $rating : 'all',
            'difficulty' => [$low, $high],
            'mode' => array_key_exists($mode, self::MODES) ? $mode : 'spaced',
        ];
    }

    /**
     * A short name for a run, shown under "Last activity".
     *
     * @param  Filters  $filters
     */
    public function label(array $filters, Exam $exam): string
    {
        $names = Category::where('exam_id', $exam->id)->whereIn('id', $filters['categories'])->orderBy('name')->pluck('name')->all();
        if (in_array(0, $filters['categories'], true)) {
            $names[] = 'General';
        }
        $subjects = match (true) {
            $names === [] => 'All subjects',
            count($names) <= 2 => implode(', ', $names),
            default => $names[0].' + '.(count($names) - 1).' more',
        };
        $parts = [$subjects, $filters['rating'] === 'all' ? 'all ratings' : self::RATINGS[$filters['rating']]];
        if ($filters['difficulty'] !== [0, 100]) {
            $parts[] = 'difficulty '.$filters['difficulty'][0].'–'.$filters['difficulty'][1].'%';
        }
        if ($filters['mode'] !== 'spaced') {
            $parts[] = strtolower(strtok(self::MODES[$filters['mode']], ':') ?: '');
        }

        return mb_strimwidth(implode(' · ', $parts), 0, 250, '…');
    }

    /**
     * The published facts that match the subject, rating and difficulty filters,
     * joined (left) to this student's review row as "r".
     *
     * @param  Filters  $filters
     * @return Builder<Flashcard>
     */
    private function matching(User $user, Exam $exam, array $filters): Builder
    {
        $categories = $filters['categories'];
        $named = array_values(array_filter($categories, fn (int $id): bool => $id > 0));
        $general = in_array(0, $categories, true);
        [$low, $high] = $filters['difficulty'];

        return Flashcard::query()
            ->leftJoin('flashcard_reviews as r', fn (JoinClause $join) => $join->on('r.flashcard_id', '=', 'flashcards.id')->where('r.user_id', '=', $user->id))
            ->where('flashcards.exam_id', $exam->id)->where('flashcards.is_published', true)
            ->when($categories !== [], fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->when($named !== [], fn (Builder $query) => $query->orWhereIn('flashcards.category_id', $named))
                ->when($general, fn (Builder $query) => $query->orWhereNull('flashcards.category_id'))))
            ->when($filters['rating'] !== 'all', fn (Builder $query) => $query->whereIn('flashcards.high_yield', $this->ratingsFrom($filters['rating'])))
            ->when([$low, $high] !== [0, 100], fn (Builder $query) => $query
                ->whereRaw('COALESCE(flashcards.difficulty, ?) BETWEEN ? AND ?', [self::UNKNOWN_DIFFICULTY, $low, $high]));
    }

    /** @return list<string> The rating and every rating above it. */
    private function ratingsFrom(string $minimum): array
    {
        $order = array_keys(Flashcard::HIGH_YIELD);
        $position = array_search($minimum, $order, true);

        return array_slice($order, 0, $position === false ? count($order) : $position + 1);
    }

    /**
     * Narrows matching facts to the ones the mode asks now.
     *
     * @param  Builder<Flashcard>  $query
     * @return Builder<Flashcard>
     */
    private function ready(Builder $query, string $mode, ?\DateTimeInterface $since): Builder
    {
        $now = now();

        return match ($mode) {
            'new' => $query->whereNull('r.flashcard_id'),
            'due' => $query->whereNotNull('r.flashcard_id')->where('r.due_at', '<=', $now),
            // Each weak fact once per run (otherwise the same one would come straight back).
            'weak' => $query->whereNotNull('r.flashcard_id')
                ->where(fn (Builder $query) => $query->where('r.lapses', '>', 0)->orWhere('r.ease', '<', 2.3))
                ->where('r.repetitions', '<', self::RECALLED_AFTER)
                ->when($since !== null, fn (Builder $query) => $query->where('r.reviewed_at', '<', $since)),
            default => $query->where(fn (Builder $query) => $query->whereNull('r.flashcard_id')->orWhere('r.due_at', '<=', $now)),
        };
    }

    /**
     * How many facts match, and how many of them are ready now with the chosen mode.
     *
     * @param  Filters  $filters
     * @return array{total: int, ready: int}
     */
    public function count(User $user, Exam $exam, array $filters): array
    {
        return [
            'total' => $this->matching($user, $exam, $filters)->count(),
            'ready' => $this->ready($this->matching($user, $exam, $filters), $filters['mode'], null)->count(),
        ];
    }

    /** The next fact of a run: due ones first (oldest first), then the most high-yield new ones. */
    public function next(User $user, TutorRun $run, Exam $exam): ?Flashcard
    {
        /** @var Filters $filters */
        $filters = $run->filters;

        return $this->ready($this->matching($user, $exam, $filters), $filters['mode'], $run->created_at)
            ->orderByRaw('CASE WHEN r.flashcard_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('r.due_at')
            ->orderByRaw("CASE flashcards.high_yield WHEN 'A++' THEN 0 WHEN 'A+' THEN 1 WHEN 'A' THEN 2 WHEN 'B' THEN 3 ELSE 4 END")
            ->orderBy('flashcards.sort_order')->orderBy('flashcards.id')
            ->first(['flashcards.*']);
    }

    /** Facts of a run still ready now (for the counter on the study screen). */
    public function remaining(User $user, TutorRun $run, Exam $exam): int
    {
        /** @var Filters $filters */
        $filters = $run->filters;

        return $this->ready($this->matching($user, $exam, $filters), $filters['mode'], $run->created_at)->count();
    }

    /**
     * The student's progress, per subject and in total, in one query.
     *
     * @return array{subjects: list<array{id: int, name: string, total: int, seen: int, recalled: int, mastered: int, weak: int, due: int, new: int}>, totals: array{total: int, seen: int, recalled: int, mastered: int, weak: int, due: int, new: int, top_total: int, top_seen: int, top_recalled: int, today: int}}
     */
    public function overview(User $user, Exam $exam): array
    {
        $now = now();
        $top = "'".implode("','", self::TOP_RATINGS)."'";
        $recalled = 'r.repetitions >= '.self::RECALLED_AFTER;
        $rows = DB::table('flashcards')
            ->leftJoin('categories', 'categories.id', '=', 'flashcards.category_id')
            ->leftJoin('flashcard_reviews as r', fn (JoinClause $join) => $join->on('r.flashcard_id', '=', 'flashcards.id')->where('r.user_id', '=', $user->id))
            ->where('flashcards.exam_id', $exam->id)->where('flashcards.is_published', true)
            ->groupBy('flashcards.category_id', 'categories.name')
            ->orderByRaw('CASE WHEN categories.name IS NULL THEN 1 ELSE 0 END')->orderBy('categories.name')
            ->selectRaw("flashcards.category_id, categories.name AS subject, COUNT(*) AS total,
                SUM(CASE WHEN r.flashcard_id IS NOT NULL THEN 1 ELSE 0 END) AS seen,
                SUM(CASE WHEN {$recalled} THEN 1 ELSE 0 END) AS recalled,
                SUM(CASE WHEN {$recalled} AND r.interval_days >= ? THEN 1 ELSE 0 END) AS mastered,
                SUM(CASE WHEN r.lapses > 0 AND r.repetitions < ? THEN 1 ELSE 0 END) AS weak,
                SUM(CASE WHEN r.due_at <= ? THEN 1 ELSE 0 END) AS due,
                SUM(CASE WHEN r.flashcard_id IS NULL THEN 1 ELSE 0 END) AS new_count,
                SUM(CASE WHEN flashcards.high_yield IN ({$top}) THEN 1 ELSE 0 END) AS top_total,
                SUM(CASE WHEN flashcards.high_yield IN ({$top}) AND r.flashcard_id IS NOT NULL THEN 1 ELSE 0 END) AS top_seen,
                SUM(CASE WHEN flashcards.high_yield IN ({$top}) AND {$recalled} THEN 1 ELSE 0 END) AS top_recalled,
                SUM(CASE WHEN r.reviewed_at >= ? THEN 1 ELSE 0 END) AS today", [self::MASTERED_DAYS, self::RECALLED_AFTER, $now, $now->copy()->startOfDay()])
            ->get();

        $subjects = $rows->map(fn (object $row): array => [
            'id' => (int) ($row->category_id ?? 0),
            'name' => (string) ($row->subject ?? 'General'),
            'total' => (int) $row->total,
            'seen' => (int) $row->seen,
            'recalled' => (int) $row->recalled,
            'mastered' => (int) $row->mastered,
            'weak' => (int) $row->weak,
            'due' => (int) $row->due,
            'new' => (int) $row->new_count,
        ])->values()->all();
        $sum = fn (string $column): int => (int) $rows->sum(fn (object $row): int => (int) $row->{$column});

        return [
            'subjects' => $subjects,
            'totals' => [
                'total' => $sum('total'), 'seen' => $sum('seen'), 'recalled' => $sum('recalled'), 'mastered' => $sum('mastered'),
                'weak' => $sum('weak'), 'due' => $sum('due'), 'new' => $sum('new_count'),
                'top_total' => $sum('top_total'), 'top_seen' => $sum('top_seen'), 'top_recalled' => $sum('top_recalled'), 'today' => $sum('today'),
            ],
        ];
    }

    /** A fact's status for the student, from their review row (null = never answered). */
    public static function status(?object $review): string
    {
        return match (true) {
            $review === null => 'new',
            (int) $review->repetitions >= self::RECALLED_AFTER && (int) $review->interval_days >= self::MASTERED_DAYS => 'mastered',
            (int) $review->repetitions >= self::RECALLED_AFTER => 'recalled',
            (int) $review->lapses > 0 => 'weak',
            default => 'learning',
        };
    }

    /**
     * The facts the student has answered, for "Review facts", newest answer first.
     *
     * @return LengthAwarePaginator<int, Flashcard>
     */
    public function answered(User $user, Exam $exam, ?string $status, ?int $categoryId, ?string $search): LengthAwarePaginator
    {
        $recalled = self::RECALLED_AFTER;

        return Flashcard::query()
            ->join('flashcard_reviews as r', fn (JoinClause $join) => $join->on('r.flashcard_id', '=', 'flashcards.id')->where('r.user_id', '=', $user->id))
            ->leftJoin('categories', 'categories.id', '=', 'flashcards.category_id')
            ->where('flashcards.exam_id', $exam->id)->where('flashcards.is_published', true)
            ->when($categoryId === 0, fn (Builder $query) => $query->whereNull('flashcards.category_id'))
            ->when($categoryId !== null && $categoryId > 0, fn (Builder $query) => $query->where('flashcards.category_id', $categoryId))
            ->when($search !== null && $search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('flashcards.front', 'like', '%'.$search.'%')->orWhere('flashcards.back', 'like', '%'.$search.'%')))
            ->when($status === 'weak', fn (Builder $query) => $query->where('r.lapses', '>', 0)->where('r.repetitions', '<', $recalled))
            ->when($status === 'learning', fn (Builder $query) => $query->where('r.lapses', 0)->where('r.repetitions', '<', $recalled))
            ->when($status === 'recalled', fn (Builder $query) => $query->where('r.repetitions', '>=', $recalled)->where('r.interval_days', '<', self::MASTERED_DAYS))
            ->when($status === 'mastered', fn (Builder $query) => $query->where('r.repetitions', '>=', $recalled)->where('r.interval_days', '>=', self::MASTERED_DAYS))
            ->when($status === 'due', fn (Builder $query) => $query->where('r.due_at', '<=', now()))
            ->orderByDesc('r.reviewed_at')->orderByDesc('flashcards.id')
            ->select(['flashcards.*', 'categories.name as subject_name', 'r.repetitions', 'r.interval_days', 'r.lapses', 'r.answers', 'r.due_at', 'r.reviewed_at', 'r.last_rating'])
            ->paginate(15)->withQueryString();
    }

    /** Forget a fact: it becomes new again for this student. */
    public function reset(User $user, Flashcard $card): void
    {
        DB::table('flashcard_reviews')->where('user_id', $user->id)->where('flashcard_id', $card->id)->delete();
    }

    /**
     * Each fact's difficulty: the share of all students' answers that were
     * "Again" (forgotten), once it has enough answers. Run by stats:refresh.
     */
    public function refreshDifficulty(): int
    {
        $updated = 0;
        DB::table('flashcard_reviews')
            ->groupBy('flashcard_id')
            ->havingRaw('SUM(answers) >= ?', [self::DIFFICULTY_MIN_ANSWERS])
            ->selectRaw('flashcard_id, SUM(answers) AS answers, SUM(lapses) AS lapses')
            ->orderBy('flashcard_id')
            ->chunk(500, function ($rows) use (&$updated): void {
                foreach ($rows as $row) {
                    $difficulty = (int) min(100, round(100 * (int) $row->lapses / max(1, (int) $row->answers)));
                    // The table directly: a difficulty change is not an edit (updated_at stays).
                    $updated += DB::table('flashcards')->where('id', $row->flashcard_id)->where(fn ($query) => $query
                        ->whereNull('difficulty')->orWhere('difficulty', '!=', $difficulty))->update(['difficulty' => $difficulty]);
                }
            });

        return $updated;
    }
}
