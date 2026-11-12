<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages\ManageUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Filament\Support\CrudActions;
use App\Models\Subscription;
use App\Models\User;
use App\Support\RoleGuard;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission;

/**
 * User accounts: add users, reset passwords and give roles (spatie). Admins
 * see full email addresses; a panel user without billing.users.manage sees
 * them masked, as the privacy notice promises.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Users and billing';

    protected static ?string $navigationLabel = 'Users';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Save the account fields. email_verified_at is not mass assignable on the
     * model (a student must never set it), so it is written explicitly here.
     *
     * @param  array<string, mixed>  $data
     */
    public static function saveAccount(User $user, array $data): User
    {
        $verified = $data['email_verified_at'] ?? null;
        unset($data['email_verified_at']);
        $user->fill($data);
        $user->forceFill(['email_verified_at' => $verified]);
        $user->save();

        return $user;
    }

    /** Keeps the domain and hides the local part. */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($local, 0, 2).'***@'.$domain;
    }

    public static function showsFullEmail(): bool
    {
        return ! config('privacy.mask_email_for_editors') || (auth()->user()?->can('billing.users.manage') ?? false);
    }

    private static function canManageRoles(): bool
    {
        return auth()->user()?->can('system.roles.manage') ?? false;
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    protected static int $globalSearchResultsLimit = 5;

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var User $record */
        return (string) ($record->name);
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var User $record */
        return ['Email' => self::showsFullEmail() ? $record->email : self::mask($record->email)];
    }

    /** @return Builder<User> */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with([]);
    }

    /** These resources have no edit page of their own: open the list, searched for the record. */
    public static function getGlobalSearchResultUrl(Model $record): string
    {
        /** @var User $record */
        return static::getUrl('index', ['tableSearch' => $record->email]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Account')->columns(2)->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('password')->password()->revealable()
                    ->rule(Password::defaults())
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): string => $operation === 'create' ? 'Share it with the user privately; they can change it in their settings.' : 'Leave empty to keep the current password.'),
                Forms\Components\Toggle::make('email_verified_at')->label('Email address verified')
                    ->formatStateUsing(fn ($state): bool => filled($state))
                    ->dehydrateStateUsing(fn ($state, ?User $record) => $state ? ($record->email_verified_at ?? now()) : null)
                    ->default(true)->inline(false)
                    ->helperText('Unverified users must confirm their email before they can practise.'),
            ]),
            Forms\Components\Section::make('Roles')->visible(fn (): bool => self::canManageRoles())->schema([
                Forms\Components\CheckboxList::make('roles')->label('')
                    ->relationship('roles', 'name', fn (Builder $query) => $query->where('guard_name', 'web')->orderBy('id'))
                    ->columns(3)->required()
                    ->helperText('admin: everything · editor: content · student: the student app. New roles appear here after you create them in Roles & permissions.')
                    ->rules([fn (?User $record): Closure => function (string $attribute, $value, Closure $fail) use ($record): void {
                        $reason = RoleGuard::refusal($record, (array) $value, auth()->user());
                        if ($reason !== null) {
                            $fail($reason);
                        }
                    }]),
            ]),
            Forms\Components\Section::make('Extra permissions')->collapsed()->visible(fn (): bool => self::canManageRoles())
                ->description('Only for exceptions. Prefer giving a role; these add to the permissions of the user\'s roles.')
                ->schema([
                    Forms\Components\CheckboxList::make('permissions')->label('')
                        ->relationship('permissions', 'name', fn (Builder $query) => $query->where('guard_name', 'web')->orderBy('name'))
                        ->getOptionLabelFromRecordUsing(fn (Permission $record): string => RoleResource::label($record->name))
                        ->columns(2)->searchable()->bulkToggleable(),
                ]),
            Forms\Components\Section::make('Leaderboard')->collapsed()->columns(2)->schema([
                Forms\Components\Toggle::make('leaderboard_opt_in')->label('Shown on the leaderboard')->inline(false),
                Forms\Components\TextInput::make('leaderboard_name')->label('Display name')->maxLength(30)->regex('/^[\pL\pN][\pL\pN .\-_]*$/u')->notRegex('/@/'),
            ]),
        ]);
    }

    /** The user's own page (View). */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Account')->icon('heroicon-o-user')->columns(4)->schema([
                Infolists\Components\TextEntry::make('name')->weight('bold'),
                Infolists\Components\TextEntry::make('email')->copyable(fn (): bool => self::showsFullEmail())
                    ->formatStateUsing(fn (string $state): string => self::showsFullEmail() ? $state : self::mask($state)),
                Infolists\Components\IconEntry::make('verified')->label('Email verified')->boolean()
                    ->state(fn (User $record): bool => $record->email_verified_at !== null),
                Infolists\Components\TextEntry::make('created_at')->label('Registered')->dateTime('j M Y, H:i'),
                Infolists\Components\TextEntry::make('roles.name')->label('Roles')->badge()->placeholder('No role'),
                Infolists\Components\TextEntry::make('leaderboard')->label('Leaderboard')
                    ->state(fn (User $record): string => $record->leaderboard_opt_in ? 'Shown as '.($record->leaderboard_name ?: $record->name) : 'Not shown'),
            ]),
            Infolists\Components\Section::make('Subscriptions')->icon('heroicon-o-credit-card')->schema([
                Infolists\Components\RepeatableEntry::make('subscriptions')->hiddenLabel()->columns(5)
                    ->state(fn (User $record) => $record->subscriptions()->with(['qbank:id,name', 'plan:id,name'])->latest('id')->limit(20)->get())
                    ->schema([
                        Infolists\Components\TextEntry::make('qbank.name')->label('Qbank')->weight('bold'),
                        Infolists\Components\TextEntry::make('plan.name')->label('Plan')->placeholder('—'),
                        Infolists\Components\TextEntry::make('state')->label('Status')->badge()
                            ->state(fn (Subscription $record): string => $record->stateLabel())
                            ->color(fn (Subscription $record): string => $record->isCurrent() ? 'success' : 'gray'),
                        Infolists\Components\TextEntry::make('ends_at')->label('Ends')->date('j M Y')->placeholder('—'),
                        Infolists\Components\TextEntry::make('amount')->label('Paid')->placeholder('—')
                            ->formatStateUsing(fn ($state, Subscription $record): string => strtoupper((string) ($record->currency ?: 'GBP')).' '.number_format((float) $state, 2)),
                    ])->placeholder('No subscriptions.'),
            ]),
            Infolists\Components\Section::make('Practice')->icon('heroicon-o-chart-bar')->columns(4)->schema([
                Infolists\Components\TextEntry::make('sessions_total')->label('Sessions')
                    ->state(fn (User $record): int => (int) self::activity($record)?->sessions),
                Infolists\Components\TextEntry::make('answered_total')->label('MCQs answered')
                    ->state(fn (User $record): int => (int) self::activity($record)?->answered),
                Infolists\Components\TextEntry::make('correct_rate')->label('Correct')
                    ->state(function (User $record): string {
                        $activity = self::activity($record);

                        return $activity !== null && $activity->answered > 0 ? round($activity->correct / $activity->answered * 100).'%' : '—';
                    }),
                Infolists\Components\TextEntry::make('last_active')->label('Last practice')
                    ->state(fn (User $record): ?string => self::activity($record)?->last_at)->since()->placeholder('Never'),
            ]),
        ]);
    }

    /** One user's practice totals, read once per page. */
    private static function activity(User $record): ?object
    {
        return once(fn (): ?object => DB::table('practice_sessions')->where('user_id', $record->id)
            ->selectRaw('count(*) as sessions, coalesce(sum(answered_count), 0) as answered, coalesce(sum(correct_count), 0) as correct, max(updated_at) as last_at')
            ->first());
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('bold')
                ->description(fn (User $record): string => self::showsFullEmail() ? $record->email : self::mask($record->email)),
            Tables\Columns\TextColumn::make('email')->searchable()->hidden(),
            Tables\Columns\TextColumn::make('roles.name')->label('Roles')->badge()
                ->color(fn (string $state): string => match ($state) {
                    'admin' => 'danger', 'editor' => 'warning', 'student' => 'gray', default => 'primary'
                }),
            Tables\Columns\IconColumn::make('email_verified_at')->label('Verified')->boolean()
                ->state(fn (User $record): bool => $record->email_verified_at !== null),
            Tables\Columns\TextColumn::make('subscriptions_count')->label('Active Qbanks')
                ->state(fn (User $record): int => (int) ($record->getAttribute('active_subscriptions') ?? 0))->alignCenter(),
            Tables\Columns\TextColumn::make('created_at')->label('Registered')->since()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('roles')->relationship('roles', 'name')->multiple()->preload(),
            Tables\Filters\TernaryFilter::make('email_verified_at')->label('Email verified')->nullable(),
            Tables\Filters\Filter::make('subscribed')->label('Has an active subscription')
                ->query(fn (Builder $query) => $query->whereIn('id', Subscription::query()->current()->select('user_id'))),
        ])->actions([
            CrudActions::viewPage(self::class),
            Tables\Actions\EditAction::make()
                ->using(fn (User $record, array $data): User => self::saveAccount($record, $data)),
            Tables\Actions\Action::make('removeFromLeaderboard')->iconButton()->label('Remove from leaderboard')->icon('heroicon-o-trophy')->color('gray')
                ->visible(fn (User $record): bool => $record->leaderboard_opt_in)
                ->authorize(fn (User $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->requiresConfirmation()
                ->modalDescription('The student disappears from the leaderboard within ten minutes and can opt in again with another name.')
                ->action(function (User $record): void {
                    $record->update(['leaderboard_opt_in' => false, 'leaderboard_name' => null]);
                    Notification::make()->success()->title('Removed from the leaderboard')->send();
                }),
            Tables\Actions\DeleteAction::make()->visible(self::deletable()),
            CrudActions::deleteWithContents(self::deletable(), 'email', fn (Model $record): bool => $record->getKey() !== auth()->id()),
        ])->bulkActions([CrudActions::bulkDelete(self::deletable()), CrudActions::bulkDeleteWithContents(fn (Model $record): bool => $record->getKey() !== auth()->id())])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles:id,name')
                ->withCount(['subscriptions as active_subscriptions' => fn (Builder $query) => $query->whereIn('id', Subscription::query()->current()->select('id'))]))
            ->defaultSort('id', 'desc')->paginationPageOptions([25, 50, 100]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/'), 'view' => ViewUser::route('/{record}')];
    }

    /** @return Closure(Model): bool Never your own account, nor one that uploaded MCQ imports (kept for the record). */
    public static function deletable(): Closure
    {
        return fn (Model $record): bool => $record->getKey() !== auth()->id()
            && ! DB::table('question_imports')->where('user_id', $record->getKey())->exists();
    }
}
