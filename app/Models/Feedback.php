<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    /** The table is named in the singular, unlike Laravel's default plural. */
    protected $table = 'feedback';

    public const TYPE_SUGGESTION = 'suggestion';

    public const TYPE_CONTENT = 'content';

    public const TYPE_BUG = 'bug';

    public const TYPE_OTHER = 'other';

    /** @var array<string, string> type => label */
    public const TYPES = [
        self::TYPE_SUGGESTION => 'Suggestion',
        self::TYPE_CONTENT => 'MCQ or explanation error',
        self::TYPE_BUG => 'Bug',
        self::TYPE_OTHER => 'Other',
    ];

    public const STATUS_NEW = 'new';

    public const STATUS_REVIEWED = 'reviewed';

    /** @var list<string> */
    protected $fillable = ['user_id', 'exam_id', 'question_id', 'type', 'message', 'status'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * The MCQ a "Report MCQ" message is about.
     *
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class)->withTrashed();
    }
}
