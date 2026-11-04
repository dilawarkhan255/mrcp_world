<?php

namespace App\Models;

use App\Support\QuestionContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

/**
 * @property array<string, string|null> $options
 * @property list<array{source: string, title: string, url?: string|null, year?: string|null}>|null $references
 * @property list<array{label: string, url: string}>|null $image_links
 */
class Question extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'exam_id', 'category_id', 'past_paper_id', 'code', 'body', 'options', 'correct_option', 'explanation',
        'key_points', 'mnemonics', 'references', 'youtube_url', 'image_links', 'image_search', 'image_search_sites', 'system', 'repeat_count', 'source_reference',
        'needs_review', 'review_note', 'is_published', 'is_demo', 'is_sample', 'difficulty',
    ];

    /** Reference sources an editor can choose. Other covers BNF, BTS, ESC and similar bodies. */
    public const REFERENCE_SOURCES = ['NICE' => 'NICE', 'WHO' => 'WHO', 'Other' => 'Other guideline or source'];

    /**
     * Everything that reveals or explains the answer stays out of serialised
     * models, so an unanswered question can never leak it.
     *
     * @var list<string>
     */
    protected $hidden = ['correct_option', 'explanation', 'key_points', 'mnemonics', 'references', 'youtube_url', 'image_links', 'image_search', 'image_search_sites', 'review_note'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'options' => 'array', 'references' => 'array', 'image_links' => 'array', 'image_search_sites' => 'array',
            'is_published' => 'boolean', 'is_demo' => 'boolean', 'is_sample' => 'boolean', 'needs_review' => 'boolean',
            'difficulty' => 'integer', 'repeat_count' => 'integer',
        ];
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
            if ($question->youtube_url !== null && $question->youtube_url !== '' && QuestionContent::youtubeId($question->youtube_url) === null) {
                throw ValidationException::withMessages(['youtube_url' => 'Enter a YouTube watch, share or embed link.']);
            }
            $question->references = QuestionContent::cleanReferences($question->references);
            $question->image_links = QuestionContent::cleanLinks($question->image_links);
            $question->image_search = QuestionContent::cleanSearchTerm($question->image_search);
            $question->image_search_sites = QuestionContent::cleanSearchSites($question->image_search_sites);
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
