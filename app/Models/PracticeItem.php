<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Older snapshots only have the first five keys; rich content was added later.
 *
 * @property array{body: string, options: array<string, string>, correct_option: string, explanation: string|null, code: string, key_points?: string|null, mnemonics?: string|null, references?: array<int, mixed>|null, youtube_url?: string|null, image_links?: array<int, mixed>|null, labels?: array<string, mixed>} $snapshot
 */
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
