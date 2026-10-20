<?php

namespace App\Http\Middleware;

use App\Models\Exam;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /** @var string */
    protected $rootView = 'app';

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $exams = $request->user() ? Exam::where('is_active', true)->orderBy('id')->get(['id', 'name', 'slug']) : collect();
        $current = $exams->firstWhere('id', $request->session()->get('exam_id')) ?? $exams->first();
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => ['user' => $request->user()],
            'exams' => $exams,
            'currentExam' => $current,
            'navigation' => $request->user() ? config('student.groups') : [],
        ];
    }
}
