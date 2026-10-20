<?php

namespace Database\Seeders;
use App\Models\Exam;
use Illuminate\Database\Seeder;

class ExamSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['mrcp-part-1' => 'MRCP Part 1', 'mrcp-part-2-written' => 'MRCP Part 2 Written'] as $slug => $name) {
            Exam::firstOrCreate(['slug' => $slug], ['name' => $name, 'is_active' => true]);
        }
    }
}
