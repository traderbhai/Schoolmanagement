<?php

namespace Database\Factories;

use App\Models\AssessmentComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssessmentComponentFactory extends Factory
{
    protected $model = AssessmentComponent::class;

    public function definition(): array
    {
        return [
            'subject_id'    => \App\Models\Subject::factory(),
            'name'          => $this->faker->randomElement(['IA1', 'IA2', 'End-Sem']),
            'max_marks'     => 100,
            'passing_marks' => 35,
            'weight'        => 100,
            'is_active'     => true,
        ];
    }
}
