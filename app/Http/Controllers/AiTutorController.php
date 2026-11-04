<?php

namespace App\Http\Controllers;

use App\Models\PracticeSession;
use App\Models\User;
use App\Services\AiTutor;
use App\Services\AiTutorException;
use App\Services\StudyPlanService;
use App\Support\CurrentExam;
use App\Support\QuestionContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoints of the AI tutor. Both need a subscription (route middleware)
 * and the API key. Questions about an MCQ are refused in an open mock; before
 * the answer is revealed, Claude is never given the answer key.
 */
class AiTutorController extends Controller
{
    public function studyPlan(Request $request, AiTutor $tutor, StudyPlanService $plans): JsonResponse
    {
        abort_unless($tutor->enabled(), 404);
        /** @var User $user */
        $user = $request->user();
        $exam = CurrentExam::content($request);

        return $this->respond($tutor, $user, fn (): string => $tutor->studyPlanAdvice($user, CurrentExam::selected($request)->name, $plans->build($user->id, $exam->id)));
    }

    public function ask(Request $request, PracticeSession $session, int $position, AiTutor $tutor): JsonResponse
    {
        abort_unless($tutor->enabled(), 404);
        /** @var User $user */
        $user = $request->user();
        abort_unless($session->user_id === $user->id, 403);
        // A mock is an exam: no AI help until it is closed.
        abort_if($session->mode === PracticeSession::MODE_MOCK && $session->completed_at === null, 403);
        $data = $request->validate(['question' => ['required', 'string', 'min:3', 'max:400']]);
        $item = $session->items()->where('position', $position)->firstOrFail();
        abort_if($item->question_id === null, 404);
        $revealed = $item->answered_at !== null;

        return $this->respond($tutor, $user, fn (): string => $tutor->askAboutQuestion(
            $user, (int) $item->question_id, $item->snapshot, $revealed, $item->selected_option, (string) $data['question'],
        ));
    }

    /** @param callable(): string $ask */
    private function respond(AiTutor $tutor, User $user, callable $ask): JsonResponse
    {
        try {
            $text = $ask();
        } catch (AiTutorException $exception) {
            return response()->json(['error' => $exception->getMessage(), 'remaining' => $tutor->remaining($user)], 422);
        }

        // The answer is Markdown; it is shown with raw HTML stripped, like every other rich text.
        return response()->json(['html' => QuestionContent::html($text), 'remaining' => $tutor->remaining($user)]);
    }
}
