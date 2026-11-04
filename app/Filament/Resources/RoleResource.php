<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages\ManageRoles;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles and the permissions they grant (spatie/laravel-permission). The
 * permissions themselves are defined in config/permissions.php, because each
 * one guards a feature in the code.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Users and billing';

    protected static ?string $navigationLabel = 'Roles & permissions';

    protected static ?int $navigationSort = 2;

    /** "content.questions.create" → "Content". */
    public static function area(string $permission): string
    {
        return Str::headline(Str::before($permission, '.'));
    }

    public static function label(string $permission): string
    {
        $labels = (array) config('permissions.labels');

        return self::area($permission).' — '.($labels[$permission] ?? $permission);
    }

    public static function isProtected(?Role $role): bool
    {
        return $role !== null && in_array($role->name, (array) config('permissions.protected_roles'), true);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(50)
                ->regex('/^[a-z][a-z0-9-]*$/')->helperText('Lowercase letters, numbers and dashes, e.g. "content-reviewer".')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('guard_name', 'web'))
                ->disabled(fn (?Role $record): bool => self::isProtected($record))
                ->dehydrated(fn (?Role $record): bool => ! self::isProtected($record)),
            Forms\Components\Placeholder::make('admin_note')->label('')
                ->content('The admin role always has every permission, so the panel can never be locked.')
                ->visible(fn (?Role $record): bool => $record?->name === 'admin'),
            Forms\Components\CheckboxList::make('permissions')->label('Permissions')
                ->relationship('permissions', 'name', fn (Builder $query) => $query->where('guard_name', 'web')->orderBy('name'))
                ->getOptionLabelFromRecordUsing(fn (Permission $record): string => self::label($record->name))
                ->columns(2)->bulkToggleable()->searchable()
                ->hidden(fn (?Role $record): bool => $record?->name === 'admin'),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->badge()->color(fn (string $state): string => match ($state) {
                'admin' => 'danger', 'editor' => 'warning', 'student' => 'gray', default => 'primary',
            })->searchable(),
            Tables\Columns\TextColumn::make('users_count')->label('Users')->counts('users')->sortable(),
            Tables\Columns\TextColumn::make('permissions_count')->label('Permissions')->counts('permissions'),
            Tables\Columns\TextColumn::make('permissions.name')->label('Areas')
                ->formatStateUsing(fn (string $state): string => self::area($state))->badge()->color('gray')->distinctList()->limitList(4),
        ])->actions([
            Tables\Actions\EditAction::make()->after(function (Role $record): void {
                if ($record->name === 'admin') {
                    $record->syncPermissions(Permission::where('guard_name', 'web')->get());
                }
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            }),
            Tables\Actions\DeleteAction::make()->after(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions()),
        ])->modifyQueryUsing(fn (Builder $query) => $query->where('guard_name', 'web')->with('permissions:id,name'))->defaultSort('id');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageRoles::route('/')];
    }
}
