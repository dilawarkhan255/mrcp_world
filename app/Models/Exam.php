<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    /** @use HasFactory<\Database\Factories\ExamFactory> */
    use HasFactory;
    /** @var list<string> */
    protected $fillable = ['name', 'slug', 'is_active'];
    /** @return array<string, string> */
    protected function casts(): array { return ['is_active' => 'boolean']; }
    /** @return HasMany<Category, $this> */
    public function categories(): HasMany { return $this->hasMany(Category::class); }
}
