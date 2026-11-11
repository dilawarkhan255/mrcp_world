<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactMessageResource\Pages\ListContactMessages;
use App\Filament\Support\CrudActions;
use App\Models\ContactMessage;
use Filament\Forms;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/** Messages from the public Contact page: read, mark as replied, delete. */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Contact messages';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $new = ContactMessage::where('status', ContactMessage::STATUS_NEW)->count();

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
            Tables\Columns\TextColumn::make('name')->searchable()->weight('bold'),
            Tables\Columns\TextColumn::make('email')->searchable()->copyable(),
            Tables\Columns\TextColumn::make('phone')->placeholder('—'),
            Tables\Columns\TextColumn::make('message')->limit(110)->wrap(),
            Tables\Columns\TextColumn::make('status')->badge()
                ->color(fn (string $state): string => $state === ContactMessage::STATUS_NEW ? 'danger' : 'success'),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options([ContactMessage::STATUS_NEW => 'New', ContactMessage::STATUS_REPLIED => 'Replied']),
        ])->actions([
            Tables\Actions\ViewAction::make()->modalHeading('Contact message')->form([
                Forms\Components\Placeholder::make('from')->content(fn (ContactMessage $record): string => $record->name.' · '.$record->email.($record->phone ? ' · '.$record->phone : '')),
                Forms\Components\Placeholder::make('sent')->content(fn (ContactMessage $record): string => $record->created_at?->toDayDateTimeString() ?? ''),
                Forms\Components\Placeholder::make('text')->label('Message')->content(fn (ContactMessage $record): string => $record->message),
            ]),
            Tables\Actions\Action::make('reply')->iconButton()->label('Reply by email')->icon('heroicon-o-paper-airplane')->color('info')
                ->url(fn (ContactMessage $record): string => 'mailto:'.$record->email.'?subject='.rawurlencode('Re: your message to '.config('app.name'))),
            Tables\Actions\Action::make('markReplied')->iconButton()->label('Mark replied')->icon('heroicon-o-check')->color('success')
                ->visible(fn (ContactMessage $record): bool => $record->status === ContactMessage::STATUS_NEW)
                ->authorize(fn (ContactMessage $record): bool => Gate::allows('update', $record))
                ->action(fn (ContactMessage $record) => $record->update(['status' => ContactMessage::STATUS_REPLIED])),
            Tables\Actions\DeleteAction::make(),
        ])->bulkActions([CrudActions::bulkDelete()])->defaultSort('id', 'desc')->paginationPageOptions([25, 50, 100]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListContactMessages::route('/')];
    }
}
