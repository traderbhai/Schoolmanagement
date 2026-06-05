<?php

namespace Database\Factories;

use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Term>
 */
class TermFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id'   => \App\Models\Program::factory(),
            'batch_id'     => \App\Models\Batch::factory(),
            'term_number'  => $this->faker->numberBetween(1, 4),
            'name'         => 'Term ' . $this->faker->numberBetween(1, 4),
            'start_date'   => now(),
            'end_date'     => now()->addMonths(3),
            'is_current'   => false,
        ];
    }
}
