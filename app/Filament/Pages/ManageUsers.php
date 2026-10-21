<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ManageUsers extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Users and billing';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $title = 'Users';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-users';

    /** Only an administrator may open the user list. */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('billing.users.view') ?? false;
    }

    public static function getRoutePath(): string
    {
        return 'users';
    }
}
