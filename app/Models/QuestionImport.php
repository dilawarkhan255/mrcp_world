<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
            if ($import->past_paper_id && ! PastPaper::whereKey($import->past_paper_id)->where('exam_id', $import->exam_id)->exists()) {
                throw ValidationException::withMessages(['past_paper_id' => 'Past paper must belong to this exam.']);
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
