<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\Qbank;
use Illuminate\Database\Seeder;

/**
 * The two Qbanks of the product and their exams: MRCPWorld 1 Qbank (MRCP 1)
 * and MRCPWorld 2 Qbank (MRCP 2). Each exam has its own
 * subjects and questions. Existing rows are left untouched.
 */
class ExamSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            'qbank-1' => [
                'name' => 'MRCPWorld 1 Qbank',
                'description' => 'MRCP 1',
                'sort_order' => 1,
                'exams' => [
                    'mrcp-part-1' => [
                        'name' => 'MRCP 1',
                        'code' => 'MP1',
                        'description' => 'Past papers and questions for the MRCP Part 1 written paper, covering every specialty of the curriculum.',
                        'sort_order' => 1,
                    ],
                ],
            ],
            'qbank-2' => [
                'name' => 'MRCPWorld 2 Qbank',
                'description' => 'MRCP 2',
                'sort_order' => 2,
                'exams' => [
                    'mrcp-part-2-written' => [
                        'name' => 'MRCP 2',
                        'code' => 'MP2',
                        'description' => 'Clinical and data interpretation practice for the MRCP Part 2 written paper.',
                        'sort_order' => 3,
                    ],
                ],
            ],
        ];

        foreach ($banks as $slug => $bank) {
            $qbank = Qbank::firstOrCreate(['slug' => $slug], [
                'name' => $bank['name'], 'description' => $bank['description'], 'sort_order' => $bank['sort_order'], 'is_active' => true,
            ]);
            foreach ($bank['exams'] as $examSlug => $attributes) {
                $exam = Exam::firstOrCreate(['slug' => $examSlug], [...$attributes, 'qbank_id' => $qbank->id, 'is_active' => true]);
                if ($exam->qbank_id === null) {
                    $exam->update(['qbank_id' => $qbank->id]);
                }
            }
        }
    }
}
