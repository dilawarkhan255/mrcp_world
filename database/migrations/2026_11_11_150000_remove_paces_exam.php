<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * MRCPWorld 2 Qbank is for MRCP 2 only: removes the MRCPWorld PACES exam
 * (added earlier the same day, never given content). An exam that is still
 * referenced by any row is switched off instead of deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::table('exams')->where('slug', 'mrcp-paces')->delete();
        } catch (QueryException) {
            DB::table('exams')->where('slug', 'mrcp-paces')->update(['is_active' => false, 'updated_at' => now()]);
        }
        DB::table('qbanks')->where('slug', 'qbank-2')->where('description', 'MRCP 2 and PACES')->update(['description' => 'MRCP 2', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('qbanks')->where('slug', 'qbank-2')->where('description', 'MRCP 2')->update(['description' => 'MRCP 2 and PACES']);
        $qbankId = DB::table('qbanks')->where('slug', 'qbank-2')->value('id');
        if ($qbankId !== null && ! DB::table('exams')->where('slug', 'mrcp-paces')->exists()) {
            DB::table('exams')->insert([
                'qbank_id' => $qbankId, 'name' => 'MRCPWorld PACES', 'slug' => 'mrcp-paces', 'code' => 'PAC',
                'description' => 'Practice for the MRCP PACES clinical exam: history taking, examination, communication and data stations.',
                'sort_order' => 4, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
};
