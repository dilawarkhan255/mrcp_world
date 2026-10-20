<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory;
    /** @var list<string> */
    protected $fillable = ['exam_id', 'name', 'slug'];
    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo { return $this->belongsTo(Exam::class); }
}
