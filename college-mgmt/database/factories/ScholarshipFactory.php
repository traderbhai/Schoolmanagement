<?php

namespace Database\Factories;

use App\Models\Scholarship;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScholarshipFactory extends Factory
{
    protected $model = Scholarship::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence,
            'percentage' => $this->faker->numberBetween(5, 50),
            'fixed_amount' => $this->faker->randomElement([null, $this->faker->numberBetween(5000, 50000)]),
            'type' => $this->faker->randomElement(['merit', 'need_based', 'category', 'other']),
            'status' => 'active',
            'valid_from' => now()->startOfYear(),
            'valid_to' => now()->endOfYear(),
        ];
    }
}
