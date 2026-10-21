<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CommentController extends Controller
{
    private function exam(Request $request): Exam
    {
        return Exam::where('is_active', true)->find($request->session()->get('exam_id')) ?? Exam::where('is_active', true)->orderBy('id')->firstOrFail();
    }

    public function index(Request $request): Response
    {
        $exam = $this->exam($request);
        $user = $request->user();
        $search = trim((string) $request->input('q', ''));

        $threads = QuestionComment::query()
            ->whereNull('parent_id')
            ->whereHas('question', fn (Builder $query) => $query->where('questions.exam_id', $exam->id))
            ->visibleTo($user)
            ->with([
                'user:id,name',
                'user.roles',
                'question:id,exam_id,code,body',
                'replies' => fn (HasMany $relation) => $relation->getQuery()->visibleTo($user)->with('user:id,name', 'user.roles')->orderBy('id'),
            ])
            ->orderByDesc('is_pinned')
            ->orderByDesc('id')
            ->simplePaginate(15)
            ->withQueryString();

        $questions = $search === '' ? collect() : Question::published()
            ->where('exam_id', $exam->id)
            ->where(fn (Builder $query) => $query->where('code', 'like', $search.'%')->orWhere('body', 'like', '%'.$search.'%'))
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'code', 'body']);

        return Inertia::render('questions/Comments', [
            'exam' => $exam->only(['id', 'name']),
            'threads' => [
                'data' => $threads->getCollection()->map(fn (QuestionComment $comment): array => $this->payload($comment, $user))->all(),
                'current_page' => $threads->currentPage(),
                'prev_page_url' => $threads->previousPageUrl(),
                'next_page_url' => $threads->nextPageUrl(),
            ],
            'questions' => $questions->map(fn (Question $question): array => [
                'id' => $question->id,
                'code' => $question->code,
                'body' => $question->body,
            ])->all(),
            'search' => $search,
            'canModerate' => $user !== null && $user->can('moderate', QuestionComment::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $exam = $this->exam($request);
        $data = $request->validate([
            'question_id' => ['required', 'integer', Rule::exists('questions', 'id')
                ->where('exam_id', $exam->id)
                ->where('is_published', true)
                ->whereNull('deleted_at')],
            'body' => ['required', 'string', 'min:3', 'max:4000'],
        ]);
        $question = Question::published()->where('exam_id', $exam->id)->whereKey($data['question_id'])->firstOrFail();
        QuestionComment::create([
            'question_id' => $question->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return back();
    }

    public function reply(Request $request, QuestionComment $comment): RedirectResponse
    {
        Gate::authorize('create', QuestionComment::class);
        abort_unless($comment->parent_id === null, 422);
        $data = $request->validate(['body' => ['required', 'string', 'min:3', 'max:4000']]);
        QuestionComment::create([
            'question_id' => $comment->question_id,
            'user_id' => $request->user()->id,
            'parent_id' => $comment->id,
            'body' => $data['body'],
        ]);

        return back();
    }

    public function moderate(Request $request, QuestionComment $comment): RedirectResponse
    {
        Gate::authorize('moderate', QuestionComment::class);
        $data = $request->validate(['action' => ['required', Rule::in(['hide', 'unhide', 'pin', 'unpin'])]]);
        match ($data['action']) {
            'hide' => $comment->update(['is_hidden' => true]),
            'unhide' => $comment->update(['is_hidden' => false]),
            'pin' => $comment->update(['is_pinned' => true]),
            'unpin' => $comment->update(['is_pinned' => false]),
            default => throw ValidationException::withMessages(['action' => 'Choose hide, unhide, pin or unpin.']),
        };

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(QuestionComment $comment, ?User $user): array
    {
        $author = $comment->user;

        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'created_at' => $comment->created_at?->toIso8601String(),
            'is_hidden' => $comment->is_hidden,
            'is_pinned' => $comment->is_pinned,
            'author' => $author->name,
            'author_role' => $author->hasRole('admin') ? 'Admin' : ($author->hasRole('editor') ? 'Editor' : 'Student'),
            'is_mine' => $user !== null && $comment->user_id === $user->id,
            'question' => $comment->question === null ? null : [
                'id' => $comment->question->id,
                'code' => $comment->question->code,
                'body' => $comment->question->body,
            ],
            'replies' => $comment->replies->map(fn (QuestionComment $reply): array => $this->payload($reply, $user))->all(),
        ];
    }
}
