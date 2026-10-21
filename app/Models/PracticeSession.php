<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PracticeSession extends Model
{
    public const MODE_PRACTICE = 'practice';

    public const MODE_MOCK = 'mock';

    public const MODE_REVIEW = 'review';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'started_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** @return BelongsTo<PastPaper, $this> */
    public function pastPaper(): BelongsTo
    {
        return $this->belongsTo(PastPaper::class);
    }

    /** @return HasMany<PracticeItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PracticeItem::class);
    }

    public function isMock(): bool
    {
        return $this->mode === self::MODE_MOCK;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Seconds left before the mock closes, never negative. */
    public function remainingSeconds(): int
    {
        if ($this->expires_at === null) {
            return 0;
        }

        return (int) max(0, now()->diffInSeconds($this->expires_at, false));
    }
}
