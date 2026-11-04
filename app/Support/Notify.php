<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\StudentNotice;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Throwable;

/**
 * One place for every in-app notification: students get a StudentNotice in
 * the bell of the student app; staff get a Filament notification in the bell
 * of the admin panel. Nothing is emailed.
 */
final class Notify
{
    public static function student(User $user, string $title, string $body, ?string $url = null, string $icon = 'Bell', string $kind = 'info'): void
    {
        self::safely(fn () => $user->notify(new StudentNotice($title, Str::limit($body, 240), $url, $icon, $kind)));
    }

    /**
     * A notification must never break the action that sent it (a sign-up,
     * an activation, an import): a failure is logged and the action goes on.
     */
    private static function safely(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** Every account with the permission, for example everyone who manages subscriptions. */
    public static function staffWith(string $permission, string $title, string $body, string $url, string $icon = 'heroicon-o-bell', string $color = 'info'): void
    {
        // An unknown permission (for example before the roles are seeded) means nobody to tell.
        if (! Permission::where('name', $permission)->where('guard_name', 'web')->exists()) {
            return;
        }
        $staff = User::permission($permission)->get();
        if ($staff->isNotEmpty()) {
            self::staff($staff->all(), $title, $body, $url, $icon, $color);
        }
    }

    /** @param list<User> $users */
    public static function staff(array $users, string $title, string $body, string $url, string $icon = 'heroicon-o-bell', string $color = 'info'): void
    {
        self::safely(fn () => Notification::make()
            ->title($title)
            ->body(Str::limit($body, 240))
            ->icon($icon)
            ->iconColor($color)
            ->actions([Action::make('open')->label('Open')->url($url)->markAsRead()])
            ->sendToDatabase($users));
    }
}
