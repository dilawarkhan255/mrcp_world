<?php

namespace App\Models;

use Database\Factories\ExamFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['qbank_id', 'content_exam_id', 'name', 'slug', 'description', 'code', 'is_active', 'sort_order'];

    protected static function booted(): void
    {
        static::saving(function (Exam $exam) {
            if ($exam->content_exam_id === null) {
                return;
            }
            if ($exam->exists && (int) $exam->content_exam_id === (int) $exam->id) {
                throw ValidationException::withMessages(['content_exam_id' => 'An exam cannot share its own content.']);
            }
            $source = Exam::find($exam->content_exam_id);
            if ($source === null || $source->content_exam_id !== null) {
                throw ValidationException::withMessages(['content_exam_id' => 'Choose an exam that owns its own subjects and questions.']);
            }
            if ($exam->qbank_id === null || (int) $source->qbank_id !== (int) $exam->qbank_id) {
                throw ValidationException::withMessages(['content_exam_id' => 'The shared exam must belong to the same Qbank.']);
            }
            if ($exam->exists && (Exam::where('content_exam_id', $exam->id)->exists() || $exam->categories()->exists() || PastPaper::where('exam_id', $exam->id)->exists())) {
                throw ValidationException::withMessages(['content_exam_id' => 'This exam already has its own content or is shared by another exam.']);
            }
        });
    }

    /** True when the exam reads the subjects and questions of another exam. */
    public function sharesContent(): bool
    {
        return $this->content_exam_id !== null;
    }

    /** The id of the exam that holds the subjects, papers and questions shown for this exam. */
    public function contentId(): int
    {
        return (int) ($this->content_exam_id ?? $this->id);
    }

    /**
     * Exams that hold their own content. Admin content forms only offer these.
     *
     * @param  Builder<Exam>  $query
     */
    public function scopeOwnsContent(Builder $query): void
    {
        $query->whereNull('content_exam_id');
    }

    /** @return BelongsTo<Qbank, $this> */
    public function qbank(): BelongsTo
    {
        return $this->belongsTo(Qbank::class);
    }

    /** @return BelongsTo<Exam, $this> */
    public function contentExam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'content_exam_id');
    }

    /** Two letter tile code, derived from the name when the exam has none. */
    public function tileCode(): string
    {
        if ($this->code !== null && $this->code !== '') {
            return strtoupper($this->code);
        }
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $this->name) ?? '');

        return substr($letters, 0, 2) ?: 'MR';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'qbank_id' => 'integer', 'content_exam_id' => 'integer'];
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /** @return HasMany<ScoreDistribution, $this> */
    public function scoreDistributions(): HasMany
    {
        return $this->hasMany(ScoreDistribution::class);
    }
}
