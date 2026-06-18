<?php

namespace App\Http\Controllers;

use App\Enum\OrderStatus;
use App\Enum\SubscriptionStatus;
use App\Events\OrderPaid;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\CartService;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Invoice;
use Stripe\Subscription as StripeSubscription;

class WebhookController extends Controller
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly CartService $cartService,
    ) {}

    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        try {
            $event = $this->stripe->constructEvent($payload, $signature);
        } catch (SignatureVerificationException) {
            return response('Invalid signature', 400);
        }

        try {
            match ($event->type) {
                'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
                'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($event->data->object),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
                'charge.refunded' => $this->handleChargeRefunded($event->data->object),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::channel('stripe')->error('Unhandled webhook exception', [
                'event_type' => $event->type,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response('OK', 200);
    }

    private function handleCheckoutCompleted(CheckoutSession $session): void
    {
        $processedOrder = DB::transaction(function () use ($session): ?Order {
            $order = Order::query()
                ->where('id', (int) $session->client_reference_id)
                ->lockForUpdate()
                ->first();

            if (! $order || $order->status !== OrderStatus::Pending) {
                return null;
            }

            $order->load('items.product', 'user');

            $order->update([
                'status' => OrderStatus::Paid,
                'stripe_payment_intent_id' => $session->payment_intent,
            ]);

            if ($session->subscription) {
                $this->createSubscriptionRecord($order, (string) $session->subscription);
            }

            $this->decrementInventory($order);

            if ($order->user) {
                $cart = Cart::where('user_id', $order->user->id)->first();
                if ($cart) {
                    $this->cartService->clear($cart);
                }
            }

            return $order;
        }, attempts: 5);

        if (! $processedOrder) {
            return;
        }

        OrderPaid::dispatch($processedOrder->fresh('items', 'user'));
    }

    private function decrementInventory(Order $order): void
    {
        $trackedItems = $order->items->filter(
            fn ($item) => $item->product_id && $item->product?->track_inventory
        );

        if ($trackedItems->isEmpty()) {
            return;
        }

        $products = Product::query()
            ->whereIn('id', $trackedItems->pluck('product_id')->unique())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($trackedItems as $item) {
            $product = $products->get($item->product_id);

            if (! $product || $product->stock_quantity === null) {
                continue;
            }

            $newStockQuantity = max(0, $product->stock_quantity - $item->quantity);

            if ($newStockQuantity === 0 && $product->stock_quantity < $item->quantity) {
                Log::channel('stripe')->warning('Checkout completed with insufficient inventory', [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'stock_quantity' => $product->stock_quantity,
                    'quantity' => $item->quantity,
                ]);
            }

            $product->update(['stock_quantity' => $newStockQuantity]);
        }
    }

    private function createSubscriptionRecord(Order $order, string $stripeSubscriptionId): void
    {
        $existing = Subscription::where('stripe_subscription_id', $stripeSubscriptionId)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return;
        }

        try {
            $stripeSub = $this->stripe->retrieveSubscription($stripeSubscriptionId);
        } catch (\Exception $e) {
            Log::channel('stripe')->error('Failed to retrieve subscription for webhook', [
                'stripe_subscription_id' => $stripeSubscriptionId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $priceId = $stripeSub->items->data[0]->price->id ?? null;
        $product = $priceId ? Product::withTrashed()->where('stripe_price_id', $priceId)->first() : null;

        Subscription::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'product_id' => $product?->id,
            'product_name' => $product?->name ?? ($stripeSub->items->data[0]->price->nickname ?? ''),
            'stripe_subscription_id' => $stripeSub->id,
            'stripe_price_id' => $priceId ?? '',
            'status' => $stripeSub->status,
            'quantity' => $stripeSub->items->data[0]->quantity ?? 1,
            'trial_ends_at' => $stripeSub->trial_end ? Carbon::createFromTimestamp($stripeSub->trial_end) : null,
            // period dates may be null on trial subscriptions at creation time;
            // customer.subscription.updated fires immediately after and corrects them.
            'current_period_start' => $stripeSub->current_period_start
                ? Carbon::createFromTimestamp($stripeSub->current_period_start)
                : now(),
            'current_period_end' => $stripeSub->current_period_end
                ? Carbon::createFromTimestamp($stripeSub->current_period_end)
                : ($stripeSub->trial_end ? Carbon::createFromTimestamp($stripeSub->trial_end) : now()->addMonth()),
            'cancel_at_period_end' => (bool) $stripeSub->cancel_at_period_end,
            'canceled_at' => $stripeSub->canceled_at ? Carbon::createFromTimestamp($stripeSub->canceled_at) : null,
        ]);
    }

    private function handleInvoicePaymentSucceeded(Invoice $invoice): void
    {
        if (! $invoice->subscription) {
            return;
        }

        DB::transaction(function () use ($invoice): void {
            $subscription = Subscription::where('stripe_subscription_id', $invoice->subscription)
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                return;
            }

            $subscription->update([
                'status' => SubscriptionStatus::Active->value,
                'current_period_start' => Carbon::createFromTimestamp($invoice->period_start),
                'current_period_end' => Carbon::createFromTimestamp($invoice->period_end),
            ]);
        });
    }

    private function handleInvoicePaymentFailed(Invoice $invoice): void
    {
        if (! $invoice->subscription) {
            return;
        }

        $subscription = Subscription::where('stripe_subscription_id', $invoice->subscription)->first();

        $subscription?->update(['status' => SubscriptionStatus::PastDue->value]);
    }

    private function handleSubscriptionUpdated(StripeSubscription $stripeSub): void
    {
        DB::transaction(function () use ($stripeSub): void {
            $subscription = Subscription::where('stripe_subscription_id', $stripeSub->id)
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                return;
            }

            $subscription->update([
                'status' => $stripeSub->status,
                'current_period_end' => Carbon::createFromTimestamp($stripeSub->current_period_end),
                'cancel_at_period_end' => (bool) $stripeSub->cancel_at_period_end,
            ]);
        });
    }

    private function handleSubscriptionDeleted(StripeSubscription $stripeSub): void
    {
        DB::transaction(function () use ($stripeSub): void {
            $subscription = Subscription::where('stripe_subscription_id', $stripeSub->id)
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                return;
            }

            $subscription->update([
                'status' => SubscriptionStatus::Canceled->value,
                'canceled_at' => now(),
            ]);
        });
    }

    private function handleChargeRefunded(object $charge): void
    {
        if (! $charge->payment_intent) {
            return;
        }

        DB::transaction(function () use ($charge): void {
            $order = Order::where('stripe_payment_intent_id', $charge->payment_intent)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return;
            }

            $order->update(['status' => OrderStatus::Refunded]);
        });
    }
}
