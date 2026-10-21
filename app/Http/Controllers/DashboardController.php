<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\PracticeSession;
use App\Services\PerformanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PerformanceService $performance): Response
    {
        $examId = Exam::where('is_active', true)->whereKey($request->session()->get('exam_id'))->value('id') ?? Exam::where('is_active', true)->orderBy('id')->value('id');
        $userId = (int) $request->user()?->id;
        $aggregate = PracticeSession::where('user_id', $userId)->where('exam_id', $examId)
            ->selectRaw('COALESCE(SUM(answered_count), 0) AS answered, COALESCE(SUM(correct_count), 0) AS correct, COUNT(*) AS sessions, COALESCE(SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS completed')->toBase()->first();

        return Inertia::render('Dashboard', ['stats' => [
            'answered' => (int) ($aggregate->answered ?? 0),
            'correct' => (int) ($aggregate->correct ?? 0),
            'sessions' => (int) ($aggregate->sessions ?? 0),
            'completed' => (int) ($aggregate->completed ?? 0),
        ], 'trend' => $performance->dailyTrend($userId, (int) $examId)]);
    }
}
