<?php

namespace Database\Seeders;

use App\Models\Exam;
use Illuminate\Database\Seeder;

class ExamSeeder extends Seeder
{
    public function run(): void
    {
        $exams = [
            'mrcp-part-1' => [
                'name' => 'MRCP Part 1',
                'code' => 'MP1',
                'description' => 'Past papers and questions for the MRCP Part 1 written paper, covering every specialty of the curriculum.',
                'sort_order' => 1,
            ],
            'mrcp-part-2-written' => [
                'name' => 'MRCP Part 2 Written',
                'code' => 'MP2',
                'description' => 'Essay and data interpretation practice for the MRCP Part 2 written paper, with the same marking scheme as the real exam.',
                'sort_order' => 2,
            ],
        ];

        foreach ($exams as $slug => $attributes) {
            Exam::firstOrCreate(['slug' => $slug], array_merge($attributes, ['is_active' => true]));
        }
    }
}
