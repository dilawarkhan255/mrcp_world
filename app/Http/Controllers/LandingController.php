<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Plan;
use App\Models\ScoreDistribution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    /** Number of buckets in the public score histogram. */
    private const BUCKETS = 20;

    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Welcome', [
            'exams' => $this->exams(),
            'plans' => $this->plans(),
            'histogram' => $this->histogram(),
            'appName' => (string) config('app.name'),
        ]);
    }

    /**
     * Active exams in display order with their published question count.
     *
     * @return list<array{id: int, name: string, slug: string, description: string|null, code: string, question_count: int}>
     */
    private function exams(): array
    {
        return Exam::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->withCount(['questions' => fn ($query) => $query->published()])
            ->get(['id', 'name', 'slug', 'description', 'code'])
            ->map(fn (Exam $exam): array => [
                'id' => $exam->id,
                'name' => $exam->name,
                'slug' => $exam->slug,
                'description' => $exam->description,
                'code' => $exam->tileCode(),
                'question_count' => $exam->questions_count,
            ])
            ->all();
    }

    /**
     * Active plans grouped by their duration in days.
     *
     * @return list<array{duration_days: int, plans: list<array{id: int, name: string, duration_days: int, price: string, amount: string, currency: string}>}>
     */
    private function plans(): array
    {
        return Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('duration_days')
            ->get()
            ->map(fn (Plan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'duration_days' => $plan->duration_days,
                'price' => $plan->priceLabel(),
                'amount' => $plan->amountLabel(),
                'currency' => $plan->currency,
            ])
            ->groupBy('duration_days')
            ->map(fn ($group, $days): array => [
                'duration_days' => (int) $days,
                'plans' => $group->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Twenty score buckets. The recorded candidates are used once the number of
     * candidates reaches the configured minimum, otherwise a clearly labelled
     * sample distribution is shown.
     *
     * @return array{buckets: list<array{start: int, end: int, count: int}>, is_sample: bool, candidates: int}
     */
    private function histogram(): array
    {
        $recorded = array_fill(0, self::BUCKETS, 0);
        $candidates = 0;
        foreach (ScoreDistribution::query()->get(['bucket_start', 'candidates']) as $row) {
            $index = intdiv($row->bucket_start, 5);
            if ($index >= 0 && $index < self::BUCKETS) {
                $recorded[$index] += $row->candidates;
                $candidates += $row->candidates;
            }
        }

        $isSample = $candidates < (int) config('landing.minimum_candidates');
        if ($isSample) {
            $sample = array_values((array) config('landing.sample_distribution'));
            $recorded = array_map(fn (int $index): int => (int) ($sample[$index] ?? 0), range(0, self::BUCKETS - 1));
        }

        $buckets = [];
        foreach ($recorded as $index => $count) {
            $buckets[] = ['start' => $index * 5, 'end' => $index * 5 + 5, 'count' => $count];
        }

        return ['buckets' => $buckets, 'is_sample' => $isSample, 'candidates' => array_sum($recorded)];
    }
}
