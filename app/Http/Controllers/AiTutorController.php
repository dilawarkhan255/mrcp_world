<?php

namespace App\Http\Controllers;

use App\Models\AtlasImage;
use App\Models\Flashcard;
use App\Models\PracticeSession;
use App\Models\TextbookChapter;
use App\Models\User;
use App\Services\AiTutor;
use App\Services\AiTutorException;
use App\Services\StudyPlanService;
use App\Support\CurrentExam;
use App\Support\QuestionContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
        // Optional: the student's own question, exam date and daily study time.
        $data = $request->validate([
            'question' => ['nullable', 'string', 'min:3', 'max:400'],
            'exam_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today', 'before:'.now()->addYears(2)->toDateString()],
            'hours' => ['nullable', 'numeric', 'min:0.5', 'max:16'],
        ]);

        return $this->respond($tutor, $user, fn (): string => $tutor->studyPlanAdvice(
            $user,
            CurrentExam::selected($request)->name,
            $plans->build($user->id, $exam->id),
            isset($data['question']) ? trim((string) $data['question']) : null,
            $data['exam_date'] ?? null,
            isset($data['hours']) ? round((float) $data['hours'], 1) : null,
        ));
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

    /** A question about a published textbook chapter of the selected exam. */
    public function chapter(Request $request, string $slug, AiTutor $tutor): JsonResponse
    {
        abort_unless($tutor->enabled(), 404);
        /** @var User $user */
        $user = $request->user();
        $chapter = TextbookChapter::published()->where('exam_id', CurrentExam::content($request)->id)->where('slug', $slug)->firstOrFail();
        $question = $this->question($request);

        return $this->respond($tutor, $user, fn (): string => $tutor->askAboutChapter($user, $chapter, $question));
    }

    /** A question about a published flashcard of the selected exam; the back is sent only once the card is turned. */
    public function flashcard(Request $request, Flashcard $flashcard, AiTutor $tutor): JsonResponse
    {
        abort_unless($tutor->enabled(), 404);
        abort_unless($flashcard->is_published && $flashcard->exam_id === CurrentExam::content($request)->id, 404);
        /** @var User $user */
        $user = $request->user();
        $question = $this->question($request);
        $revealed = $request->boolean('revealed');

        return $this->respond($tutor, $user, fn (): string => $tutor->askAboutFlashcard($user, $flashcard, $revealed, $question));
    }

    /** A question about a published Image Atlas picture the student may see (shared, or in the selected Qbank). */
    public function atlas(Request $request, AtlasImage $image, AiTutor $tutor): JsonResponse
    {
        abort_unless($tutor->enabled(), 404);
        $qbankId = CurrentExam::selected($request)->qbank_id;
        abort_unless(
            AtlasImage::published()->whereKey($image->id)->where(fn (Builder $query) => $query->whereNull('qbank_id')->orWhere('qbank_id', $qbankId))->exists(),
            404,
        );
        /** @var User $user */
        $user = $request->user();
        $question = $this->question($request);

        return $this->respond($tutor, $user, fn (): string => $tutor->askAboutAtlasImage($user, $image, $question));
    }

    /** The student's own question (3 to 400 characters). */
    private function question(Request $request): string
    {
        return trim((string) $request->validate(['question' => ['required', 'string', 'min:3', 'max:400']])['question']);
    }

    /** The Knowledge Tutor page: a conversation about any syllabus topic. */
    public function tutor(Request $request, AiTutor $tutor): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('tutor/Index', [
            'exam' => CurrentExam::props($request),
            'enabled' => $tutor->enabled(),
            'remaining' => $tutor->enabled() ? $tutor->remaining($user) : 0,
            'historyLimit' => AiTutor::TUTOR_HISTORY,
        ]);
    }

    public function tutorChat(Request $request, AiTutor $tutor): JsonResponse
    {
        abort_unless($tutor->enabled(), 404);
        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:2000'],
            'history' => ['nullable', 'array', 'max:'.(AiTutor::TUTOR_HISTORY * 2)],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:6000'],
        ]);
        /** @var User $user */
        $user = $request->user();
        /** @var list<array{role: string, content: string}> $history */
        $history = array_values($data['history'] ?? []);

        try {
            $text = $tutor->tutorChat($user, CurrentExam::selected($request)->name, $history, (string) $data['message']);
        } catch (AiTutorException $exception) {
            return response()->json(['error' => $exception->getMessage(), 'remaining' => $tutor->remaining($user)], 422);
        }

        // The Markdown goes back too: the browser sends it as history with the next question.
        return response()->json(['text' => $text, 'html' => QuestionContent::html($text), 'remaining' => $tutor->remaining($user)]);
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
