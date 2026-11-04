<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([ExamSeeder::class, PlanSeeder::class, RoleSeeder::class, LocalUserSeeder::class]);
        // Sample MCQs, textbook and demo activity: local and testing only (the seeder checks the environment).
        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoContentSeeder::class);
        }
    }
}
