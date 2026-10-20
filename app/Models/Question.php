<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

/** @property array<string, string|null> $options */
class Question extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['exam_id', 'category_id', 'past_paper_id', 'code', 'body', 'options', 'correct_option', 'explanation', 'is_published'];

    /** @var list<string> */
    protected $hidden = ['correct_option', 'explanation'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['options' => 'array', 'is_published' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Question $question) {
            $options = array_filter($question->options, fn ($value): bool => is_string($value) && trim($value) !== '');
            if (count($options) < 2 || count($options) > 5 || array_diff(array_keys($options), ['A', 'B', 'C', 'D', 'E']) || ! isset($options[$question->correct_option])) {
                throw ValidationException::withMessages(['options' => 'Provide 2–5 options and select an existing correct answer.']);
            }
            if (! Category::whereKey($question->category_id)->where('exam_id', $question->exam_id)->exists()) {
                throw ValidationException::withMessages(['category_id' => 'Category must belong to this exam.']);
            }
            if ($question->past_paper_id && ! PastPaper::whereKey($question->past_paper_id)->where('exam_id', $question->exam_id)->exists()) {
                throw ValidationException::withMessages(['past_paper_id' => 'Past paper must belong to this exam.']);
            }
            $question->options = $options;
        });
    }

    /** @param Builder<Question> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->where(function (Builder $query) {
            $query->whereNull('past_paper_id')->orWhereHas('pastPaper', fn (Builder $paper) => $paper->where('is_published', true));
        });
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

    /** @return BelongsTo<PastPaper, $this> */
    public function pastPaper(): BelongsTo
    {
        return $this->belongsTo(PastPaper::class);
    }
}
