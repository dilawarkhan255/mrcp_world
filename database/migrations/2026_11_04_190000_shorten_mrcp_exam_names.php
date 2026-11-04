<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Shows the exams as "MRCP 1" and "MRCP 2" (without "UK/Irish"). A name an
 * admin has already changed is left alone.
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string}> slug => [old name, new name] */
    private const EXAMS = [
        'mrcp-part-1' => ['MRCP 1 UK/Irish', 'MRCP 1'],
        'mrcp-part-2-written' => ['MRCP 2 UK/Irish', 'MRCP 2'],
    ];

    /** @var array<string, array{0: string, 1: string}> slug => [old description, new description] */
    private const QBANKS = [
        'qbank-1' => ['MRCP 1 UK/Irish and IMM', 'MRCP 1 and IMM'],
        'qbank-2' => ['MRCP 2 UK/Irish and FCPS Part 2', 'MRCP 2 and FCPS Part 2'],
    ];

    public function up(): void
    {
        foreach (self::EXAMS as $slug => [$old, $new]) {
            DB::table('exams')->where('slug', $slug)->where('name', $old)->update(['name' => $new, 'updated_at' => now()]);
        }
        foreach (self::QBANKS as $slug => [$old, $new]) {
            DB::table('qbanks')->where('slug', $slug)->where('description', $old)->update(['description' => $new, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::EXAMS as $slug => [$old, $new]) {
            DB::table('exams')->where('slug', $slug)->where('name', $new)->update(['name' => $old]);
        }
        foreach (self::QBANKS as $slug => [$old, $new]) {
            DB::table('qbanks')->where('slug', $slug)->where('description', $new)->update(['description' => $old]);
        }
    }
};
