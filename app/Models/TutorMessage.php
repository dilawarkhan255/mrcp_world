<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One turn of an AI tutor conversation: the student's question or the tutor's answer (Markdown). */
class TutorMessage extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['tutor_conversation_id', 'role', 'content'];

    /** @return BelongsTo<TutorConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(TutorConversation::class, 'tutor_conversation_id');
    }
}
