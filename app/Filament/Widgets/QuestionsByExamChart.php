<?php

namespace App\Filament\Widgets;

use App\Models\Exam;
use App\Models\Question;
use Filament\Widgets\ChartWidget;

class QuestionsByExamChart extends ChartWidget
{
    protected static ?string $heading = 'Questions by exam';

    protected static ?string $description = 'Published and draft questions in each exam.';

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $exams = Exam::query()->whereNull('content_exam_id')->orderBy('sort_order')->orderBy('id')->pluck('name', 'id');
        $counts = Question::query()->toBase()->whereNull('deleted_at')
            ->groupBy('exam_id')
            ->selectRaw('exam_id, COUNT(*) AS total, COALESCE(SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END), 0) AS published')
            ->get()->keyBy('exam_id');
        $published = $drafts = [];
        foreach ($exams->keys() as $id) {
            $row = $counts[$id] ?? null;
            $published[] = (int) ($row->published ?? 0);
            $drafts[] = (int) ($row->total ?? 0) - (int) ($row->published ?? 0);
        }

        // One colour of the logo's "M" per exam, in turn.
        $logo = ['#F28C28', '#2DBE4E', '#1BB8D6', '#1E6FD9', '#5B3FC4', '#F5C518'];
        $colours = [];
        foreach (array_keys($published) as $index) {
            $colours[] = $logo[$index % count($logo)];
        }

        return [
            'datasets' => [
                ['label' => 'Published', 'data' => $published, 'backgroundColor' => $colours, 'borderRadius' => 6],
                ['label' => 'Drafts', 'data' => $drafts, 'backgroundColor' => 'rgba(148, 163, 184, 0.55)', 'borderRadius' => 6],
            ],
            'labels' => $exams->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    protected function getOptions(): array
    {
        return ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'beginAtZero' => true]]];
    }
}
