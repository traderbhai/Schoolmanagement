<?php

namespace Database\Factories;

use App\Models\Semester;
use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class SemesterFactory extends Factory
{
    protected $model = Semester::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'name'             => 'Semester ' . $this->faker->numberBetween(1, 8),
            'number'           => $this->faker->numberBetween(1, 8),
            'start_date'       => now()->startOfMonth(),
            'end_date'         => now()->addMonths(6),
            'is_current'       => false,
        ];
    }
}
