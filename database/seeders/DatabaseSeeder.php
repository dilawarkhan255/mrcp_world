<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([ExamSeeder::class, PlanSeeder::class, RoleSeeder::class, LocalUserSeeder::class]);
        // Sample MCQs, textbook and demo activity: only when asked for (SEED_DEMO_CONTENT=true, local only) and in
        // tests. The owner removed the demo content on 2026-11-11, so a normal db:seed must not bring it back.
        if (app()->environment('testing') || (app()->environment('local') && config('app.seed_demo_content'))) {
            $this->call(DemoContentSeeder::class);
        }
    }
}
