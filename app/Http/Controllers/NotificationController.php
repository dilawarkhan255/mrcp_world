<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\StudentNotice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The bell of the student app. Only StudentNotice rows are listed; admin
 * panel notifications stay in the panel's own bell.
 */
class NotificationController extends Controller
{
    public const LIMIT = 15;

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $items = $user->notifications()->where('type', StudentNotice::class)->latest()->limit(self::LIMIT)->get()
            ->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'title' => (string) ($notification->data['title'] ?? ''),
                'body' => (string) ($notification->data['body'] ?? ''),
                'url' => $notification->data['url'] ?? null,
                'icon' => (string) ($notification->data['icon'] ?? 'Bell'),
                'kind' => (string) ($notification->data['kind'] ?? 'info'),
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toIso8601String(),
            ])->all();

        return response()->json(['items' => $items, 'unread' => self::unread($user)]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $notification = $user->notifications()->where('type', StudentNotice::class)->whereKey($id)->firstOrFail();
        $notification->markAsRead();
        $url = $notification->data['url'] ?? null;

        // Opening a notification marks it read and follows its link.
        return is_string($url) && str_starts_with($url, '/') ? redirect($url) : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->unreadNotifications()->where('type', StudentNotice::class)->update(['read_at' => now()]);

        return back();
    }

    public static function unread(User $user): int
    {
        return $user->unreadNotifications()->where('type', StudentNotice::class)->count();
    }
}
