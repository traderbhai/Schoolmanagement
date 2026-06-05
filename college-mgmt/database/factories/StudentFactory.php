<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'        => \App\Models\User::factory(),
            'program_id'     => \App\Models\Program::factory(),
            'batch_id'       => \App\Models\Batch::factory(),
            'enrollment_number' => $this->faker->unique()->numerify('ENR#####'),
            'roll_number'    => $this->faker->numerify('ROLL###'),
            'date_of_birth'  => $this->faker->dateTimeBetween('-25 years', '-18 years'),
            'gender'         => $this->faker->randomElement(['M', 'F', 'Other']),
            'phone'          => $this->faker->phoneNumber,
            'admission_date' => now()->subYear(),
            'status'         => 'active',
        ];
    }
}
