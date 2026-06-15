<?php

namespace App\Services;

use App\Enum\BillingInterval;
use App\Enum\ProductType;
use App\Models\Product;
use App\Models\User;
use Stripe\BillingPortal\Session as BillingPortalSession;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Customer;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Price;
use Stripe\Product as StripeProduct;
use Stripe\StripeClient;
use Stripe\Subscription;
use Stripe\Webhook;

class StripeService
{
    public function __construct(private StripeClient $client) {}

    /**
     * @throws ApiErrorException
     */
    public function createCustomer(User $user): Customer
    {
        return $this->client->customers->create([
            'email' => (string) $user->email,
            'name' => (string) $user->full_name,
        ]);
    }

    /**
     * @throws ApiErrorException
     */
    public function createProduct(Product $product): StripeProduct
    {
        return $this->client->products->create([
            'name' => (string) $product->name,
            'description' => (string) $product->description,
        ]);
    }

    /**
     * @throws ApiErrorException
     */
    public function createPrice(Product $product, string $stripeProductId): Price
    {
        $params = [
            'product' => $stripeProductId,
            'unit_amount' => (int) $product->price,
            'currency' => 'usd',
        ];

        if ($product->type === ProductType::Subscription) {
            $params['recurring'] = [
                'interval' => $this->stripeInterval($product->billing_interval),
                'interval_count' => (int) ($product->billing_interval_count ?? 1),
            ];
        }

        return $this->client->prices->create($params);
    }

    /**
     * @throws ApiErrorException
     */
    public function archivePrice(string $stripePriceId): void
    {
        $this->client->prices->update($stripePriceId, ['active' => false]);
    }

    /**
     * @throws ApiErrorException
     */
    public function archiveProduct(string $stripeProductId): void
    {
        $this->client->products->update($stripeProductId, ['active' => false]);
    }

    /**
     * @throws ApiErrorException
     */
    public function updateProduct(string $stripeProductId, array $params): StripeProduct
    {
        return $this->client->products->update($stripeProductId, $params);
    }

    /**
     * @throws ApiErrorException
     */
    public function createCheckoutSession(array $params): CheckoutSession
    {
        return $this->client->checkout->sessions->create($params);
    }

    /**
     * @throws ApiErrorException
     */
    public function retrieveCheckoutSession(string $sessionId): CheckoutSession
    {
        return $this->client->checkout->sessions->retrieve($sessionId);
    }

    /**
     * @throws ApiErrorException
     */
    public function retrieveSubscription(string $subscriptionId): Subscription
    {
        return $this->client->subscriptions->retrieve($subscriptionId);
    }

    /**
     * @throws ApiErrorException
     */
    public function cancelSubscription(string $stripeSubscriptionId, bool $atPeriodEnd = true): Subscription
    {
        return $this->client->subscriptions->update($stripeSubscriptionId, [
            'cancel_at_period_end' => $atPeriodEnd,
        ]);
    }

    /**
     * @throws ApiErrorException
     */
    public function createBillingPortalSession(string $stripeCustomerId, string $returnUrl): BillingPortalSession
    {
        return $this->client->billingPortal->sessions->create([
            'customer' => $stripeCustomerId,
            'return_url' => $returnUrl,
        ]);
    }

    /**
     * @throws SignatureVerificationException
     */
    public function constructEvent(string $payload, string $signature): Event
    {
        return Webhook::constructEvent(
            $payload,
            $signature,
            (string) config('services.stripe.webhook_secret'),
        );
    }

    private function stripeInterval(BillingInterval $interval): string
    {
        return match ($interval) {
            BillingInterval::Weekly => 'week',
            BillingInterval::Monthly => 'month',
            BillingInterval::Yearly => 'year',
        };
    }
}
