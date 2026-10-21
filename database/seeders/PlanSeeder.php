<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => '1 month access', 'duration_days' => 30, 'price' => 2500, 'currency' => 'PKR', 'sort_order' => 1],
            ['name' => '3 month access', 'duration_days' => 90, 'price' => 6000, 'currency' => 'PKR', 'sort_order' => 2],
            ['name' => '12 month access', 'duration_days' => 365, 'price' => 18000, 'currency' => 'PKR', 'sort_order' => 3],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(['name' => $plan['name']], array_merge($plan, ['is_active' => true]));
        }
    }
}
