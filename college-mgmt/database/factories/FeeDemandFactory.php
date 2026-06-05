<?php

namespace Database\Factories;

use App\Models\FeeDemand;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeeDemandFactory extends Factory
{
    protected $model = FeeDemand::class;

    public function definition(): array
    {
        $totalAmount = $this->faker->numberBetween(50000, 200000);
        $scholarshipDeduction = $this->faker->numberBetween(0, (int)($totalAmount * 0.5));
        $finalAmount = $totalAmount - $scholarshipDeduction;

        return [
            'student_id' => Student::factory(),
            'term_id' => Term::factory(),
            'total_amount' => $totalAmount,
            'scholarship_deduction' => $scholarshipDeduction,
            'final_amount' => $finalAmount,
            'due_date' => now()->addMonth(),
            'last_reminder_sent' => null,
            'status' => $this->faker->randomElement(['pending', 'partially_paid', 'fully_paid']),
        ];
    }
}
