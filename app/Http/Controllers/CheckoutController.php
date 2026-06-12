<?php

namespace App\Http\Controllers;

use App\Enum\OrderStatus;
use App\Enum\ProductType;
use App\Models\Order;
use App\Services\CartService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly StripeService $stripe,
    ) {}

    /**
     * @throws \Throwable
     */
    public function store(Request $request): SymfonyResponse
    {
        $user = $request->user();
        $cart = $this->cartService->getOrCreate($user);
        $cart->loadMissing('items.product');

        if ($this->cartService->isEmpty($cart)) {
            return back()->withErrors(['checkout' => 'Your cart is empty.']);
        }

        $inactiveItems = $cart->items->filter(
            fn ($item) => $item->product === null || ! $item->product->is_active
        );

        if ($inactiveItems->isNotEmpty()) {
            return back()->withErrors(['checkout' => 'Some items in your cart are no longer available. Please remove them before checking out.']);
        }

        $unsyncedItems = $cart->items->filter(
            fn ($item) => empty($item->product->stripe_price_id)
        );

        if ($unsyncedItems->isNotEmpty()) {
            return back()->withErrors(['checkout' => 'Some items are not yet ready for purchase. Please try again shortly.']);
        }

        // Lazy Stripe customer creation if Stripe was down at registration
        if (! $user->stripe_customer_id) {
            try {
                $customer = $this->stripe->createCustomer($user);
                $user->stripe_customer_id = $customer->id;
                $user->save();
            } catch (ApiErrorException $e) {
                Log::channel('stripe')->error('Failed to create Stripe customer at checkout', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                return back()->withErrors(['checkout' => 'Payment setup failed. Please try again.']);
            }
        }

        $subtotal = $this->cartService->total($cart);

        $order = DB::transaction(function () use ($cart, $user, $subtotal) {
            $order = Order::create([
                'user_id' => $user->id,
                'status' => OrderStatus::Pending,
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ]);

            foreach ($cart->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'product_name' => (string) $item->product->name,
                    'product_sku' => (string) $item->product->sku,
                    'product_type' => $item->product->type->value,
                    'price' => (int) $item->product->price,
                    'quantity' => $item->quantity,
                ]);
            }

            return $order;
        });

        $isSubscription = $this->cartService->hasSubscription($cart);

        $params = [
            'customer' => (string) $user->stripe_customer_id,
            'client_reference_id' => (string) $order->id,
            'mode' => $isSubscription ? 'subscription' : 'payment',
            'success_url' => route('checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('checkout.cancel'),
        ];

        if ($isSubscription) {
            $subscriptionItems = $cart->items->filter(
                fn ($item) => $item->product->type === ProductType::Subscription
            );
            $oneTimeItems = $cart->items->filter(
                fn ($item) => $item->product->type !== ProductType::Subscription
            );

            $params['line_items'] = $subscriptionItems->map(fn ($item) => [
                'price' => (string) $item->product->stripe_price_id,
                'quantity' => $item->quantity,
            ])->values()->toArray();

            $subscriptionData = [];

            if ($oneTimeItems->isNotEmpty()) {
                $subscriptionData['add_invoice_items'] = $oneTimeItems->map(fn ($item) => [
                    'price' => (string) $item->product->stripe_price_id,
                    'quantity' => $item->quantity,
                ])->values()->toArray();
            }

            $maxTrial = $subscriptionItems->max(fn ($item) => $item->product->trial_period_days);
            if ($maxTrial) {
                $subscriptionData['trial_period_days'] = (int) $maxTrial;
            }

            if (! empty($subscriptionData)) {
                $params['subscription_data'] = $subscriptionData;
            }
        } else {
            $params['line_items'] = $cart->items->map(fn ($item) => [
                'price' => (string) $item->product->stripe_price_id,
                'quantity' => $item->quantity,
            ])->values()->toArray();
        }

        try {
            $session = $this->stripe->createCheckoutSession($params);
        } catch (ApiErrorException $e) {
            Log::channel('stripe')->error('Failed to create Stripe checkout session', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['checkout' => 'Failed to start checkout. Please try again.']);
        }

        $order->update(['stripe_checkout_session_id' => $session->id]);

        return Inertia::location((string) $session->url);
    }

    public function success(Request $request): Response
    {
        $sessionId = $request->query('session_id');

        if (! $sessionId) {
            abort(400);
        }

        $order = Order::with('items')
            ->where('stripe_checkout_session_id', $sessionId)
            ->firstOrFail();

        abort_unless($order->user_id === $request->user()->id, 403);

        return Inertia::render('checkout/success', [
            'order' => $order,
        ]);
    }

    public function cancel(): Response
    {
        return Inertia::render('checkout/cancel');
    }
}
