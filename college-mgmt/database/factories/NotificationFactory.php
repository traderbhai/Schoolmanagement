<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'title'      => $this->faker->sentence(4),
            'message'    => $this->faker->paragraph,
            'type'       => $this->faker->randomElement(['info', 'success', 'warning', 'error']),
            'action_url' => $this->faker->optional()->url,
            'is_read'    => false,
            'read_at'    => null,
        ];
    }
}
