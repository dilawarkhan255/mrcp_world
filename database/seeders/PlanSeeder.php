<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Qbank;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /** Every Qbank: 3 months for £8 and 5 months for £12. */
    public const PLANS = [
        ['duration_days' => 90, 'price' => 8, 'sort_order' => 1],
        ['duration_days' => 150, 'price' => 12, 'sort_order' => 2],
    ];

    public function run(): void
    {
        // Existing plans are left untouched (an admin may have changed them).
        foreach (Qbank::orderBy('sort_order')->orderBy('id')->get() as $qbank) {
            foreach (self::PLANS as $plan) {
                Plan::firstOrCreate(['qbank_id' => $qbank->id, 'duration_days' => $plan['duration_days']], [
                    'name' => $qbank->name, 'price' => $plan['price'], 'currency' => 'GBP',
                    'is_active' => true, 'sort_order' => $plan['sort_order'],
                ]);
            }
        }
    }
}
