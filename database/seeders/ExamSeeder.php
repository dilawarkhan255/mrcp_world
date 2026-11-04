<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\Qbank;
use Illuminate\Database\Seeder;

/**
 * The two Qbanks of the product and their exams. IMM shares the MRCP 1 bank
 * and FCPS Part 2 shares the MRCP 2 bank, so neither duplicates content.
 * Existing rows are left untouched.
 */
class ExamSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            'qbank-1' => [
                'name' => 'MRCPWorld 1 Qbank',
                'description' => 'MRCP 1 and IMM',
                'sort_order' => 1,
                'owner' => ['mrcp-part-1', [
                    'name' => 'MRCP 1',
                    'code' => 'MP1',
                    'description' => 'Past papers and questions for the MRCP Part 1 written paper, covering every specialty of the curriculum.',
                    'sort_order' => 1,
                ]],
                'shared' => ['imm', ['name' => 'IMM', 'code' => 'IMM', 'sort_order' => 2]],
            ],
            'qbank-2' => [
                'name' => 'MRCPWorld 2 Qbank',
                'description' => 'MRCP 2 and FCPS Part 2',
                'sort_order' => 2,
                'owner' => ['mrcp-part-2-written', [
                    'name' => 'MRCP 2',
                    'code' => 'MP2',
                    'description' => 'Clinical and data interpretation practice for the MRCP Part 2 written paper.',
                    'sort_order' => 3,
                ]],
                'shared' => ['fcps-part-2', ['name' => 'FCPS Part 2', 'code' => 'FC2', 'sort_order' => 4]],
            ],
        ];

        foreach ($banks as $slug => $bank) {
            $qbank = Qbank::firstOrCreate(['slug' => $slug], [
                'name' => $bank['name'], 'description' => $bank['description'], 'sort_order' => $bank['sort_order'], 'is_active' => true,
            ]);
            [$ownerSlug, $ownerAttributes] = $bank['owner'];
            $owner = Exam::firstOrCreate(['slug' => $ownerSlug], [...$ownerAttributes, 'qbank_id' => $qbank->id, 'is_active' => true]);
            if ($owner->qbank_id === null) {
                $owner->update(['qbank_id' => $qbank->id]);
            }
            [$sharedSlug, $sharedAttributes] = $bank['shared'];
            Exam::firstOrCreate(['slug' => $sharedSlug], [...$sharedAttributes, 'qbank_id' => $qbank->id, 'content_exam_id' => $owner->id, 'is_active' => true]);
        }
    }
}
