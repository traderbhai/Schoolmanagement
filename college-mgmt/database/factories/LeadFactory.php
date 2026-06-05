<?php

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'        => $this->faker->name,
            'email'       => $this->faker->unique()->safeEmail,
            'phone'       => $this->faker->phoneNumber,
            'program_id'  => \App\Models\Program::factory(),
            'source'      => $this->faker->randomElement(['web_form', 'referral', 'advertisement', 'social_media', 'event', 'agent', 'other']),
            'status'      => $this->faker->randomElement(['new', 'contacted', 'interested', 'not_interested', 'converted']),
            'notes'       => $this->faker->sentence,
        ];
    }
}
