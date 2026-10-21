<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionComment extends Model
{
    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_hidden' => 'boolean', 'is_pinned' => 'boolean'];
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<QuestionComment, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(QuestionComment::class, 'parent_id');
    }

    /** @return HasMany<QuestionComment, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(QuestionComment::class, 'parent_id');
    }

    /**
     * Hidden comments stay visible to moderators and to their author.
     *
     * @param  Builder<QuestionComment>  $query
     */
    public function scopeVisibleTo(Builder $query, ?User $user): void
    {
        if ($user !== null && $user->hasAnyRole(['editor', 'admin'])) {
            return;
        }
        $query->where(function (Builder $query) use ($user): void {
            $query->where('is_hidden', false);
            if ($user !== null) {
                $query->orWhere('user_id', $user->id);
            }
        });
    }
}
