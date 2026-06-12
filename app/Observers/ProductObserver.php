<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\StripeService;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

class ProductObserver
{
    public function __construct(private readonly StripeService $stripe) {}

    public function created(Product $product): void
    {
        try {
            $stripeProduct = $this->stripe->createProduct($product);
            $stripePrice = $this->stripe->createPrice($product, $stripeProduct->id);

            $product->withoutEvents(fn () => $product->update([
                'stripe_product_id' => $stripeProduct->id,
                'stripe_price_id' => $stripePrice->id,
            ]));
        } catch (ApiErrorException $e) {
            Log::channel('stripe')->error('Failed to sync new product to Stripe', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(Product $product): void
    {
        if (! $product->stripe_product_id) {
            return;
        }

        try {
            if ($product->wasChanged(['name', 'description'])) {
                $this->stripe->updateProduct((string) $product->stripe_product_id, [
                    'name' => (string) $product->name,
                    'description' => (string) $product->description,
                ]);
            }

            if ($product->wasChanged(['price', 'billing_interval', 'billing_interval_count'])) {
                $this->stripe->archivePrice((string) $product->stripe_price_id);
                $stripePrice = $this->stripe->createPrice($product, (string) $product->stripe_product_id);

                $product->withoutEvents(fn () => $product->update([
                    'stripe_price_id' => $stripePrice->id,
                ]));
            }
        } catch (ApiErrorException $e) {
            Log::channel('stripe')->error('Failed to sync updated product to Stripe', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function forceDeleted(Product $product): void
    {
        if (! $product->stripe_product_id) {
            return;
        }

        try {
            $this->stripe->archiveProduct((string) $product->stripe_product_id);
        } catch (ApiErrorException $e) {
            Log::channel('stripe')->error('Failed to archive Stripe product on force delete', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
