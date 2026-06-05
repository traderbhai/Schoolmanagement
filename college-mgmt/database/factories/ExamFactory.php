<?php

namespace Database\Factories;

use App\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'term_id'        => \App\Models\Term::factory(),
            'program_id'     => \App\Models\Program::factory(),
            'subject_id'     => \App\Models\Subject::factory(),
            'name'           => $this->faker->word . ' Exam',
            'type'           => 'theory',
            'exam_date'      => now()->addDays(30),
            'start_time'     => '09:00',
            'end_time'       => '12:00',
            'total_marks'    => 100,
            'passing_marks'  => 40,
        ];
    }
}
