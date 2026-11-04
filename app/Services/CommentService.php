<?php

namespace App\Services;

use App\Models\QuestionComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Comment threads shared by the Comments page and the practice screen.
 * Visibility is applied in the query: a hidden thread or reply is only
 * returned to its author and to moderators.
 */
class CommentService
{
    /** Threads shown under one question on the practice screen. */
    public const QUESTION_LIMIT = 20;

    /** @return Builder<QuestionComment> */
    public function threads(?User $user): Builder
    {
        return QuestionComment::query()
            ->whereNull('parent_id')
            ->visibleTo($user)
            ->with([
                'user:id,name',
                'user.roles',
                'question:id,exam_id,code,body',
                'replies' => fn (HasMany $relation) => $relation->getQuery()->visibleTo($user)->with('user:id,name', 'user.roles')->orderBy('id'),
            ])
            ->orderByDesc('is_pinned')
            ->orderByDesc('id');
    }

    /**
     * The visible threads of one question, newest and pinned first.
     *
     * @return list<array<string, mixed>>
     */
    public function forQuestion(int $questionId, ?User $user): array
    {
        return $this->threads($user)
            ->where('question_id', $questionId)
            ->limit(self::QUESTION_LIMIT)
            ->get()
            ->map(fn (QuestionComment $comment): array => $this->payload($comment, $user))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(QuestionComment $comment, ?User $user): array
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
