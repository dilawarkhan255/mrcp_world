<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PrivacyNotice extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Privacy';

    protected static ?string $title = 'Privacy';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.privacy-notice';

    /** Every staff member may read the privacy notice. */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('system.privacy.view') ?? false;
    }

    public static function getRoutePath(): string
    {
        return 'privacy';
    }
}
