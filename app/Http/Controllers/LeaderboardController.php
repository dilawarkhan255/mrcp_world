<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LeaderboardService;
use App\Support\CurrentExam;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    public function index(Request $request, LeaderboardService $leaderboard): Response
    {
        $data = $request->validate(['period' => ['nullable', Rule::in([LeaderboardService::WEEKLY, LeaderboardService::ALL_TIME])]]);
        $period = $data['period'] ?? LeaderboardService::WEEKLY;
        /** @var User $user */
        $user = $request->user();
        $optedIn = $user->leaderboard_opt_in && $user->leaderboard_name !== null;
        $board = $leaderboard->board(CurrentExam::content($request)->id, $period, $optedIn ? $user->id : null);
        // Only the rank and display name are shared; user ids stay on the server.
        $public = fn (array $entry): array => ['rank' => $entry['rank'], 'name' => $entry['name'], 'correct' => $entry['correct'], 'answered' => $entry['answered'], 'accuracy' => $entry['accuracy'], 'is_me' => $entry['user_id'] === $user->id];

        return Inertia::render('Leaderboard', [
            'exam' => CurrentExam::props($request),
            'period' => $period,
            'entries' => array_map($public, $board['entries']),
            'me' => $board['me'] === null ? null : $public($board['me']),
            'qualified' => $board['qualified'],
            'optedIn' => $optedIn,
            'minAnswers' => (int) config('student.leaderboard.min_answers', 20),
            'weekStart' => $leaderboard->weekStart()->toIso8601String(),
            'refreshMinutes' => (int) config('student.leaderboard.cache_minutes', 10),
        ]);
    }
}
