<?php

namespace App\Filament\Widgets;

use App\Models\Feedback;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestFeedbackWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected static ?string $heading = 'Latest student feedback';

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Feedback::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Feedback::query()->with('user:id,name')->where('status', Feedback::STATUS_NEW)->latest('id')->limit(5))
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Student'),
                Tables\Columns\TextColumn::make('type')->badge()->formatStateUsing(fn (string $state): string => Feedback::TYPES[$state] ?? $state),
                Tables\Columns\TextColumn::make('message')->limit(60)->wrap(),
                Tables\Columns\TextColumn::make('created_at')->label('Sent')->since(),
            ])
            ->emptyStateHeading('Inbox zero')
            ->emptyStateDescription('New student feedback will show here.')
            ->emptyStateIcon('heroicon-o-inbox')
            ->headerActions([
                Tables\Actions\Action::make('all')->label('Open inbox')->url('/admin/feedback')->link(),
            ]);
    }
}
