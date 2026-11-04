<?php

namespace App\Http\Controllers;

use App\Services\StudyPlanService;
use App\Support\CurrentExam;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudyPlanController extends Controller
{
    public function index(Request $request, StudyPlanService $plans): Response
    {
        return Inertia::render('StudyPlan', [
            'exam' => CurrentExam::props($request),
            'plan' => $plans->build((int) $request->user()?->id, CurrentExam::content($request)->id),
        ]);
    }
}
