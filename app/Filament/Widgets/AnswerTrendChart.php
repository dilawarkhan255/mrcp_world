<?php

namespace App\Filament\Widgets;

use App\Models\PracticeItem;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class AnswerTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Answers per day';

    protected static ?string $description = 'Submitted answers over the last 14 days (UTC).';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return auth()->user()?->can('billing.users.view') ?? false;
    }

    protected function getType(): string
    {
        return 'line';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        /** @var array{labels: list<string>, answered: list<int>, correct: list<int>} $data */
        $data = Cache::remember('admin.dashboard.answer-trend', now()->addMinutes(5), function (): array {
            $rows = PracticeItem::query()->toBase()->where('answered_at', '>=', now()->startOfDay()->subDays(13))
                ->groupByRaw('DATE(answered_at)')
                ->selectRaw('DATE(answered_at) AS day, COUNT(*) AS answered, COALESCE(SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END), 0) AS correct')
                ->get()->keyBy('day');
            $labels = $answered = $correct = [];
            for ($offset = 13; $offset >= 0; $offset--) {
                $day = now()->subDays($offset);
                $row = $rows[$day->toDateString()] ?? null;
                $labels[] = $day->format('j M');
                $answered[] = (int) ($row->answered ?? 0);
                $correct[] = (int) ($row->correct ?? 0);
            }

            return ['labels' => $labels, 'answered' => $answered, 'correct' => $correct];
        });

        return [
            'datasets' => [
                ['label' => 'Answered', 'data' => $data['answered'], 'borderColor' => '#1E6FD9', 'backgroundColor' => 'rgba(30, 111, 217, 0.12)', 'fill' => true, 'tension' => 0.35],
                ['label' => 'Correct', 'data' => $data['correct'], 'borderColor' => '#2DBE4E', 'backgroundColor' => 'transparent', 'tension' => 0.35],
            ],
            'labels' => $data['labels'],
        ];
    }
}
