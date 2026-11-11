<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action as TableAction;

/**
 * One look for every Create, Edit and View window of the admin panel: a
 * centred modal (never a side panel), an icon and heading that stay at the
 * top and buttons that stay at the bottom while a long form scrolls, buttons
 * on the right, and a form that does not close when the backdrop is clicked
 * (so typed work is not lost). Registered for every resource in
 * AdminPanelProvider::boot(); a resource can still ask for a wider window.
 * The colours (logo line, icon tint) are in filament/admin-styles.
 */
final class CrudModal
{
    public const ICONS = [
        'create' => 'heroicon-o-plus-circle',
        'edit' => 'heroicon-o-pencil-square',
        'view' => 'heroicon-o-eye',
    ];

    public static function style(Action|TableAction $action, string $kind, MaxWidth $width = MaxWidth::ThreeExtraLarge): Action|TableAction
    {
        return $action
            ->slideOver(false)
            ->modalWidth($width)
            ->modalIcon(self::ICONS[$kind] ?? null)
            ->modalIconColor('primary')
            ->modalAlignment(Alignment::Start)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalFooterActionsAlignment(Alignment::End)
            // A form keeps its typed data until Cancel; a read-only view closes on the backdrop.
            ->closeModalByClickingAway($kind === 'view');
    }
}
