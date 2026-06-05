<?php

namespace Database\Factories;

use App\Models\Applicant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Applicant>
 */
class ApplicantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'           => \App\Models\User::factory(),
            'program_id'        => \App\Models\Program::factory(),
            'batch_id'          => \App\Models\Batch::factory(),
            'application_number' => Applicant::generateApplicationNumber(),
            'status'            => 'submitted',
            'personal_data'     => [],
            'academic_data'     => [],
            'family_data'       => [],
            'additional_data'   => [],
            'applied_at'        => now(),
        ];
    }
}
