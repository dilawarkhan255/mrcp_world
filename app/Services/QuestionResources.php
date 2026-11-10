<?php

namespace App\Services;

use App\Models\AtlasImage;
use App\Models\Exam;
use App\Models\Question;
use App\Models\TextbookChapter;
use App\Support\QuestionContent;
use Illuminate\Database\Eloquent\Builder;

/**
 * The learning resources of one MCQ for the right sidebar of the practice
 * screen. They are only sent inside the revealed result (after the answer,
 * or after a mock closes), because a chapter title or an atlas image can
 * name the diagnosis.
 *
 * Textbook: the chapters an editor linked to the MCQ, otherwise the
 * chapters of its subject. Atlas: the images an editor linked, otherwise
 * published images whose title or diagnosis matches the MCQ's search term.
 * Search links (Google Images, YouTube) are built from the editor's search
 * term only; no link is ever invented.
 */
class QuestionResources
{
    public const TEXTBOOK_LIMIT = 4;

    public const ATLAS_LIMIT = 6;

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{textbook: array{items: list<array{title: string, summary: string|null, url: string}>, linked: bool, subject: string|null}, atlas: array{items: list<array{id: int, title: string, diagnosis: string|null, image_url: string, url: string, credit: string|null}>, linked: bool}, web: array{term: string|null, google_images: string|null, youtube: string|null}}
     */
    public function forQuestion(?int $questionId, array $snapshot): array
    {
        $term = QuestionContent::cleanSearchTerm(isset($snapshot['image_search']) ? (string) $snapshot['image_search'] : null);
        $resources = [
            'textbook' => ['items' => [], 'linked' => false, 'subject' => null],
            'atlas' => ['items' => [], 'linked' => false],
            'web' => [
                'term' => $term,
                'google_images' => $term === null ? null : 'https://www.google.com/search?tbm=isch&q='.rawurlencode($term),
                'youtube' => $term === null ? null : 'https://www.youtube.com/results?search_query='.rawurlencode($term),
            ],
        ];
        $question = $questionId === null ? null : Question::withTrashed()->with('category:id,name')->find($questionId, ['id', 'exam_id', 'category_id']);
        if ($question === null) {
            return $resources;
        }

        $resources['textbook'] = $this->textbook($question);
        $resources['atlas'] = $this->atlas($question, $term);

        return $resources;
    }

    /** @return array{items: list<array{title: string, summary: string|null, url: string}>, linked: bool, subject: string|null} */
    private function textbook(Question $question): array
    {
        $linked = $question->textbookChapters()->published()->where('textbook_chapters.exam_id', $question->exam_id)
            ->limit(self::TEXTBOOK_LIMIT)
            ->get(['textbook_chapters.id', 'textbook_chapters.title', 'textbook_chapters.slug', 'textbook_chapters.summary']);
        $chapters = $linked->isNotEmpty() ? $linked : TextbookChapter::published()
            ->where('exam_id', $question->exam_id)->where('category_id', $question->category_id)
            ->orderBy('sort_order')->orderBy('id')->limit(self::TEXTBOOK_LIMIT)->get(['id', 'title', 'slug', 'summary']);

        return [
            'items' => $chapters->map(fn (TextbookChapter $chapter): array => [
                'title' => $chapter->title,
                'summary' => $chapter->summary,
                'url' => '/textbooks/'.$chapter->slug,
            ])->values()->all(),
            'linked' => $linked->isNotEmpty(),
            'subject' => $question->category?->name,
        ];
    }

    /** @return array{items: list<array{id: int, title: string, diagnosis: string|null, image_url: string, url: string, credit: string|null}>, linked: bool} */
    private function atlas(Question $question, ?string $term): array
    {
        $qbankId = Exam::whereKey($question->exam_id)->value('qbank_id');
        // The same images the Image Atlas shows: shared ones and those of this Qbank.
        $visible = fn (Builder $query) => $query->whereNull('atlas_images.qbank_id')
            ->when($qbankId !== null, fn (Builder $query) => $query->orWhere('atlas_images.qbank_id', $qbankId));
        $columns = ['atlas_images.id', 'atlas_images.title', 'atlas_images.diagnosis', 'atlas_images.image_path', 'atlas_images.source_name', 'atlas_images.attribution'];

        $linked = $question->atlasImages()->published()->where($visible)->limit(self::ATLAS_LIMIT)->get($columns);
        $images = $linked;
        if ($linked->isEmpty() && $term !== null) {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
            $images = AtlasImage::published()->where($visible)
                ->where(fn (Builder $query) => $query->whereRaw("title LIKE ? ESCAPE '!'", [$like])->orWhereRaw("diagnosis LIKE ? ESCAPE '!'", [$like]))
                ->orderBy('title')->orderBy('id')->limit(self::ATLAS_LIMIT)->get($columns);
        }

        return [
            'items' => $images->map(fn (AtlasImage $image): array => [
                'id' => $image->id,
                'title' => $image->title,
                'diagnosis' => $image->diagnosis,
                'image_url' => $image->url(),
                'url' => '/image-atlas?q='.rawurlencode($image->title),
                'credit' => $image->attribution ?: $image->source_name,
            ])->values()->all(),
            'linked' => $linked->isNotEmpty(),
        ];
    }
}
