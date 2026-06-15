<?php

namespace Database\Factories;

use App\Enum\SubscriptionStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_id' => null,
            'product_id' => null,
            'product_name' => $this->faker->words(2, true),
            'stripe_subscription_id' => 'sub_'.$this->faker->regexify('[a-zA-Z0-9]{24}'),
            'stripe_price_id' => 'price_'.$this->faker->regexify('[a-zA-Z0-9]{24}'),
            'status' => SubscriptionStatus::Active,
            'quantity' => 1,
            'trial_ends_at' => null,
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addMonth(),
            'cancel_at_period_end' => false,
            'canceled_at' => null,
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn () => [
            'user_id' => $order->user_id,
            'order_id' => $order->id,
        ]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn () => [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'stripe_price_id' => $product->stripe_price_id,
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => now(),
            'cancel_at_period_end' => false,
        ]);
    }

    public function trialing(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }
}
