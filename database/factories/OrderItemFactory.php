<?php

namespace Database\Factories;

use App\Enum\ProductType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => null,
            'product_name' => $this->faker->words(3, true),
            'product_sku' => strtoupper($this->faker->bothify('??-####')),
            'product_type' => ProductType::Physical->value,
            'price' => $this->faker->numberBetween(500, 10000),
            'quantity' => $this->faker->numberBetween(1, 5),
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn () => [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'product_type' => $product->type->value,
            'price' => $product->price,
        ]);
    }
}
