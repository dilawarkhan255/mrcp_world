<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Services\PerformanceService;
use App\Support\CurrentExam;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceController extends Controller
{
    private function exam(Request $request): Exam
    {
        return CurrentExam::content($request);
    }

    public function index(Request $request, PerformanceService $performance): Response
    {
        $exam = $this->exam($request);
        $userId = (int) $request->user()?->id;

        return Inertia::render('questions/Performance', [
            'exam' => CurrentExam::props($request),
            'summary' => $performance->summary($userId, $exam->id),
            'trend' => $performance->dailyTrend($userId, $exam->id),
            'categories' => $performance->categoryBreakdown($userId, $exam->id),
            'weakest' => $performance->weakestQuestions($userId, $exam->id),
            'streak' => $performance->streak($userId, $exam->id),
        ]);
    }
}
