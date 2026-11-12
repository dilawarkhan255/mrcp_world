<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * A flashcard: a short prompt on the front and the answer on the back
 * (Markdown, rendered with raw HTML stripped). It belongs to the exam that
 * owns the content, so students of a shared-bank exam study the owner's cards.
 */
class Flashcard extends Model
{
    /** @var list<string> */
    protected $fillable = ['exam_id', 'category_id', 'question_id', 'front', 'back', 'high_yield', 'sort_order', 'is_published'];

    /**
     * How important a fact is for the exam, most important first. The Knowledge
     * Tutor asks the higher ones first and its progress counts A++ to A.
     *
     * @var array<string, string>
     */
    public const HIGH_YIELD = [
        'A++' => 'A++ · comes up again and again',
        'A+' => 'A+ · very often tested',
        'A' => 'A · often tested',
        'B' => 'B · sometimes tested',
        'C' => 'C · good to know',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'sort_order' => 'integer', 'difficulty' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (Flashcard $card) {
            if (Exam::whereKey($card->exam_id)->whereNotNull('content_exam_id')->exists()) {
                throw ValidationException::withMessages(['exam_id' => 'This exam shares another exam\'s question bank. Add the card to that exam instead.']);
            }
            if (! array_key_exists((string) $card->high_yield, self::HIGH_YIELD)) {
                $card->high_yield = 'A';
            }
            if ($card->category_id !== null && ! Category::whereKey($card->category_id)->where('exam_id', $card->exam_id)->exists()) {
                throw ValidationException::withMessages(['category_id' => 'The subject must belong to this exam.']);
            }
        });
    }

    /** @param Builder<Flashcard> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('flashcards.is_published', true);
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
