<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Qbank;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // £8 for 6 months per Qbank, from the MRCPWorld brochure. Existing plans are left untouched.
        foreach (Qbank::orderBy('sort_order')->orderBy('id')->get() as $index => $qbank) {
            Plan::firstOrCreate(['qbank_id' => $qbank->id], [
                'name' => $qbank->name.' — 6 months', 'duration_days' => 180, 'price' => 8, 'currency' => 'GBP',
                'is_active' => true, 'sort_order' => $index + 1,
            ]);
        }
    }
}
