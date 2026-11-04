<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages\ManageSubscriptions;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Manual subscriptions: students send a request with a payment reference,
 * an admin checks the payment and activates it for the plan's period.
 */
class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Users and billing';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $pending = Subscription::where('status', Subscription::STATUS_PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Requests waiting for activation';
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['payment_reference', 'user.name', 'user.email'];
    }

    protected static int $globalSearchResultsLimit = 5;

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Subscription $record */
        return (string) (($record->user->name ?? 'Subscription').' · '.($record->qbank->name ?? ''));
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Subscription $record */
        return ['State' => $record->stateLabel(), 'Payment ref.' => $record->payment_reference ?? '—'];
    }

    /** @return Builder<Subscription> */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['user:id,name,email', 'qbank:id,name']);
    }

    /** These resources have no edit page of their own: open the list, searched for the record. */
    public static function getGlobalSearchResultUrl(Model $record): string
    {
        /** @var Subscription $record */
        return static::getUrl('index', ['tableSearch' => (string) $record->payment_reference]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')->label('Student')->relationship('user', 'email')->searchable()->required()
                ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->name.' — '.$record->email),
            Forms\Components\Select::make('qbank_id')->label('Qbank')->relationship('qbank', 'name')->required()->live(),
            Forms\Components\Select::make('plan_id')->label('Plan')
                ->relationship('plan', 'name', modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('qbank_id', $get('qbank_id'))),
            Forms\Components\Select::make('status')->options(Subscription::STATUSES)->required()->default(Subscription::STATUS_ACTIVE),
            Forms\Components\DateTimePicker::make('starts_at')->label('Access from')->default(now())->seconds(false),
            Forms\Components\DateTimePicker::make('ends_at')->label('Access until')->seconds(false)->after('starts_at')
                ->required(fn (Get $get): bool => $get('status') === Subscription::STATUS_ACTIVE)->default(now()->addDays(180)),
            Forms\Components\TextInput::make('payment_reference')->maxLength(255),
            Forms\Components\TextInput::make('amount')->numeric()->minValue(0)->prefix('£'),
            Forms\Components\Textarea::make('admin_note')->label('Admin note')->maxLength(1000)->rows(2)->columnSpanFull(),
            Forms\Components\Placeholder::make('student_note')->label('Student note')->content(fn (?Subscription $record): string => $record?->student_note ?: '—')
                ->visible(fn (?Subscription $record): bool => $record !== null)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('user.name')->label('Student')->searchable()->description(fn (Subscription $record): ?string => $record->user?->email),
            Tables\Columns\TextColumn::make('qbank.name')->label('Qbank')->badge()->color('gray'),
            Tables\Columns\TextColumn::make('status')->label('State')
                ->formatStateUsing(fn (Subscription $record): string => $record->stateLabel())->badge()
                ->color(fn (Subscription $record): string => match (true) {
                    $record->isCurrent() => 'success',
                    $record->status === Subscription::STATUS_PENDING => 'warning',
                    default => 'gray',
                }),
            Tables\Columns\TextColumn::make('payment_reference')->label('Payment ref.')->searchable()->limit(24)->toggleable(),
            Tables\Columns\TextColumn::make('starts_at')->label('From')->date()->sortable(),
            Tables\Columns\TextColumn::make('ends_at')->label('Until')->date()->sortable()
                ->color(fn (Subscription $record): ?string => $record->isCurrent() && $record->ends_at?->lt(now()->addDays(14)) ? 'warning' : null),
            Tables\Columns\TextColumn::make('created_at')->label('Requested')->since()->sortable()->toggleable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options(Subscription::STATUSES),
            Tables\Filters\SelectFilter::make('qbank')->relationship('qbank', 'name'),
            Tables\Filters\Filter::make('current')->label('Giving access now')
                ->query(fn (Builder $query) => $query->whereIn('id', Subscription::query()->current()->select('id'))),
            Tables\Filters\Filter::make('expiring')->label('Ends within 14 days')
                ->query(fn (Builder $query) => $query->whereIn('id', Subscription::query()->current()->select('id'))->where('ends_at', '<=', now()->addDays(14))),
        ])->actions([
            Tables\Actions\Action::make('activate')->iconButton()->label(fn (Subscription $record): string => $record->isCurrent() ? 'Extend' : 'Activate')
                ->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn (Subscription $record): bool => $record->status !== Subscription::STATUS_CANCELLED)
                ->authorize(fn (Subscription $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->form([
                    Forms\Components\TextInput::make('days')->label('Days of access')->numeric()->minValue(1)->maxValue(3650)->required()
                        ->default(fn (Subscription $record): int => (int) ($record->plan->duration_days ?? 180))
                        ->helperText('Starts now, or after the end of a subscription that is still running.'),
                ])
                ->modalDescription(fn (Subscription $record): string => 'Payment reference: '.($record->payment_reference ?: 'none').'. Check the payment before activating.')
                ->action(function (Subscription $record, array $data): void {
                    /** @var User $admin */
                    $admin = auth()->user();
                    $active = app(SubscriptionService::class)->activate($record, $admin, (int) $data['days']);
                    Notification::make()->success()->title('Access open until '.$active->ends_at?->toFormattedDateString())->send();
                }),
            Tables\Actions\Action::make('cancel')->iconButton()->label('Deactivate')->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn (Subscription $record): bool => $record->status !== Subscription::STATUS_CANCELLED)
                ->authorize(fn (Subscription $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->requiresConfirmation()->modalDescription('The student loses access to this Qbank straight away.')
                ->form([Forms\Components\TextInput::make('note')->label('Reason (optional)')->maxLength(1000)])
                ->action(fn (Subscription $record, array $data) => app(SubscriptionService::class)->cancel($record, $data['note'] ?? null)),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])->modifyQueryUsing(fn (Builder $query) => $query->with(['user:id,name,email', 'qbank:id,name', 'plan:id,duration_days']))
            ->defaultSort('id', 'desc')->paginationPageOptions([25, 50, 100]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageSubscriptions::route('/')];
    }

    /**
     * Default amount and currency from the chosen plan, for a subscription an admin grants directly.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillFromPlan(array $data): array
    {
        $plan = isset($data['plan_id']) ? Plan::find($data['plan_id']) : null;
        $data['currency'] = $plan->currency ?? 'GBP';
        $data['amount'] ??= $plan?->price;
        if (($data['status'] ?? null) === Subscription::STATUS_ACTIVE) {
            $data['activated_by'] = auth()->id();
            $data['activated_at'] = now();
        }

        return $data;
    }
}
