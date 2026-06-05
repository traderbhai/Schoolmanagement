<?php

namespace Database\Factories;

use App\Models\AcademicCalendar;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicCalendarFactory extends Factory
{
    protected $model = AcademicCalendar::class;

    public function definition(): array
    {
        $eventType = $this->faker->randomElement(['holiday', 'exam_week', 'semester_start', 'semester_end', 'class_start', 'class_end', 'other']);
        $isHoliday = $eventType === 'holiday';

        return [
            'term_id' => Term::factory(),
            'event_date' => $this->faker->dateTimeBetween('now', '+6 months'),
            'event_name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence,
            'event_type' => $eventType,
            'is_holiday' => $isHoliday,
        ];
    }
}
