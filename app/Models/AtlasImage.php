<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A clinical image of the Image Atlas. Images may come from other sources, so
 * the source, licence and a human copyright confirmation are recorded, and an
 * image without that confirmation can never be published.
 */
class AtlasImage extends Model
{
    public const DISK = 'public';

    public const DIRECTORY = 'atlas';

    /** @var array<string, string> licence => label */
    public const LICENSES = [
        'own' => 'Our own image',
        'permission' => 'Used with written permission',
        'cc0' => 'CC0 / public domain',
        'cc-by' => 'CC BY',
        'cc-by-sa' => 'CC BY-SA',
        'cc-by-nc' => 'CC BY-NC',
        'unknown' => 'Unknown — do not publish',
    ];

    /** @var list<string> */
    protected $fillable = [
        'qbank_id', 'title', 'slug', 'diagnosis', 'subject', 'system', 'description', 'image_path',
        'source_name', 'source_url', 'license', 'attribution', 'copyright_confirmed', 'confirmed_by', 'confirmed_at', 'is_published',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['copyright_confirmed' => 'boolean', 'is_published' => 'boolean', 'confirmed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (AtlasImage $image) {
            if ((string) ($image->getAttributes()['slug'] ?? '') === '') {
                $image->slug = self::uniqueSlug($image->title);
            }
            if (! str_starts_with($image->image_path, self::DIRECTORY.'/') || str_contains($image->image_path, '..')) {
                throw ValidationException::withMessages(['image_path' => 'Upload the image through the atlas form.']);
            }
            if (! array_key_exists($image->license, self::LICENSES)) {
                throw ValidationException::withMessages(['license' => 'Choose a licence from the list.']);
            }
            if ($image->isDirty('copyright_confirmed')) {
                $image->confirmed_by = $image->copyright_confirmed ? auth()->id() : null;
                $image->confirmed_at = $image->copyright_confirmed ? now() : null;
            }
            if ($image->is_published && (! $image->copyright_confirmed || $image->license === 'unknown')) {
                throw ValidationException::withMessages(['is_published' => 'Confirm the copyright and choose a known licence before publishing this image.']);
            }
        });
        static::deleted(function (AtlasImage $image) {
            Storage::disk(self::DISK)->delete($image->image_path);
        });
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title) ?: 'image', 200, '');
        $slug = $base;
        $suffix = 2;
        while (self::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /** @param Builder<AtlasImage> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->where('copyright_confirmed', true);
    }

    public function url(): string
    {
        return Storage::disk(self::DISK)->url($this->image_path);
    }

    /** @return BelongsTo<Qbank, $this> */
    public function qbank(): BelongsTo
    {
        return $this->belongsTo(Qbank::class);
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
