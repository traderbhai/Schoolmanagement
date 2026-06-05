<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id'    => \App\Models\Program::factory(),
            'department_id' => \App\Models\Department::factory(),
            'name'          => $this->faker->word . ' ' . $this->faker->word,
            'code'          => 'SUB' . strtoupper($this->faker->unique()->lexify('###')),
            'description'   => $this->faker->sentence,
            'credits'       => $this->faker->numberBetween(1, 4),
            'type'          => 'theory',
            'hours_per_week' => $this->faker->numberBetween(2, 5),
            'is_active'     => true,
        ];
    }
}
