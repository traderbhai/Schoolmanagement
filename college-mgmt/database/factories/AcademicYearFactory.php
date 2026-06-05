<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = now()->year;
        return [
            'name'       => "{$year}-" . ($year + 1),
            'start_year' => $year,
            'end_year'   => $year + 1,
            'start_date' => now()->startOfYear(),
            'end_date'   => now()->endOfYear(),
            'is_current' => false,
        ];
    }
}
