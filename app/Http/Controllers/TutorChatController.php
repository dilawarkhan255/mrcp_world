<?php

namespace App\Http\Controllers;

use App\Models\TutorConversation;
use App\Models\TutorMessage;
use App\Models\User;
use App\Services\AiTutor;
use App\Services\AiTutorException;
use App\Support\CurrentExam;
use App\Support\QuestionContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The AI tutor: conversations with Claude about any syllabus topic, saved
 * for the student (list on the left, open, rename, delete). The earlier
 * turns are read from the database, never trusted from the browser.
 */
class TutorChatController extends Controller
{
    /** Conversations listed on the left. */
    private const LISTED = 50;

    public function index(Request $request, AiTutor $tutor, ?TutorConversation $conversation = null): Response
    {
        /** @var User $user */
        $user = $request->user();
        if ($conversation !== null) {
            $this->authorizeOwner($request, $conversation);
        }

        return Inertia::render('tutor/Ai', [
            'exam' => CurrentExam::props($request),
            'enabled' => $tutor->enabled(),
            'remaining' => $tutor->enabled() ? $tutor->remaining($user) : 0,
            'conversations' => TutorConversation::where('user_id', $user->id)->latest('updated_at')->latest('id')->limit(self::LISTED)
                ->get(['id', 'title', 'updated_at'])
                ->map(fn (TutorConversation $item): array => ['id' => $item->id, 'title' => $item->title, 'updated' => $item->updated_at?->diffForHumans()])->all(),
            'conversation' => $conversation === null ? null : [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'messages' => $conversation->messages()->get(['role', 'content'])
                    ->map(fn (TutorMessage $message): array => $this->turn($message->role, $message->content))->all(),
            ],
        ]);
    }

    public function send(Request $request, AiTutor $tutor): JsonResponse
    {
        abort_unless($tutor->enabled(), 404);
        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $conversation = null;
        if (! empty($data['conversation_id'])) {
            $conversation = TutorConversation::findOrFail((int) $data['conversation_id']);
            $this->authorizeOwner($request, $conversation);
        }
        $history = $conversation === null ? [] : $conversation->messages()->reorder()->latest('id')->limit(AiTutor::TUTOR_HISTORY * 2)
            ->get(['role', 'content'])->reverse()
            ->map(fn (TutorMessage $message): array => ['role' => $message->role, 'content' => $message->content])->values()->all();
        $message = trim((string) $data['message']);

        try {
            $text = $tutor->tutorChat($user, CurrentExam::selected($request)->name, $history, $message);
        } catch (AiTutorException $exception) {
            return response()->json(['error' => $exception->getMessage(), 'remaining' => $tutor->remaining($user)], 422);
        }

        // Saved only once Claude has answered, so a failed request leaves no half conversation.
        $conversation = DB::transaction(function () use ($conversation, $user, $request, $message, $text): TutorConversation {
            $conversation ??= TutorConversation::create([
                'user_id' => $user->id, 'exam_id' => CurrentExam::selected($request)->id, 'title' => Str::limit($message, 80),
            ]);
            $conversation->messages()->createMany([['role' => 'user', 'content' => $message], ['role' => 'assistant', 'content' => $text]]);
            $conversation->touch();

            return $conversation;
        });

        return response()->json([
            'conversation' => ['id' => $conversation->id, 'title' => $conversation->title, 'updated' => $conversation->updated_at?->diffForHumans()],
            ...$this->turn('assistant', $text),
            'remaining' => $tutor->remaining($user),
        ]);
    }

    public function rename(Request $request, TutorConversation $conversation): RedirectResponse
    {
        $this->authorizeOwner($request, $conversation);
        $data = $request->validate(['title' => ['required', 'string', 'min:1', 'max:80']]);
        // A rename is not new activity: the list order stays.
        $conversation->timestamps = false;
        $conversation->update(['title' => trim((string) $data['title'])]);

        return back();
    }

    public function destroy(Request $request, TutorConversation $conversation): RedirectResponse
    {
        $this->authorizeOwner($request, $conversation);
        $conversation->delete();

        return redirect()->route('tutor.ai');
    }

    /** @return array{role: string, content: string, html: string|null} */
    private function turn(string $role, string $content): array
    {
        // The tutor writes Markdown, shown with raw HTML stripped like every other rich text.
        return ['role' => $role, 'content' => $content, 'html' => $role === 'assistant' ? QuestionContent::html($content) : null];
    }

    private function authorizeOwner(Request $request, TutorConversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()?->id, 404);
    }
}
