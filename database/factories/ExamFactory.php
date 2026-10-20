<?php

namespace Database\Factories;
use App\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Exam> */
class ExamFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        return ['name' => $name, 'slug' => Str::slug($name), 'is_active' => true];
    }
}
