<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreDistribution extends Model
{
    /** @var list<string> */
    protected $fillable = ['exam_id', 'bucket_start', 'bucket_end', 'candidates'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['bucket_start' => 'integer', 'bucket_end' => 'integer', 'candidates' => 'integer'];
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
