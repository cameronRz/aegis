<?php

namespace Database\Factories;

use App\Enum\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = $this->faker->numberBetween(500, 50000);

        return [
            'user_id' => User::factory(),
            'status' => OrderStatus::Pending,
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'stripe_checkout_session_id' => null,
            'stripe_payment_intent_id' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Paid,
            'stripe_checkout_session_id' => 'cs_test_'.$this->faker->regexify('[a-zA-Z0-9]{24}'),
            'stripe_payment_intent_id' => 'pi_test_'.$this->faker->regexify('[a-zA-Z0-9]{24}'),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Expired]);
    }
}
