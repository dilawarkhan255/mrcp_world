<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PracticeSession extends Model
{
    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
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
}
