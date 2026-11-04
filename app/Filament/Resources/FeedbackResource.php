<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FeedbackResource\Pages\ListFeedback;
use App\Models\Feedback;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class FeedbackResource extends Resource
{
    protected static ?string $model = Feedback::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    protected static ?string $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Student feedback';

    protected static ?string $modelLabel = 'feedback';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $new = Feedback::where('status', Feedback::STATUS_NEW)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('created_at')->label('Sent')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('user.name')->label('Student')->searchable(),
            Tables\Columns\TextColumn::make('exam.name')->label('Exam')->toggleable(),
            Tables\Columns\TextColumn::make('question.code')->label('MCQ')->placeholder('—')->badge()->color('gray')
                ->url(fn (Feedback $record): ?string => $record->question ? '/admin/questions?tableSearch='.urlencode($record->question->code) : null),
            Tables\Columns\TextColumn::make('type')->badge()->formatStateUsing(fn (string $state): string => Feedback::TYPES[$state] ?? $state),
            Tables\Columns\TextColumn::make('message')->limit(120)->wrap(),
            Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state): string => $state === Feedback::STATUS_NEW ? 'danger' : 'success'),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options([
                Feedback::STATUS_NEW => 'New',
                Feedback::STATUS_REVIEWED => 'Reviewed',
            ]),
            Tables\Filters\SelectFilter::make('type')->options(Feedback::TYPES),
            Tables\Filters\TernaryFilter::make('question_id')->label('MCQ reports')->nullable(),
        ])->actions([
            Tables\Actions\Action::make('markReviewed')
                ->label('Mark reviewed')
                ->icon('heroicon-o-check')
                ->visible(fn (Feedback $record): bool => $record->status === Feedback::STATUS_NEW)
                ->authorize(fn (Feedback $record): bool => Gate::allows('update', $record))
                ->action(fn (Feedback $record) => $record->update(['status' => Feedback::STATUS_REVIEWED])),
            Tables\Actions\DeleteAction::make(),
        ])->modifyQueryUsing(fn ($query) => $query->with(['user:id,name', 'exam:id,name', 'question:id,code']))->defaultSort('id', 'desc')->paginationPageOptions([25, 50, 100]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListFeedback::route('/')];
    }
}
