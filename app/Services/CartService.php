<?php

namespace App\Services;

use App\Enum\ProductType;
use App\Exceptions\CartException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;

class CartService
{
    public function getOrCreate(User $user): Cart
    {
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $cart->loadMissing('items.product');

        return $cart;
    }

    public function add(Cart $cart, Product $product, int $quantity = 1): CartItem
    {
        if ($product->trashed() || ! $product->is_active) {
            throw CartException::productInactive();
        }

        // Query from DB — the in-memory $cart->items collection is stale if items
        // were added after the cart was last loaded.
        $existingItem = $cart->items()->where('product_id', $product->id)->first();
        $newQuantity = $existingItem ? $existingItem->quantity + $quantity : $quantity;

        if ($product->type === ProductType::Subscription && $newQuantity > 1) {
            throw CartException::subscriptionQuantityExceeded();
        }

        if ($product->track_inventory && $product->stock_quantity !== null && $product->stock_quantity < $newQuantity) {
            throw CartException::insufficientStock($product->stock_quantity);
        }

        if ($existingItem) {
            $existingItem->update(['quantity' => $newQuantity]);
            $this->syncCartCount($cart);

            return $existingItem->fresh();
        }

        $item = $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);

        $this->syncCartCount($cart);

        return $item;
    }

    public function updateQuantity(CartItem $item, int $quantity): CartItem
    {
        $product = $item->product;

        if ($product === null) {
            throw CartException::productUnavailable();
        }

        if ($product->type === ProductType::Subscription && $quantity > 1) {
            throw CartException::subscriptionQuantityExceeded();
        }

        if ($product->track_inventory && $product->stock_quantity !== null && $product->stock_quantity < $quantity) {
            throw CartException::insufficientStock($product->stock_quantity);
        }

        $item->update(['quantity' => $quantity]);

        $this->syncCartCount($item->cart);

        return $item->fresh();
    }

    public function remove(CartItem $item): void
    {
        $cart = $item->cart;
        $item->delete();
        $this->syncCartCount($cart);
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
        $this->syncCartCount($cart);
    }

    public function total(Cart $cart): int
    {
        return $cart->items
            ->filter(fn (CartItem $item) => $item->product !== null)
            ->sum(fn (CartItem $item) => $item->product->price * $item->quantity);
    }

    public function isEmpty(Cart $cart): bool
    {
        return $cart->items->isEmpty();
    }

    public function hasSubscription(Cart $cart): bool
    {
        return $cart->items->contains(
            fn (CartItem $item) => $item->product?->type === ProductType::Subscription
        );
    }

    private function syncCartCount(Cart $cart): void
    {
        session(['cart_count' => $cart->items()->count()]);
    }
}
