<?php

namespace Database\Factories;

use App\Enum\BillingInterval;
use App\Enum\PriceType;
use App\Enum\ProductType;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => null,
            'name' => fake()->words(3, true),
            'type' => ProductType::Physical,
            'sku' => strtoupper(fake()->unique()->bothify('??-####')),
            'is_active' => true,
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(100, 100000),
            'price_type' => PriceType::OneTime,
            'billing_interval' => null,
            'billing_interval_count' => null,
            'trial_period_days' => null,
            'stock_quantity' => null,
            'track_inventory' => false,
            'sort_order' => 0,
            'image' => fake()->imageUrl(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function physical(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProductType::Physical,
            'price_type' => PriceType::OneTime,
            'billing_interval' => null,
            'billing_interval_count' => null,
        ]);
    }

    public function digital(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProductType::Digital,
            'price_type' => PriceType::OneTime,
            'billing_interval' => null,
            'billing_interval_count' => null,
        ]);
    }

    public function subscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProductType::Subscription,
            'price_type' => PriceType::Recurring,
            'billing_interval' => BillingInterval::Monthly,
            'billing_interval_count' => 1,
        ]);
    }

    public function withCategory(Category $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }

    public function trackingInventory(): static
    {
        return $this->state(fn (array $attributes) => [
            'track_inventory' => true,
            'stock_quantity' => fake()->numberBetween(1, 500),
        ]);
    }
}
