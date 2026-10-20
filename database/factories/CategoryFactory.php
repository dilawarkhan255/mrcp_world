<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return ['exam_id' => Exam::factory(), 'name' => $name, 'slug' => Str::slug($name)];
    }
}
