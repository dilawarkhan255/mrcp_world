<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Carbon;

/**
 * A 7-day study plan built from the student's own answers. No AI service is
 * involved: the weakest subjects (lowest accuracy, with enough answers) and
 * the subjects not practised yet are scheduled over the week.
 *
 * Each day has a few subjects: two slots rotate through the weak subjects and
 * one through the unpractised ones; when one group is empty, the other fills
 * the day. A subject never gets more questions than its main bank holds.
 */
class StudyPlanService
{
    public function __construct(private PerformanceService $performance) {}

    /**
     * @return array{
     *     weak: list<array{id: int, name: string, answered: int, correct: int, accuracy: int, available: int, reason: string}>,
     *     unpractised: list<array{id: int, name: string, available: int, reason: string}>,
     *     days: list<array{day: int, date: string, total: int, items: list<array{category_id: int, name: string, count: int, kind: string}>}>,
     *     has_data: bool,
     *     min_answers: int
     * }
     */
    public function build(int $userId, int $examId): array
    {
        $min = (int) config('student.study_plan.min_answers', 10);
        $breakdown = collect($this->performance->categoryBreakdown($userId, $examId))->keyBy('id');
        // Main bank questions per subject: the plan starts main bank sessions.
        $available = Category::where('exam_id', $examId)
            ->withCount(['questions' => fn ($query) => $query->published()->whereNull('past_paper_id')])
            ->orderBy('name')
            ->get(['id', 'name']);
        $counts = $available->pluck('questions_count', 'id');

        $weak = $breakdown->filter(fn (array $row): bool => $row['answered'] >= $min && (int) ($counts[$row['id']] ?? 0) > 0)
            ->sortBy([['accuracy', 'asc'], ['answered', 'desc'], ['name', 'asc']])
            ->take((int) config('student.study_plan.weak_subjects', 3))
            ->map(fn (array $row): array => [
                ...$row,
                'available' => (int) $counts[$row['id']],
                'reason' => $row['accuracy'].'% correct across '.$row['answered'].' answers',
            ])->values()->all();

        $unpractised = $available->filter(fn (Category $category): bool => $category->questions_count > 0 && ! $breakdown->has($category->id))
            ->sortByDesc('questions_count')
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'available' => (int) $category->questions_count,
                'reason' => 'Not practised yet · '.$category->questions_count.' questions available',
            ])->values()->all();

        return [
            'weak' => $weak,
            'unpractised' => $unpractised,
            'days' => $this->schedule($weak, $unpractised),
            'has_data' => $breakdown->isNotEmpty(),
            'min_answers' => $min,
        ];
    }

    /**
     * @param  list<array{id: int, name: string, available: int}>  $weak
     * @param  list<array{id: int, name: string, available: int}>  $unpractised
     * @return list<array{day: int, date: string, total: int, items: list<array{category_id: int, name: string, count: int, kind: string}>}>
     */
    private function schedule(array $weak, array $unpractised): array
    {
        $slots = max(1, (int) config('student.study_plan.subjects_per_day', 3));
        $perSubject = max(1, intdiv((int) config('student.study_plan.daily_questions', 30), $slots));
        $days = [];
        $weakTurn = 0;
        $newTurn = 0;
        for ($day = 0; $day < (int) config('student.study_plan.days', 7); $day++) {
            /** @var array<int, array{0: array{id: int, name: string, available: int}, 1: string}> $picked */
            $picked = [];
            // Take the next subjects of a group in turn, skipping any already on this day.
            $take = function (array $pool, int &$turn, string $kind, int $wanted) use (&$picked): void {
                for ($tries = 0; $wanted > 0 && $tries < count($pool); $tries++) {
                    $subject = $pool[$turn++ % count($pool)];
                    if (! isset($picked[$subject['id']])) {
                        $picked[$subject['id']] = [$subject, $kind];
                        $wanted--;
                    }
                }
            };
            $take($weak, $weakTurn, 'weak', $unpractised === [] ? $slots : $slots - 1);
            $take($unpractised, $newTurn, 'new', $slots - count($picked));
            $take($weak, $weakTurn, 'weak', $slots - count($picked));
            $items = [];
            foreach ($picked as [$subject, $kind]) {
                $items[] = ['category_id' => $subject['id'], 'name' => $subject['name'], 'count' => min($perSubject, $subject['available']), 'kind' => $kind];
            }
            $days[] = [
                'day' => $day + 1,
                'date' => Carbon::today()->addDays($day)->toDateString(),
                'total' => array_sum(array_column($items, 'count')),
                'items' => $items,
            ];
        }

        return $days;
    }
}
