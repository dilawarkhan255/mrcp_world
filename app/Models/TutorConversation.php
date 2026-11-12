<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A saved conversation with the AI tutor. Only its owner can open it. */
class TutorConversation extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'exam_id', 'title'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TutorMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(TutorMessage::class)->orderBy('id');
    }
}
