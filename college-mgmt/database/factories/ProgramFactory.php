<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    private static int $sequence = 1;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sequence = self::$sequence++;

        return [
            'department_id'            => \App\Models\Department::factory(),
            'name'                     => $this->faker->word . ' Program ' . $sequence,
            'code'                     => 'PRG' . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT),
            'abbreviation'             => 'P' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'system_type'              => 'semester',
            'duration_years'           => 2,
            'total_terms'              => 4,
            'description'              => $this->faker->text,
            'default_intake_capacity'  => 60,
            'is_active'                => true,
        ];
    }
}
