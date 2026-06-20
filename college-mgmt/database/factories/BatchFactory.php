<?php

namespace Database\Factories;

use App\Models\Batch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    private static int $codeSequence = 1;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sequence = self::$codeSequence++;

        return [
            'program_id'       => \App\Models\Program::factory(),
            'academic_year_id' => \App\Models\AcademicYear::factory(),
            'name'             => sprintf('Batch %06d', $sequence),
            'code'             => sprintf('B%06d', $sequence),
            'start_date'       => now()->startOfYear(),
            'end_date'         => now()->endOfYear(),
            'intake_capacity'  => 60,
            'status'           => 'active',
            'description'      => $this->faker->text,
        ];
    }
}
