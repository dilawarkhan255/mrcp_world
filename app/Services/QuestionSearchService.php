<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finds published questions of one exam by an exact MCQ code or by words in the stem.
 */
class QuestionSearchService
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 100;

    public const PAGE_SIZE = 20;

    /** Practice sessions are started from at most this many matches. */
    public const PRACTICE_LIMIT = 100;

    /** @return Builder<Question> */
    public function matches(Exam $exam, string $term): Builder
    {
        // Escape the LIKE wildcards so that % and _ in the search are literal characters.
        // ESCAPE '!' is written explicitly because the default escape character differs between MySQL and SQLite.
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        return Question::published()
            ->where('exam_id', $exam->id)
            ->where(function (Builder $query) use ($term, $escaped) {
                $query->where('code', $term)
                    ->orWhereRaw("body LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
            })
            ->with(['category:id,name'])
            ->orderBy('id');
    }
}
