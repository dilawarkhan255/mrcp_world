<?php

namespace App\Http\Controllers;

use App\Models\PracticeSession;
use App\Support\CurrentExam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Server-side rows for the Yajra data tables of the student app
 * (resources/js/components/YajraTable.vue). Every query is limited to the
 * signed-in student and the selected exam's question bank.
 */
class StudentTableController extends Controller
{
    /** Every session of the student: practice, review and mock. */
    public function sessions(Request $request): JsonResponse
    {
        return $this->table($request, fn (Builder $query) => $query);
    }

    /** Mock exam attempts. */
    public function mocks(Request $request): JsonResponse
    {
        return $this->table($request, fn (Builder $query) => $query->where('mode', PracticeSession::MODE_MOCK));
    }

    /** @param callable(Builder<PracticeSession>): mixed $scope */
    private function table(Request $request, callable $scope): JsonResponse
    {
        $query = PracticeSession::query()
            ->where('user_id', (int) $request->user()?->id)
            ->where('exam_id', CurrentExam::content($request)->id)
            ->select(['id', 'mode', 'total_questions', 'answered_count', 'correct_count', 'completed_at', 'created_at', 'started_at', 'duration_seconds']);
        $scope($query);

        return DataTables::eloquent($query)
            ->addColumn('accuracy', fn (PracticeSession $session): ?int => $session->answered_count > 0 ? (int) round($session->correct_count / $session->answered_count * 100) : null)
            ->addColumn('score', fn (PracticeSession $session): int => $session->total_questions > 0 ? (int) round($session->correct_count / $session->total_questions * 100) : 0)
            ->addColumn('status', fn (PracticeSession $session): string => $session->completed_at !== null ? 'Completed' : 'In progress')
            ->addColumn('kind', fn (PracticeSession $session): string => match ($session->mode) {
                PracticeSession::MODE_REVIEW => 'Review', PracticeSession::MODE_MOCK => 'Mock', default => 'Practice',
            })
            // Minutes used so far (mocks), capped at the time allowed.
            ->addColumn('minutes', function (PracticeSession $session): ?int {
                if ($session->started_at === null) {
                    return null;
                }
                $seconds = max(0, (int) $session->started_at->diffInSeconds($session->completed_at ?? now()));
                if ($session->duration_seconds !== null) {
                    $seconds = min($seconds, (int) $session->duration_seconds);
                }

                return intdiv($seconds, 60);
            })
            ->editColumn('created_at', fn (PracticeSession $session): ?string => $session->created_at?->toIso8601String())
            ->editColumn('completed_at', fn (PracticeSession $session): ?string => $session->completed_at?->toIso8601String())
            // Search by session number only; the rest are numbers and dates.
            ->filter(function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('search.value', ''));
                if ($term !== '' && ctype_digit(ltrim($term, '#'))) {
                    $query->whereKey((int) ltrim($term, '#'));
                }
            })
            ->orderColumns(['id', 'created_at', 'total_questions', 'answered_count', 'correct_count'], ':column $1')
            ->only(['id', 'kind', 'total_questions', 'answered_count', 'correct_count', 'accuracy', 'score', 'status', 'minutes', 'created_at', 'completed_at'])
            ->toJson();
    }
}
