<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One start of the Knowledge Tutor: the filters the student chose and how
 * many facts they answered with them. Listed under "Last activity", so the
 * student can continue with the same filters.
 *
 * @property array{categories: list<int>, rating: string, difficulty: array{0: int, 1: int}, mode: string} $filters
 */
class TutorRun extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'exam_id', 'label', 'filters', 'answered'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['filters' => 'array', 'answered' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
