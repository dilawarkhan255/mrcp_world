<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property array{body: string, options: array<string, string>, correct_option: string, explanation: string|null, code: string} $snapshot */
class PracticeItem extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var list<string> */
    protected $hidden = ['snapshot'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['snapshot' => 'array', 'is_correct' => 'boolean', 'answered_at' => 'datetime'];
    }
}
