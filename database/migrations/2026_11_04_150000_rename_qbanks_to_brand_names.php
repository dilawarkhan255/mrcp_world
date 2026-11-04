<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Use the product names from the MRCPWorld brochure. A Qbank an admin has
 * already renamed is left alone.
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string}> slug => [old name, new name] */
    private const NAMES = [
        'qbank-1' => ['Qbank 1', 'MRCPWorld 1 Qbank'],
        'qbank-2' => ['Qbank 2', 'MRCPWorld 2 Qbank'],
    ];

    public function up(): void
    {
        foreach (self::NAMES as $slug => [$old, $new]) {
            DB::table('qbanks')->where('slug', $slug)->where('name', $old)->update(['name' => $new, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::NAMES as $slug => [$old, $new]) {
            DB::table('qbanks')->where('slug', $slug)->where('name', $new)->update(['name' => $old]);
        }
    }
};
