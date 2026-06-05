<?php

namespace Database\Factories;

use App\Models\ExamResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamResult>
 */
class ExamResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id'        => \App\Models\Exam::factory(),
            'student_id'     => \App\Models\Student::factory(),
            'marks_obtained' => $this->faker->randomFloat(2, 0, 100),
            'grade'          => $this->faker->randomElement(['A', 'B', 'C', 'D', 'F']),
            'is_absent'      => false,
        ];
    }
}
