<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A notification shown in the bell of the student app. Admin panel
 * notifications use Filament's own format and appear in the panel instead.
 * Stored in the database only; no email is sent.
 */
class StudentNotice extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
        public string $icon = 'Bell',
        public string $kind = 'info',
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{title: string, body: string, url: string|null, icon: string, kind: string} */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'icon' => $this->icon, 'kind' => $this->kind];
    }
}
