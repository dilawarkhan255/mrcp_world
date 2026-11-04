<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A chapter of the extended textbook. Chapters belong to the exam that owns
 * the content (so IMM reads the MRCP 1 UK/Irish textbook) and optionally to
 * a subject of that exam. Body and key points are Markdown.
 */
class TextbookChapter extends Model
{
    /** @var list<string> */
    protected $fillable = ['exam_id', 'category_id', 'title', 'slug', 'summary', 'body', 'key_points', 'sort_order', 'is_published'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (TextbookChapter $chapter) {
            if ((string) ($chapter->getAttributes()['slug'] ?? '') === '') {
                $base = Str::limit(Str::slug($chapter->title) ?: 'chapter', 190, '');
                $slug = $base;
                for ($suffix = 2; self::where('exam_id', $chapter->exam_id)->where('slug', $slug)->whereKeyNot($chapter->id ?? 0)->exists(); $suffix++) {
                    $slug = $base.'-'.$suffix;
                }
                $chapter->slug = $slug;
            }
            if (Exam::whereKey($chapter->exam_id)->whereNotNull('content_exam_id')->exists()) {
                throw ValidationException::withMessages(['exam_id' => 'This exam shares another exam\'s question bank. Add the chapter to that exam instead.']);
            }
            if ($chapter->category_id !== null && ! Category::whereKey($chapter->category_id)->where('exam_id', $chapter->exam_id)->exists()) {
                throw ValidationException::withMessages(['category_id' => 'The subject must belong to this exam.']);
            }
        });
    }

    /** @param Builder<TextbookChapter> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
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
}
