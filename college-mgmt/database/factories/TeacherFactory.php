<?php

namespace Database\Factories;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        return [
            'user_id'          => \App\Models\User::factory(),
            'department_id'    => \App\Models\Department::factory(),
            'employee_id'      => $this->faker->unique()->numerify('EMP####'),
            'designation'      => $this->faker->randomElement(['Assistant Professor', 'Associate Professor', 'Professor']),
            'qualification'    => 'M.Tech',
            'employment_type'  => 'full_time',
            'status'           => 'active',
        ];
    }
}
