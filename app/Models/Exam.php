<?php

namespace App\Models;

use Database\Factories\ExamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['name', 'slug', 'description', 'code', 'is_active', 'sort_order'];

    /** Two letter tile code, derived from the name when the exam has none. */
    public function tileCode(): string
    {
        if ($this->code !== null && $this->code !== '') {
            return strtoupper($this->code);
        }
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $this->name) ?? '');

        return substr($letters, 0, 2) ?: 'MR';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /** @return HasMany<ScoreDistribution, $this> */
    public function scoreDistributions(): HasMany
    {
        return $this->hasMany(ScoreDistribution::class);
    }
}
