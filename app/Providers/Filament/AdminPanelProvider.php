<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Table row actions are icon buttons with their name as a tooltip, the
     * same in every resource. Edit and delete keep Filament's pencil and bin
     * icons (set in their setUp(), which runs after these defaults); custom
     * actions name their own icons.
     */
    public function boot(): void
    {
        Tables\Actions\EditAction::configureUsing(fn (Tables\Actions\EditAction $action) => $action
            ->iconButton()->color('primary'));
        Tables\Actions\DeleteAction::configureUsing(fn (Tables\Actions\DeleteAction $action) => $action
            ->iconButton());
        Tables\Actions\Action::configureUsing(fn (Tables\Actions\Action $action) => $action
            ->tooltip(fn (): ?string => $action->isIconButton() ? $action->getLabel() : null));
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(fn (): string => (string) config('app.name'))
            ->brandLogo(asset('brand/mrcp-world-logo.trim.png'))
            ->darkModeBrandLogo(asset('brand/mrcp-world-logo.dark.png'))
            ->brandLogoHeight('4rem')
            ->favicon(asset('brand/mrcp-world-mark.trim.png'))
            ->font('Manrope')
            ->colors([
                'primary' => Color::hex('#0B2A6F'),
                'gray' => Color::Slate,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('17rem')
            ->maxContentWidth(MaxWidth::ScreenTwoExtraLarge)
            ->navigationGroups([
                NavigationGroup::make('Content'),
                NavigationGroup::make('Media'),
                NavigationGroup::make('Community'),
                NavigationGroup::make('Users and billing'),
                NavigationGroup::make('System')->collapsed(),
            ])
            ->darkMode(true)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            // The brand logo links here.
            ->homeUrl(fn (): string => Dashboard::getUrl())
            // The way back to the student app (it has an "Admin panel" link to return here).
            ->navigationItems([
                NavigationItem::make('Student app')
                    ->url('/dashboard')
                    ->icon('heroicon-o-academic-cap')
                    ->sort(-1), // Right after the Dashboard (-2), which must stay first: Filament uses the first item as the panel URL.
            ])
            ->userMenuItems([
                MenuItem::make()->label('Open the student app')->url('/dashboard')->icon('heroicon-o-academic-cap'),
            ])
            ->renderHook(PanelsRenderHook::HEAD_START, fn (): string => view('filament.appearance-sync')->render())
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): string => view('filament.admin-styles')->render())
            ->renderHook(PanelsRenderHook::TOPBAR_START, fn (): string => view('filament.topbar-toggle')->render())
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn (): string => view('filament.appearance-switch')->render())
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldSuffix(fn (): string => 'Ctrl K')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ], isPersistent: true);
    }
}
