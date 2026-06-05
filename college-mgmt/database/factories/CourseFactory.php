<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => $this->faker->words(3, true),
            'code' => 'CRS' . strtoupper($this->faker->unique()->lexify('###')),
            'description' => $this->faker->sentence,
            'duration_years' => $this->faker->numberBetween(1, 4),
            'total_semesters' => $this->faker->numberBetween(2, 8),
            'is_active' => true,
        ];
    }
}
