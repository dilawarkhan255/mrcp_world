<?php

namespace App\Filament\Widgets;

use App\Models\QuestionImport;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestImportsWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'Recent bulk uploads';

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', QuestionImport::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(QuestionImport::query()->with('exam:id,name')->latest('id')->limit(5))
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#'),
                Tables\Columns\TextColumn::make('exam.name')->label('Exam'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'completed' => 'success',
                    'failed' => 'danger',
                    'processing' => 'info',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('imported')->label('Accepted')->numeric(),
                Tables\Columns\TextColumn::make('rejected')->numeric()->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('created_at')->label('Uploaded')->since(),
            ])
            ->emptyStateHeading('No uploads yet')
            ->emptyStateDescription('Bulk CSV or XLSX uploads appear here with their progress.')
            ->emptyStateIcon('heroicon-o-arrow-up-tray')
            ->headerActions([
                Tables\Actions\Action::make('all')->label('View all')->url('/admin/question-imports')->link(),
            ]);
    }
}
