<?php

namespace App\Http\Controllers;

use App\Models\AtlasImage;
use App\Support\CurrentExam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Image Atlas for students: published images with a confirmed copyright that
 * belong to every Qbank or to the Qbank of the selected exam.
 */
class AtlasController extends Controller
{
    public const PAGE_SIZE = 24;

    public function index(Request $request): Response
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'subject' => ['nullable', 'string', 'max:100'],
        ]);
        $term = trim((string) ($data['q'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        $qbankId = CurrentExam::selected($request)->qbank_id;

        $base = AtlasImage::published()->where(fn (Builder $query) => $query->whereNull('qbank_id')
            ->when($qbankId !== null, fn (Builder $query) => $query->orWhere('qbank_id', $qbankId)));

        $images = (clone $base)
            ->when($subject !== '', fn (Builder $query) => $query->where('subject', $subject))
            ->when($term !== '', function (Builder $query) use ($term) {
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
                $query->where(fn (Builder $query) => $query->whereRaw("title LIKE ? ESCAPE '!'", [$like])->orWhereRaw("diagnosis LIKE ? ESCAPE '!'", [$like]));
            })
            ->orderBy('subject')->orderBy('title')->orderBy('id')
            ->simplePaginate(self::PAGE_SIZE, ['id', 'title', 'diagnosis', 'subject', 'system', 'description', 'image_path', 'source_name', 'source_url', 'license', 'attribution'])
            ->withQueryString();

        return Inertia::render('atlas/Index', [
            'exam' => CurrentExam::props($request),
            'filters' => ['q' => $term, 'subject' => $subject],
            'subjects' => (clone $base)->whereNotNull('subject')->distinct()->orderBy('subject')->pluck('subject')->all(),
            'images' => [
                'data' => collect($images->items())->map(fn (AtlasImage $image): array => [
                    'id' => $image->id,
                    'title' => $image->title,
                    'diagnosis' => $image->diagnosis,
                    'subject' => $image->subject,
                    'system' => $image->system,
                    'description' => $image->description,
                    'url' => $image->url(),
                    'credit' => $image->attribution ?: $image->source_name,
                    'source_url' => $image->source_url,
                    'license' => AtlasImage::LICENSES[$image->license] ?? $image->license,
                ])->all(),
                'current_page' => $images->currentPage(),
                'prev_page_url' => $images->previousPageUrl(),
                'next_page_url' => $images->nextPageUrl(),
            ],
        ]);
    }
}
