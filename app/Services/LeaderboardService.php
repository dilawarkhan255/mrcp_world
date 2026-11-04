<?php

namespace App\Services;

use App\Models\PracticeSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Leaderboard of one exam, for students who opted in.
 *
 * Only a student's first answer to each question counts, so repeating a
 * question cannot raise a rank. Practice answers and answers of closed mocks
 * count; review sessions do not, and an open mock does not, because a rank
 * that moves after an answer would reveal whether it was correct.
 *
 * Ranking: correct first answers, then accuracy, then display name. A
 * student needs a minimum number of first answers in the period. The ranked
 * list is cached for a few minutes per exam and period.
 */
class LeaderboardService
{
    public const WEEKLY = 'weekly';

    public const ALL_TIME = 'all';

    /** Monday 00:00 of the current week in the leaderboard timezone, as UTC. */
    public function weekStart(): CarbonImmutable
    {
        return CarbonImmutable::now((string) config('student.leaderboard.timezone', 'Europe/London'))
            ->startOfWeek(CarbonImmutable::MONDAY)
            ->utc();
    }

    /**
     * @return array{entries: list<array{rank: int, user_id: int, name: string, correct: int, answered: int, accuracy: int}>, me: array{rank: int, user_id: int, name: string, correct: int, answered: int, accuracy: int}|null, qualified: int}
     */
    public function board(int $examId, string $period, ?int $userId): array
    {
        $since = $period === self::WEEKLY ? $this->weekStart() : null;
        $key = 'leaderboard:'.$examId.':'.$period.($since === null ? '' : ':'.$since->format('YmdHi'));
        /** @var list<array{rank: int, user_id: int, name: string, correct: int, answered: int, accuracy: int}> $ranked */
        $ranked = Cache::remember($key, now()->addMinutes((int) config('student.leaderboard.cache_minutes', 10)), fn (): array => $this->rank($examId, $since));
        $me = null;
        if ($userId !== null) {
            foreach ($ranked as $entry) {
                if ($entry['user_id'] === $userId) {
                    $me = $entry;
                    break;
                }
            }
        }

        return [
            'entries' => array_slice($ranked, 0, (int) config('student.leaderboard.size', 50)),
            'me' => $me,
            'qualified' => count($ranked),
        ];
    }

    /** @return list<array{rank: int, user_id: int, name: string, correct: int, answered: int, accuracy: int}> */
    private function rank(int $examId, ?CarbonImmutable $since): array
    {
        // Every counted answer of opted-in students, numbered per student and question in answer order.
        $answers = DB::table('practice_items as pi')
            ->join('practice_sessions as ps', 'ps.id', '=', 'pi.practice_session_id')
            ->join('users as u', 'u.id', '=', 'ps.user_id')
            ->where('u.leaderboard_opt_in', true)
            ->whereNotNull('u.leaderboard_name')
            ->where('ps.exam_id', $examId)
            ->whereNotNull('pi.answered_at')
            ->whereNotNull('pi.question_id')
            ->where(fn ($query) => $query->where('ps.mode', PracticeSession::MODE_PRACTICE)
                ->orWhere(fn ($query) => $query->where('ps.mode', PracticeSession::MODE_MOCK)->whereNotNull('ps.completed_at')))
            ->selectRaw('ps.user_id, pi.is_correct, pi.answered_at, ROW_NUMBER() OVER (PARTITION BY ps.user_id, pi.question_id ORDER BY pi.answered_at, pi.id) AS answer_number');

        $rows = DB::query()->fromSub($answers, 'a')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.answer_number', 1)
            ->when($since !== null, fn ($query) => $query->where('a.answered_at', '>=', $since))
            ->groupBy('a.user_id', 'u.leaderboard_name')
            ->havingRaw('COUNT(*) >= ?', [(int) config('student.leaderboard.min_answers', 20)])
            ->selectRaw('a.user_id, u.leaderboard_name AS name, COUNT(*) AS answered, COALESCE(SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END), 0) AS correct')
            ->get();

        $entries = $rows->map(fn ($row): array => [
            'user_id' => (int) $row->user_id,
            'name' => (string) $row->name,
            'correct' => (int) $row->correct,
            'answered' => (int) $row->answered,
            'ratio' => (int) $row->correct / max(1, (int) $row->answered),
        ])->sort(function (array $a, array $b): int {
            return [$b['correct'], $b['ratio'], mb_strtolower($a['name']), $a['user_id']] <=> [$a['correct'], $a['ratio'], mb_strtolower($b['name']), $b['user_id']];
        })->values();

        // Equal correct answers and equal accuracy share a rank (1, 2, 2, 4).
        $ranked = [];
        $previous = null;
        foreach ($entries as $index => $entry) {
            $tie = $previous !== null && $previous['correct'] === $entry['correct'] && abs($previous['ratio'] - $entry['ratio']) < 1e-9;
            $rank = $tie ? $ranked[$index - 1]['rank'] : $index + 1;
            $ranked[] = [
                'rank' => $rank, 'user_id' => $entry['user_id'], 'name' => $entry['name'], 'correct' => $entry['correct'],
                'answered' => $entry['answered'], 'accuracy' => (int) round($entry['ratio'] * 100),
            ];
            $previous = $entry;
        }

        return $ranked;
    }
}
