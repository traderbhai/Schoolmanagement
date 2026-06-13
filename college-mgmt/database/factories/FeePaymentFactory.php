<?php

namespace Database\Factories;

use App\Models\FeePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeePaymentFactory extends Factory
{
    protected $model = FeePayment::class;

    public function definition(): array
    {
        return [
            'fee_demand_id'  => \App\Models\FeeDemand::factory(),
            'student_id'     => \App\Models\Student::factory(),
            'amount_paid'    => $this->faker->numberBetween(1000, 50000),
            'payment_method' => $this->faker->randomElement(['upi', 'bank_transfer', 'cash']),
            'transaction_id' => $this->faker->unique()->numerify('TXN######'),
            'payment_date'   => now()->toDateString(),
            'status'         => 'pending',
        ];
    }
}
