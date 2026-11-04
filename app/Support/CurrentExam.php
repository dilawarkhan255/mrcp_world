<?php

namespace App\Support;

use App\Models\Exam;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * The exam a student has chosen in the sidebar, and the exam whose content is
 * shown for it. They differ for an exam that shares another exam's question
 * bank (IMM reads MRCP 1). Sessions, bookmarks, reviews and
 * performance are stored against the content exam, so switching between two
 * exams of one Qbank keeps the same history.
 *
 * Resolved once per request.
 */
final class CurrentExam
{
    private const ATTRIBUTE = '_current_exam';

    private const ACCESS = '_current_exam_access';

    /** The exam chosen in the sidebar, or the first active exam. */
    public static function selected(Request $request): Exam
    {
        return self::resolve($request)['selected'] ?? throw (new ModelNotFoundException)->setModel(Exam::class);
    }

    /** The exam that owns the subjects, papers and questions for the selection. */
    public static function content(Request $request): Exam
    {
        return self::resolve($request)['content'] ?? throw (new ModelNotFoundException)->setModel(Exam::class);
    }

    /** The content exam id, or null when no exam is active yet. */
    public static function contentId(Request $request): ?int
    {
        return self::resolve($request)['content']?->id;
    }

    /** Whether the signed-in user may open the full content of the selected exam's Qbank. */
    public static function hasAccess(Request $request): bool
    {
        // Cached on the request, so the layout, the sidebar and the page share one query.
        if (! $request->attributes->has(self::ACCESS)) {
            $selected = self::resolve($request)['selected'];
            $request->attributes->set(self::ACCESS, $selected !== null && app(SubscriptionService::class)->hasAccess($request->user(), $selected->qbank_id));
        }

        return (bool) $request->attributes->get(self::ACCESS);
    }

    /**
     * Page props: the content exam id with the name the student chose.
     *
     * @return array{id: int, name: string}
     */
    public static function props(Request $request): array
    {
        return ['id' => self::content($request)->id, 'name' => self::selected($request)->name];
    }

    /** @return array{selected: Exam|null, content: Exam|null} */
    private static function resolve(Request $request): array
    {
        /** @var array{selected: Exam|null, content: Exam|null}|null $cached */
        $cached = $request->attributes->get(self::ATTRIBUTE);
        if ($cached !== null) {
            return $cached;
        }
        $selected = Exam::where('is_active', true)->find($request->session()->get('exam_id'))
            ?? Exam::where('is_active', true)->orderBy('sort_order')->orderBy('id')->first();
        $content = $selected?->sharesContent() ? (Exam::find($selected->content_exam_id) ?? $selected) : $selected;
        $resolved = ['selected' => $selected, 'content' => $content];
        $request->attributes->set(self::ATTRIBUTE, $resolved);

        return $resolved;
    }
}
