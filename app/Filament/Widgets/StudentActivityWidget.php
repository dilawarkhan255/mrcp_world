<?php

namespace App\Filament\Widgets;

use App\Models\Feedback;
use App\Models\PracticeItem;
use App\Models\PracticeSession;
use App\Models\Subscription;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StudentActivityWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getHeading(): ?string
    {
        return 'Student activity';
    }

    protected function getDescription(): ?string
    {
        return 'Last 7 days. Figures refresh every five minutes.';
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('billing.users.view') ?? false;
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        /** @var array{students: int, new: int, answers: int, correct: int, sessions: int, daily: list<float>} $data */
        $data = Cache::remember('admin.dashboard.activity', now()->addMinutes(5), function (): array {
            $since = now()->subDays(7);
            $students = User::role('student')->toBase()
                ->selectRaw('COUNT(*) AS total, COALESCE(SUM(CASE WHEN users.created_at >= ? THEN 1 ELSE 0 END), 0) AS recent', [$since])
                ->first();
            $answers = PracticeItem::query()->toBase()->where('answered_at', '>=', $since)
                ->selectRaw('COUNT(*) AS answered, COALESCE(SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END), 0) AS correct')
                ->first();
            $daily = PracticeItem::query()->toBase()->where('answered_at', '>=', now()->startOfDay()->subDays(6))
                ->groupByRaw('DATE(answered_at)')
                ->selectRaw('DATE(answered_at) AS day, COUNT(*) AS answered')
                ->pluck('answered', 'day');
            $series = [];
            for ($offset = 6; $offset >= 0; $offset--) {
                $series[] = (float) ($daily[now()->subDays($offset)->toDateString()] ?? 0);
            }

            return [
                'students' => (int) ($students->total ?? 0),
                'new' => (int) ($students->recent ?? 0),
                'answers' => (int) ($answers->answered ?? 0),
                'correct' => (int) ($answers->correct ?? 0),
                'sessions' => PracticeSession::query()->where('created_at', '>=', $since)->count(),
                'daily' => $series,
            ];
        });
        $accuracy = $data['answers'] > 0 ? round($data['correct'] / $data['answers'] * 100) : 0;

        return [
            Stat::make('Students', number_format($data['students']))
                ->description('+'.number_format($data['new']).' joined this week')
                ->descriptionIcon('heroicon-m-user-plus')
                ->icon('heroicon-o-users')
                ->color('primary')
                ->url('/admin/users'),
            Stat::make('Answers submitted', number_format($data['answers']))
                ->description($accuracy.'% answered correctly')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->icon('heroicon-o-check-badge')
                ->chart($data['daily'])
                ->color('success'),
            Stat::make('Sessions started', number_format($data['sessions']))
                ->description('Practice, mocks and reviews')
                ->descriptionIcon('heroicon-m-play')
                ->icon('heroicon-o-academic-cap')
                ->color('info'),
            Stat::make('Active subscriptions', number_format(Subscription::query()->current()->count()))
                ->description(number_format(Subscription::where('status', Subscription::STATUS_PENDING)->count()).' requests waiting for activation')
                ->descriptionIcon('heroicon-m-clock')
                ->icon('heroicon-o-credit-card')
                ->color('primary')
                ->url('/admin/subscriptions?tableFilters[status][value]=pending'),
            Stat::make('Open feedback', number_format(Feedback::where('status', Feedback::STATUS_NEW)->count()))
                ->description('Messages waiting for review')
                ->descriptionIcon('heroicon-m-inbox')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('warning')
                ->url('/admin/feedback'),
        ];
    }
}
