<?php

namespace App\Http\Middleware;

use App\Models\Exam;
use App\Support\CurrentExam;
use App\Support\StudentNavigation;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /** @var string */
    protected $rootView = 'app';

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $exams = $request->user()
            ? Exam::where('exams.is_active', true)
                ->leftJoin('qbanks', 'qbanks.id', '=', 'exams.qbank_id')
                ->orderByRaw('CASE WHEN qbanks.sort_order IS NULL THEN 1 ELSE 0 END')
                ->orderBy('qbanks.sort_order')->orderBy('exams.sort_order')->orderBy('exams.id')
                ->get(['exams.id', 'exams.name', 'exams.slug', 'qbanks.name as qbank'])
            : collect();
        $current = null;
        $content = null;
        $access = null;
        if ($exams->isNotEmpty()) {
            $current = CurrentExam::selected($request);
            $content = CurrentExam::content($request);
            $access = CurrentExam::hasAccess($request);
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'canManageContent' => $request->user()?->isStaff() ?? false,
            ],
            'exams' => $exams,
            'currentExam' => $current?->only(['id', 'name', 'slug']),
            'navigation' => fn () => $request->user() ? StudentNavigation::for($content, $access === false) : [],
            'access' => fn () => $current === null ? null : [
                'has_access' => (bool) $access,
                'qbank' => $current->qbank?->name,
            ],
            'flash' => ['status' => fn () => $request->session()->get('status')],
            'protection' => fn () => $this->protection($request),
            'links' => [
                'whatsapp' => config('services.community.whatsapp_url') ?: null,
                'play_store' => config('services.community.play_store_url') ?: null,
            ],
        ];
    }

    /**
     * Copy protection for students and guests; content staff are exempt.
     *
     * @return array{enabled: bool, watermark: string|null}
     */
    private function protection(Request $request): array
    {
        $user = $request->user();
        $enabled = (bool) config('student.protection.enabled') && ! ($user?->can('content.questions.view') ?? false);

        return [
            'enabled' => $enabled,
            'watermark' => $enabled && $user !== null && config('student.protection.watermark') ? $user->email.' · '.now()->toDateString() : null,
        ];
    }
}
