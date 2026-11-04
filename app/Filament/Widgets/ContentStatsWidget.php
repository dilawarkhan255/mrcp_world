<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\PastPaper;
use App\Models\Question;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContentStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected function getHeading(): ?string
    {
        return 'Content library';
    }

    protected function getDescription(): ?string
    {
        return 'Every question we hold, and how much of the library is live.';
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        // One aggregate per table instead of one COUNT query per card.
        $questions = Question::query()->toBase()
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END), 0) AS published, COALESCE(SUM(CASE WHEN needs_review = 1 THEN 1 ELSE 0 END), 0) AS flagged')
            ->first();
        $papers = PastPaper::query()->toBase()
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END), 0) AS published')
            ->first();
        $total = (int) ($questions->total ?? 0);
        $published = (int) ($questions->published ?? 0);
        $share = $total > 0 ? round($published / $total * 100) : 0;

        return [
            Stat::make('Questions', number_format($total))
                ->description(number_format(Category::query()->count()).' subjects across all exams')
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('primary')
                ->icon('heroicon-o-question-mark-circle')
                ->url('/admin/questions'),
            Stat::make('Published', number_format($published))
                ->description($share.'% of the library is live')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->url('/admin/questions?tableFilters[is_published][value]=1'),
            Stat::make('Drafts', number_format($total - $published))
                ->description('Awaiting review and publication')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color('warning')
                ->icon('heroicon-o-pencil-square')
                ->url('/admin/questions?tableFilters[is_published][value]=0'),
            Stat::make('Past papers', number_format((int) ($papers->total ?? 0)))
                ->description(number_format((int) ($papers->published ?? 0)).' published')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info')
                ->icon('heroicon-o-document-text')
                ->url('/admin/past-papers'),
            Stat::make('Flagged for review', number_format((int) ($questions->flagged ?? 0)))
                ->description('Citations or facts a person must check')
                ->descriptionIcon('heroicon-m-flag')
                ->color((int) ($questions->flagged ?? 0) > 0 ? 'danger' : 'gray')
                ->icon('heroicon-o-flag')
                ->url('/admin/questions?tableFilters[needs_review][value]=1'),
        ];
    }
}
