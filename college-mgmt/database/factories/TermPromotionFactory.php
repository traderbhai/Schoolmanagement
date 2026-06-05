<?php

namespace Database\Factories;

use App\Models\TermPromotion;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

class TermPromotionFactory extends Factory
{
    protected $model = TermPromotion::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'current_term_id' => Term::factory(),
            'promoted_to_term_id' => Term::factory(),
            'cgpa' => $this->faker->randomFloat(2, 2.0, 4.0),
            'attendance_percentage' => $this->faker->numberBetween(50, 100),
            'meets_academic_criteria' => $this->faker->boolean,
            'meets_attendance_criteria' => $this->faker->boolean,
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected', 'on_hold']),
            'remarks' => $this->faker->optional()->sentence,
            'processed_by' => null,
            'processed_at' => null,
        ];
    }
}
