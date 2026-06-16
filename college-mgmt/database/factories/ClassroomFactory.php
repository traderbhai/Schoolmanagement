<?php

namespace Database\Factories;

use App\Models\Classroom;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassroomFactory extends Factory
{
    protected $model = Classroom::class;

    public function definition(): array
    {
        return [
            'name'         => 'Room ' . $this->faker->unique()->numerify('###'),
            'room_number'  => $this->faker->unique()->numerify('R###'),
            'capacity'     => $this->faker->randomElement([30, 40, 60]),
            'type'         => 'lecture',
            'is_active'    => true,
        ];
    }
}
