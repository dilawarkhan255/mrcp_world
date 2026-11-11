<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** @property list<array{row: int, message: string}>|null $errors */
class QuestionImport extends Model
{
    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['publish' => 'boolean', 'errors' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (QuestionImport $import) {
            if (! str_starts_with($import->path, 'question-imports/') || str_contains($import->path, '..')) {
                throw ValidationException::withMessages(['path' => 'Select a private question import file.']);
            }
            if ($import->category_id && ! Category::whereKey($import->category_id)->where('exam_id', $import->exam_id)->exists()) {
                throw ValidationException::withMessages(['category_id' => 'The subject must belong to this exam.']);
            }
            if ($import->past_paper_id && ! PastPaper::whereKey($import->past_paper_id)->where('exam_id', $import->exam_id)->exists()) {
                throw ValidationException::withMessages(['past_paper_id' => 'Past paper must belong to this exam.']);
            }
            if ($import->isDirty('exam_id') && Exam::whereKey($import->exam_id)->whereNotNull('content_exam_id')->exists()) {
                throw ValidationException::withMessages(['exam_id' => 'This exam shares another exam\'s question bank. Import into that exam instead.']);
            }
        });
        // A deleted upload record takes its private file with it.
        static::deleted(function (QuestionImport $import) {
            if (str_starts_with($import->path, 'question-imports/')) {
                Storage::disk('local')->delete($import->path);
            }
        });
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
}
