<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AnswerTrendChart;
use App\Filament\Widgets\ContentStatsWidget;
use App\Filament\Widgets\ContentWorkflow;
use App\Filament\Widgets\LatestFeedbackWidget;
use App\Filament\Widgets\LatestImportsWidget;
use App\Filament\Widgets\QuestionsByExamChart;
use App\Filament\Widgets\StudentActivityWidget;
use App\Models\Question;
use App\Models\QuestionImport;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as FilamentDashboard;

class Dashboard extends FilamentDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = -2;

    protected ?string $heading = 'Dashboard';

    public function getSubheading(): ?string
    {
        $name = (string) (auth()->user()->name ?? '');
        $greeting = match (true) {
            now()->hour < 12 => 'Good morning',
            now()->hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        return trim($greeting.', '.strtok($name, ' ')).'. Content health, student activity and the publishing workflow at a glance.';
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('newQuestion')
                ->label('New MCQ')
                ->icon('heroicon-m-plus')
                ->url('/admin/questions?action=create')
                ->visible(fn (): bool => auth()->user()?->can('create', Question::class) ?? false),
            Action::make('bulkUpload')
                ->label('Bulk upload')
                ->icon('heroicon-m-arrow-up-tray')
                ->color('gray')
                ->url('/admin/question-imports')
                ->visible(fn (): bool => auth()->user()?->can('viewAny', QuestionImport::class) ?? false),
            Action::make('studentView')
                ->label('Student view')
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->color('gray')
                ->url('/dashboard', shouldOpenInNewTab: true),
        ];
    }

    public function getWidgets(): array
    {
        return [
            ContentStatsWidget::class,
            StudentActivityWidget::class,
            AnswerTrendChart::class,
            QuestionsByExamChart::class,
            LatestImportsWidget::class,
            LatestFeedbackWidget::class,
            ContentWorkflow::class,
        ];
    }

    /** @return int|string|array<string, int|string|null> */
    public function getColumns(): int|string|array
    {
        return ['default' => 1, 'lg' => 2];
    }
}
