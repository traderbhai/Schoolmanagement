<?php

namespace Database\Factories;

use App\Models\TimetableSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimetableSlotFactory extends Factory
{
    protected $model = TimetableSlot::class;

    public function definition(): array
    {
        $hour = $this->faker->numberBetween(8, 15);
        return [
            'name'       => sprintf('%02d:00 - %02d:00', $hour, $hour + 1),
            'start_time' => sprintf('%02d:00:00', $hour),
            'end_time'   => sprintf('%02d:00:00', $hour + 1),
            'is_break'   => false,
            'sort_order' => $hour - 7,
            'is_active'  => true,
        ];
    }
}
