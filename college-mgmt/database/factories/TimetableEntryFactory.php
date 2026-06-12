<?php

namespace Database\Factories;

use App\Models\TimetableEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimetableEntryFactory extends Factory
{
    protected $model = TimetableEntry::class;

    public function definition(): array
    {
        return [
            'subject_id'        => \App\Models\Subject::factory(),
            'teacher_id'        => \App\Models\Teacher::factory(),
            'classroom_id'      => \App\Models\Classroom::factory(),
            'timetable_slot_id' => \App\Models\TimetableSlot::factory(),
            'program_id'        => \App\Models\Program::factory(),
            'day_of_week'       => $this->faker->randomElement(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']),
            'is_active'         => true,
        ];
    }
}
