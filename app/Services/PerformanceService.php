<?php

namespace App\Services;

use App\Models\PracticeSession;
use Illuminate\Support\Facades\DB;

class PerformanceService
{
    public const TREND_DAYS = 14;

    /**
     * Overall totals for the selected exam.
     *
     * @return array{answered: int, correct: int, accuracy: int, sessions: int, completed: int, mocks: int}
     */
    public function summary(int $userId, int $examId): array
    {
        $totals = DB::table('practice_items as pi')
            ->join('practice_sessions as ps', 'ps.id', '=', 'pi.practice_session_id')
            ->where('ps.user_id', $userId)
            ->where('ps.exam_id', $examId)
            ->whereNotNull('pi.answered_at')
            ->selectRaw('COUNT(*) as answered, COALESCE(SUM(CASE WHEN pi.is_correct = 1 THEN 1 ELSE 0 END), 0) as correct')
            ->first();

        $sessions = PracticeSession::where('user_id', $userId)
            ->where('exam_id', $examId)
            ->selectRaw('COUNT(*) as sessions, COALESCE(SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END), 0) as completed')
            ->selectRaw('COALESCE(SUM(CASE WHEN mode = ? THEN 1 ELSE 0 END), 0) as mocks', [PracticeSession::MODE_MOCK])
            ->first();

        $answered = (int) ($totals->answered ?? 0);
        $correct = (int) ($totals->correct ?? 0);

        return [
            'answered' => $answered,
            'correct' => $correct,
            'accuracy' => $answered > 0 ? (int) round($correct / $answered * 100) : 0,
            'sessions' => (int) ($sessions->sessions ?? 0),
            'completed' => (int) ($sessions->completed ?? 0),
            'mocks' => (int) ($sessions->mocks ?? 0),
        ];
    }

    /**
     * Answered and correct answers per day for the last few days.
     *
     * @return list<array{day: string, answered: int, correct: int}>
     */
    public function dailyTrend(int $userId, int $examId, int $days = self::TREND_DAYS): array
    {
        $rows = DB::table('practice_items as pi')
            ->join('practice_sessions as ps', 'ps.id', '=', 'pi.practice_session_id')
            ->where('ps.user_id', $userId)
            ->where('ps.exam_id', $examId)
            ->whereNotNull('pi.answered_at')
            ->where('pi.answered_at', '>=', today()->subDays($days - 1)->startOfDay())
            ->groupByRaw('DATE(pi.answered_at)')
            ->selectRaw('DATE(pi.answered_at) as day, COUNT(*) as answered, COALESCE(SUM(CASE WHEN pi.is_correct = 1 THEN 1 ELSE 0 END), 0) as correct')
            ->get();

        $series = [];
        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $day = today()->subDays($offset)->toDateString();
            $series[$day] = ['day' => $day, 'answered' => 0, 'correct' => 0];
        }
        foreach ($rows as $row) {
            $day = (string) $row->day;
            if (isset($series[$day])) {
                $series[$day]['answered'] = (int) $row->answered;
                $series[$day]['correct'] = (int) $row->correct;
            }
        }

        return array_values($series);
    }

    /**
     * Accuracy per category.
     *
     * @return list<array{id: int, name: string, answered: int, correct: int, accuracy: int}>
     */
    public function categoryBreakdown(int $userId, int $examId): array
    {
        return DB::table('practice_items as pi')
            ->join('practice_sessions as ps', 'ps.id', '=', 'pi.practice_session_id')
            ->join('questions as q', 'q.id', '=', 'pi.question_id')
            ->join('categories as c', 'c.id', '=', 'q.category_id')
            ->where('ps.user_id', $userId)
            ->where('ps.exam_id', $examId)
            ->whereNotNull('pi.answered_at')
            ->groupBy('c.id')
            ->select('c.id', 'c.name')
            ->selectRaw('COUNT(*) as answered, COALESCE(SUM(CASE WHEN pi.is_correct = 1 THEN 1 ELSE 0 END), 0) as correct')
            ->orderByDesc('answered')
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'answered' => (int) $row->answered,
                'correct' => (int) $row->correct,
                'accuracy' => (int) $row->answered > 0 ? (int) round((int) $row->correct / (int) $row->answered * 100) : 0,
            ])
            ->all();
    }

    /**
     * The questions answered wrongly most often.
     *
     * @return list<array{id: int, body: string, wrong_count: int}>
     */
    public function weakestQuestions(int $userId, int $examId, int $limit = 5): array
    {
        return DB::table('practice_items as pi')
            ->join('practice_sessions as ps', 'ps.id', '=', 'pi.practice_session_id')
            ->join('questions as q', 'q.id', '=', 'pi.question_id')
            ->where('ps.user_id', $userId)
            ->where('ps.exam_id', $examId)
            ->where('pi.is_correct', false)
            ->groupBy('q.id')
            ->select('q.id', 'q.body')
            ->selectRaw('COUNT(*) as wrong_count')
            ->orderByDesc('wrong_count')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => ['id' => (int) $row->id, 'body' => (string) $row->body, 'wrong_count' => (int) $row->wrong_count])
            ->all();
    }

    /** Consecutive days with at least one saved answer, counting back from today. */
    public function streak(int $userId, int $examId): int
    {
        $days = DB::table('practice_items as pi')
            ->join('practice_sessions as ps', 'ps.id', '=', 'pi.practice_session_id')
            ->where('ps.user_id', $userId)
            ->where('ps.exam_id', $examId)
            ->whereNotNull('pi.answered_at')
            ->distinct()
            ->selectRaw('DATE(pi.answered_at) as day')
            ->orderByDesc('day')
            ->pluck('day')
            ->map(fn ($day): string => (string) $day)
            ->all();

        $streak = 0;
        foreach ($days as $day) {
            $expected = today()->subDays($streak)->toDateString();
            if ($day === $expected) {
                $streak++;

                continue;
            }
            if ($streak === 0 && $day === today()->subDay()->toDateString()) {
                $streak++;

                continue;
            }
            break;
        }

        return $streak;
    }
}
