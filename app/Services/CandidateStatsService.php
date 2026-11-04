<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Candidates correct %", average time and the subject average, computed from
 * the answers of every student.
 *
 * The refresh is incremental and idempotent: it finds the questions answered
 * since the last run (indexed by practice_items.answered_at) and recomputes
 * the full totals of only those questions, so a lost watermark or a repeated
 * run can never double count. The practice screen then reads one row per
 * question and per subject.
 */
class CandidateStatsService
{
    public const WATERMARK = 'candidate-stats.watermark';

    /** Minutes each run re-reads before the previous run started. */
    private const OVERLAP_MINUTES = 5;

    /** Questions recomputed per grouped query. */
    private const CHUNK = 500;

    /** Answers slower than this are treated as "left open" and not timed. */
    public const MAX_TIMED_SECONDS = 1800;

    /** Below this many answers the percentages are not shown to students. */
    public const MIN_ATTEMPTS = 5;

    /** @return int Number of questions refreshed. */
    public function refresh(bool $full = false): int
    {
        $startedAt = now();
        /** @var string|null $since */
        $since = $full ? null : Cache::get(self::WATERMARK);
        $refreshed = 0;
        $categories = [];

        $this->affectedQuestions($since)->chunk(self::CHUNK)->each(function (Collection $ids) use (&$refreshed, &$categories) {
            $refreshed += $this->refreshQuestions($ids->all());
            foreach (DB::table('questions')->whereIn('id', $ids->all())->distinct()->pluck('category_id') as $categoryId) {
                $categories[(int) $categoryId] = true;
            }
        });
        foreach (array_chunk(array_keys($categories), self::CHUNK) as $chunk) {
            $this->refreshCategories($chunk);
        }
        // The next run looks back to a little before this one started, so an answer committed while
        // this run was reading is never missed. Recomputing a question twice is harmless.
        Cache::forever(self::WATERMARK, $startedAt->copy()->subMinutes(self::OVERLAP_MINUTES)->toDateTimeString());

        return $refreshed;
    }

    /**
     * Candidate figures for the practice screen, or null when too few students answered.
     *
     * @return array{attempts: int, correct_percent: int, average_seconds: int|null, options: array<string, int>, topic_percent: int|null}|null
     */
    public function forQuestion(int $questionId): ?array
    {
        $row = DB::table('question_stats as qs')
            ->join('questions as q', 'q.id', '=', 'qs.question_id')
            ->leftJoin('category_stats as cs', 'cs.category_id', '=', 'q.category_id')
            ->where('qs.question_id', $questionId)
            ->first(['qs.attempts', 'qs.correct', 'qs.timed_attempts', 'qs.total_seconds', 'qs.option_counts', 'cs.attempts as topic_attempts', 'cs.correct as topic_correct']);
        if ($row === null || (int) $row->attempts < self::MIN_ATTEMPTS) {
            return null;
        }
        $attempts = (int) $row->attempts;
        $options = [];
        foreach ((array) json_decode((string) ($row->option_counts ?? '{}'), true) as $option => $count) {
            $options[(string) $option] = (int) round((int) $count / $attempts * 100);
        }
        ksort($options);
        $topicAttempts = (int) ($row->topic_attempts ?? 0);

        return [
            'attempts' => $attempts,
            'correct_percent' => (int) round((int) $row->correct / $attempts * 100),
            'average_seconds' => (int) $row->timed_attempts > 0 ? (int) round((int) $row->total_seconds / (int) $row->timed_attempts) : null,
            'options' => $options,
            'topic_percent' => $topicAttempts >= self::MIN_ATTEMPTS ? (int) round((int) ($row->topic_correct ?? 0) / $topicAttempts * 100) : null,
        ];
    }

    /** @return Collection<int, int> */
    private function affectedQuestions(?string $since): Collection
    {
        if ($since === null) {
            return DB::table('questions')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id);
        }

        return DB::table('practice_items')
            ->where('answered_at', '>=', $since)
            ->whereNotNull('question_id')
            ->distinct()
            ->orderBy('question_id')
            ->pluck('question_id')
            ->map(fn ($id): int => (int) $id);
    }

    /** @param list<int> $ids */
    private function refreshQuestions(array $ids): int
    {
        $totals = DB::table('practice_items')
            ->whereIn('question_id', $ids)
            ->whereNotNull('answered_at')
            ->groupBy('question_id')
            ->selectRaw('question_id, COUNT(*) AS attempts, COALESCE(SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END), 0) AS correct')
            ->selectRaw('COALESCE(SUM(CASE WHEN time_seconds IS NOT NULL AND time_seconds <= ? THEN 1 ELSE 0 END), 0) AS timed_attempts', [self::MAX_TIMED_SECONDS])
            ->selectRaw('COALESCE(SUM(CASE WHEN time_seconds IS NOT NULL AND time_seconds <= ? THEN time_seconds ELSE 0 END), 0) AS total_seconds', [self::MAX_TIMED_SECONDS])
            ->get()->keyBy('question_id');
        $options = [];
        DB::table('practice_items')
            ->whereIn('question_id', $ids)
            ->whereNotNull('answered_at')
            ->groupBy('question_id', 'selected_option')
            ->selectRaw('question_id, selected_option, COUNT(*) AS total')
            ->get()
            ->each(function ($row) use (&$options) {
                $options[(int) $row->question_id][(string) $row->selected_option] = (int) $row->total;
            });
        $now = now();
        $rows = [];
        foreach ($totals as $questionId => $total) {
            $rows[] = [
                'question_id' => (int) $questionId,
                'attempts' => (int) $total->attempts,
                'correct' => (int) $total->correct,
                'timed_attempts' => (int) $total->timed_attempts,
                'total_seconds' => (int) $total->total_seconds,
                'option_counts' => json_encode($options[(int) $questionId] ?? [], JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ];
        }
        if ($rows !== []) {
            DB::table('question_stats')->upsert($rows, ['question_id'], ['attempts', 'correct', 'timed_attempts', 'total_seconds', 'option_counts', 'updated_at']);
        }

        return count($rows);
    }

    /** @param list<int> $categoryIds */
    private function refreshCategories(array $categoryIds): void
    {
        $now = now();
        $rows = DB::table('question_stats as qs')
            ->join('questions as q', 'q.id', '=', 'qs.question_id')
            ->whereIn('q.category_id', $categoryIds)
            ->groupBy('q.category_id')
            ->selectRaw('q.category_id, COALESCE(SUM(qs.attempts), 0) AS attempts, COALESCE(SUM(qs.correct), 0) AS correct')
            ->get()
            ->map(fn ($row): array => ['category_id' => (int) $row->category_id, 'attempts' => (int) $row->attempts, 'correct' => (int) $row->correct, 'updated_at' => $now])
            ->all();
        if ($rows !== []) {
            DB::table('category_stats')->upsert($rows, ['category_id'], ['attempts', 'correct', 'updated_at']);
        }
    }

    /** When the figures were last refreshed, for the admin dashboard. */
    public function lastRefreshed(): ?CarbonInterface
    {
        $value = Cache::get(self::WATERMARK);

        return is_string($value) ? now()->parse($value) : null;
    }
}
