<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class PastPaper extends Model
{
    /** @var list<string> */
    protected $fillable = ['exam_id', 'title', 'slug', 'year', 'is_published'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'year' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (PastPaper $paper) {
            if ($paper->isDirty('exam_id') && ($paper->questions()->withTrashed()->exists() || QuestionImport::where('past_paper_id', $paper->id)->exists() || PracticeSession::where('past_paper_id', $paper->id)->exists())) {
                throw ValidationException::withMessages(['exam_id' => 'A paper with content or sessions cannot be moved to another exam.']);
            }
        });
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
