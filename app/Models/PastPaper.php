<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PastPaper extends Model
{
    /** @var list<string> */
    protected $fillable = ['exam_id', 'title', 'slug', 'year', 'sitting', 'description', 'image_path', 'is_published'];

    public const IMAGE_DISK = 'public';

    public const IMAGE_DIRECTORY = 'past-papers';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'year' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (PastPaper $paper) {
            if ($paper->image_path !== null && (! str_starts_with($paper->image_path, self::IMAGE_DIRECTORY.'/') || str_contains($paper->image_path, '..'))) {
                throw ValidationException::withMessages(['image_path' => 'Upload the cover image through the paper form.']);
            }
            if (Exam::whereKey($paper->exam_id)->whereNotNull('content_exam_id')->exists()) {
                throw ValidationException::withMessages(['exam_id' => 'This exam shares another exam\'s question bank. Add the paper to that exam instead.']);
            }
        });
        static::updating(function (PastPaper $paper) {
            if ($paper->isDirty('exam_id') && ($paper->questions()->withTrashed()->exists() || QuestionImport::where('past_paper_id', $paper->id)->exists() || PracticeSession::where('past_paper_id', $paper->id)->exists())) {
                throw ValidationException::withMessages(['exam_id' => 'A paper with content or sessions cannot be moved to another exam.']);
            }
        });
    }

    /** Public address of the cover image, or null. */
    public function imageUrl(): ?string
    {
        return $this->image_path === null ? null : Storage::disk(self::IMAGE_DISK)->url($this->image_path);
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
