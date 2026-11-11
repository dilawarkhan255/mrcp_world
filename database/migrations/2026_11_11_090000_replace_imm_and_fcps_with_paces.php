<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Drops IMM and FCPS Part 2 (which only read the MRCP 1 and MRCP 2 banks) and
 * adds MRCPWorld PACES, an exam with its own subjects and questions, sold in
 * the MRCPWorld 2 Qbank. An exam that is still referenced by any row (the
 * foreign keys restrict the delete) is switched off instead of deleted.
 */
return new class extends Migration
{
    private const REMOVED = ['imm', 'fcps-part-2'];

    /** @var array<string, array{0: string, 1: string}> slug => [old description, new description] */
    private const QBANKS = [
        'qbank-1' => ['MRCP 1 and IMM', 'MRCP 1'],
        'qbank-2' => ['MRCP 2 and FCPS Part 2', 'MRCP 2 and PACES'],
    ];

    public function up(): void
    {
        foreach (self::REMOVED as $slug) {
            try {
                DB::table('exams')->where('slug', $slug)->delete();
            } catch (QueryException) {
                DB::table('exams')->where('slug', $slug)->update(['is_active' => false, 'updated_at' => now()]);
            }
        }

        foreach (self::QBANKS as $slug => [$old, $new]) {
            DB::table('qbanks')->where('slug', $slug)->where('description', $old)->update(['description' => $new, 'updated_at' => now()]);
        }

        $qbankId = DB::table('qbanks')->where('slug', 'qbank-2')->value('id');
        if ($qbankId !== null && ! DB::table('exams')->where('slug', 'mrcp-paces')->exists()) {
            DB::table('exams')->insert([
                'qbank_id' => $qbankId,
                'content_exam_id' => null,
                'name' => 'MRCPWorld PACES',
                'slug' => 'mrcp-paces',
                'code' => 'PAC',
                'description' => 'Practice for the MRCP PACES clinical exam: history taking, examination, communication and data stations.',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        try {
            DB::table('exams')->where('slug', 'mrcp-paces')->delete();
        } catch (QueryException) {
            // PACES already has content; keep it.
        }

        foreach (self::QBANKS as $slug => [$old, $new]) {
            DB::table('qbanks')->where('slug', $slug)->where('description', $new)->update(['description' => $old]);
        }

        $shared = [
            'imm' => ['IMM', 'IMM', 2, 'mrcp-part-1', 'qbank-1'],
            'fcps-part-2' => ['FCPS Part 2', 'FC2', 4, 'mrcp-part-2-written', 'qbank-2'],
        ];
        foreach ($shared as $slug => [$name, $code, $order, $owner, $qbank]) {
            if (DB::table('exams')->where('slug', $slug)->exists()) {
                DB::table('exams')->where('slug', $slug)->update(['is_active' => true]);

                continue;
            }
            $ownerId = DB::table('exams')->where('slug', $owner)->value('id');
            $qbankId = DB::table('qbanks')->where('slug', $qbank)->value('id');
            if ($ownerId !== null && $qbankId !== null) {
                DB::table('exams')->insert([
                    'qbank_id' => $qbankId, 'content_exam_id' => $ownerId, 'name' => $name, 'slug' => $slug, 'code' => $code,
                    'sort_order' => $order, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }
};
