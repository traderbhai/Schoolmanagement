<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id'            => \App\Models\Department::factory(),
            'name'                     => $this->faker->word . ' Program',
            'code'                     => strtoupper($this->faker->lexify('???')),
            'abbreviation'             => strtoupper($this->faker->lexify('??')),
            'system_type'              => 'semester',
            'duration_years'           => 2,
            'total_terms'              => 4,
            'description'              => $this->faker->text,
            'default_intake_capacity'  => 60,
            'is_active'                => true,
        ];
    }
}
