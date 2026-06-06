<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'       => $this->faker->word . ' Department',
            'code'       => strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            'description' => $this->faker->text,
            'head_name'  => $this->faker->name,
            'is_active'  => true,
        ];
    }
}
