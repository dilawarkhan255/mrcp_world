<?php

namespace App\Http\Controllers;

use App\Models\AtlasImage;
use App\Models\Category;
use App\Models\Question;
use App\Models\TextbookChapter;
use App\Services\QuestionSearchService;
use App\Support\CurrentExam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The search box in the top bar: a few results per kind while the student
 * types. Pressing Enter opens the full MCQ search page. Content a student
 * cannot open without a subscription is not searched; the response says so.
 */
class QuickSearchController extends Controller
{
    private const PER_KIND = 5;

    public function __invoke(Request $request, QuestionSearchService $search): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:'.QuestionSearchService::MIN_LENGTH, 'max:'.QuestionSearchService::MAX_LENGTH]]);
        $term = trim((string) $data['q']);
        $exam = CurrentExam::content($request);
        $access = CurrentExam::hasAccess($request);
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        $pages = collect($this->pages())
            ->filter(fn (array $page): bool => Str::contains(Str::lower($page['title']), Str::lower($term)))
            ->take(self::PER_KIND)->values()->all();
        $subjects = Category::where('exam_id', $exam->id)->whereRaw("name LIKE ? ESCAPE '!'", [$like])
            ->orderBy('name')->limit(self::PER_KIND)->get(['name', 'slug'])
            ->map(fn (Category $category): array => ['title' => $category->name, 'href' => '/questions/subjects/'.$category->slug])->all();

        $questions = $chapters = $images = [];
        if ($access) {
            $questions = $search->matches($exam, $term)->limit(self::PER_KIND)->get(['id', 'code', 'body', 'category_id'])
                ->map(fn (Question $question): array => [
                    'title' => $question->code,
                    'excerpt' => Str::limit(self::plain($question->body), 110),
                    'subject' => $question->category?->name,
                    'href' => '/questions/search?q='.rawurlencode($question->code),
                ])->all();
            $chapters = TextbookChapter::published()->where('exam_id', $exam->id)->whereRaw("title LIKE ? ESCAPE '!'", [$like])
                ->orderBy('title')->limit(self::PER_KIND)->get(['title', 'slug'])
                ->map(fn (TextbookChapter $chapter): array => ['title' => $chapter->title, 'href' => '/textbooks/'.$chapter->slug])->all();
            $qbankId = CurrentExam::selected($request)->qbank_id;
            $images = AtlasImage::published()
                ->where(fn (Builder $query) => $query->whereNull('qbank_id')->when($qbankId !== null, fn (Builder $query) => $query->orWhere('qbank_id', $qbankId)))
                ->where(fn (Builder $query) => $query->whereRaw("title LIKE ? ESCAPE '!'", [$like])->orWhereRaw("diagnosis LIKE ? ESCAPE '!'", [$like]))
                ->orderBy('title')->limit(self::PER_KIND)->get(['title'])
                ->map(fn (AtlasImage $image): array => ['title' => $image->title, 'href' => '/image-atlas?q='.rawurlencode($image->title)])->all();
        }

        return response()->json([
            'term' => $term,
            'locked' => ! $access,
            'searchUrl' => '/questions/search?q='.rawurlencode($term),
            'groups' => array_values(array_filter([
                ['label' => 'Questions', 'icon' => 'ListChecks', 'items' => $questions],
                ['label' => 'Subjects', 'icon' => 'Folder', 'items' => $subjects],
                ['label' => 'Textbook', 'icon' => 'BookOpen', 'items' => $chapters],
                ['label' => 'Image Atlas', 'icon' => 'Images', 'items' => $images],
                ['label' => 'Pages', 'icon' => 'LayoutGrid', 'items' => $pages],
            ], fn (array $group): bool => $group['items'] !== [])),
        ]);
    }

    /** Markdown to one line of plain text: formatting marks removed, whitespace collapsed. */
    private static function plain(string $markdown): string
    {
        $text = (string) preg_replace('/[*_#>|`\[\]()!]+/', '', $markdown);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /** @return list<array{title: string, href: string}> */
    private function pages(): array
    {
        $pages = [];
        $collect = function (array $items) use (&$collect, &$pages): void {
            foreach ($items as $item) {
                if (! empty($item['href']) && ! empty($item['title'])) {
                    $pages[] = ['title' => (string) $item['title'], 'href' => (string) $item['href']];
                }
                $collect($item['children'] ?? []);
            }
        };
        $collect((array) config('student.navigation'));

        return $pages;
    }
}
