<?php

use App\Enum\OrderStatus;
use App\Enum\SubscriptionStatus;
use App\Events\OrderPaid;
use App\Mail\OrderConfirmationMail;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Stripe\Customer;
use Stripe\Event as StripeEvent;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Price;
use Stripe\Product as StripeProduct;
use Stripe\Subscription as StripeSubscription;

beforeEach(function () {
    // Prevent mail from rendering in tests that don't specifically test mail behaviour
    Mail::fake();

    // Mock ProductObserver + CreateNewUser Stripe calls so factory-created products/users
    // don't hit the real Stripe API and overwrite our test stripe_price_id values.
    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('createProduct')->andReturn(StripeProduct::constructFrom(['id' => 'prod_test']));
        $mock->allows('createPrice')->andReturn(Price::constructFrom(['id' => 'price_test']));
        $mock->allows('createCustomer')->andReturn(Customer::constructFrom(['id' => 'cus_test']));
    });
});

// Build a fake StripeEvent and mock constructEvent to return it.
// Must be called AFTER all factory creates — this replaces the beforeEach mock.
function fakeStripeEvent(string $type, array $objectData, ?StripeSubscription $stripeSub = null): void
{
    $event = StripeEvent::constructFrom([
        'id' => 'evt_test',
        'type' => $type,
        'data' => ['object' => $objectData],
    ]);

    test()->mock(StripeService::class, function (MockInterface $mock) use ($event, $stripeSub) {
        $mock->allows('constructEvent')->andReturn($event);
        if ($stripeSub) {
            $mock->allows('retrieveSubscription')->andReturn($stripeSub);
        }
    });
}

function fakeStripeSubscription(string $priceId = 'price_test', string $subId = 'sub_test'): StripeSubscription
{
    return StripeSubscription::constructFrom([
        'id' => $subId,
        'object' => 'subscription',
        'status' => 'active',
        'current_period_start' => now()->timestamp,
        'current_period_end' => now()->addMonth()->timestamp,
        'cancel_at_period_end' => false,
        'canceled_at' => null,
        'trial_end' => null,
        'items' => [
            'object' => 'list',
            'data' => [[
                'id' => 'si_test',
                'object' => 'subscription_item',
                'price' => ['id' => $priceId, 'object' => 'price', 'nickname' => null],
                'quantity' => 1,
            ]],
        ],
    ]);
}

// --- checkout.session.completed ---

it('marks a pending order as paid on checkout.session.completed', function () {
    $order = Order::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => OrderStatus::Pending,
    ]);

    fakeStripeEvent('checkout.session.completed', [
        'id' => 'cs_test',
        'object' => 'checkout.session',
        'client_reference_id' => (string) $order->id,
        'payment_intent' => 'pi_test123',
        'subscription' => null,
        'mode' => 'payment',
    ]);

    $this->post(route('webhooks.stripe'))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->fresh()->stripe_payment_intent_id)->toBe('pi_test123');
});

it('dispatches OrderPaid event after checkout.session.completed', function () {
    Event::fake([OrderPaid::class]);

    $order = Order::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => OrderStatus::Pending,
    ]);

    fakeStripeEvent('checkout.session.completed', [
        'id' => 'cs_test',
        'object' => 'checkout.session',
        'client_reference_id' => (string) $order->id,
        'payment_intent' => 'pi_test',
        'subscription' => null,
        'mode' => 'payment',
    ]);

    $this->post(route('webhooks.stripe'))->assertOk();

    Event::assertDispatched(OrderPaid::class, fn ($e) => $e->order->id === $order->id);
});

it('queues order confirmation email via OrderPaid listener', function () {
    Mail::fake();

    $user = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatus::Pending,
    ]);

    fakeStripeEvent('checkout.session.completed', [
        'id' => 'cs_test',
        'object' => 'checkout.session',
        'client_reference_id' => (string) $order->id,
        'payment_intent' => 'pi_test',
        'subscription' => null,
        'mode' => 'payment',
    ]);

    $this->post(route('webhooks.stripe'))->assertOk();

    Mail::assertQueued(OrderConfirmationMail::class, fn ($mail) => $mail->order->id === $order->id);
});

it('decrements inventory for tracked physical products on checkout', function () {
    $product = Product::factory()->physical()->create([
        'track_inventory' => true,
        'stock_quantity' => 10,
    ]);
    $order = Order::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => OrderStatus::Pending,
    ]);
    OrderItem::factory()->forProduct($product)->create([
        'order_id' => $order->id,
        'quantity' => 3,
    ]);

    fakeStripeEvent('checkout.session.completed', [
        'id' => 'cs_test',
        'object' => 'checkout.session',
        'client_reference_id' => (string) $order->id,
        'payment_intent' => 'pi_test',
        'subscription' => null,
        'mode' => 'payment',
    ]);

    $this->post(route('webhooks.stripe'))->assertOk();

    expect($product->fresh()->stock_quantity)->toBe(7);
});

it('clears the user cart on checkout.session.completed', function () {
    $user = User::factory()->create();
    $cart = Cart::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->physical()->create();
    CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatus::Pending,
    ]);

    fakeStripeEvent('checkout.session.completed', [
        'id' => 'cs_test',
        'object' => 'checkout.session',
        'client_reference_id' => (string) $order->id,
        'payment_intent' => 'pi_test',
        'subscription' => null,
        'mode' => 'payment',
    ]);

    $this->post(route('webhooks.stripe'))->assertOk();

    expect($cart->fresh()->items()->count())->toBe(0);
});

it('creates a subscription record on subscription checkout completion', function () {
    $product = Product::factory()->subscription()->create([
        'name' => 'Pro Plan',
        'stripe_price_id' => 'price_test',
    ]);
    $order = Order::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => OrderStatus::Pending,
    ]);

    $stripeSub = fakeStripeSubscription('price_test', 'sub_test');

    fakeStripeEvent('checkout.session.completed', [
        'id' => 'cs_test',
        'object' => 'checkout.session',
        'client_reference_id' => (string) $order->id,
        'payment_intent' => null,
        'subscription' => 'sub_test',
        'mode' => 'subscription',
    ], $stripeSub);

    $this->post(route('webhooks.stripe'))->assertOk();

    $sub = Subscription::where('stripe_subscription_id', 'sub_test')->first();
    expect($sub)->not->toBeNull()
        ->and($sub->user_id)->toBe($order->user_id)
        ->and($sub->order_id)->toBe($order->id)
        ->and($sub->product_id)->toBe($product->id)
        ->and($sub->product_name)->toBe('Pro Plan')
        ->and($sub->status)->toBe(SubscriptionStatus::Active);
});

it('does not double-process a duplicate checkout.session.completed webhook', function () {
    $order = Order::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => OrderStatus::Paid,
        'stripe_payment_intent_id' => 'pi_existing',
    ]);

    fakeStripeEvent('checkout.session.completed', [
        'id' => 'cs_test',
        'object' => 'checkout.session',
        'client_reference_id' => (string) $order->id,
        'payment_intent' => 'pi_new',
        'subscription' => null,
        'mode' => 'payment',
    ]);

    $this->post(route('webhooks.stripe'))->assertOk();

    expect($order->fresh()->stripe_payment_intent_id)->toBe('pi_existing');
});

it('does not create duplicate subscription records for the same stripe_subscription_id', function () {
    $order = Order::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => OrderStatus::Pending,
    ]);
    Subscription::factory()->create([
        'user_id' => $order->user_id,
        'order_id' => $order->id,
        'stripe_subscription_id' => 'sub_test',
    ]);

    $stripeSub = fakeStripeSubscription('price_test', 'sub_test');

    fakeStripeEvent('checkout.session.completed', [
        'id' => 'cs_test',
        'object' => 'checkout.session',
        'client_reference_id' => (string) $order->id,
        'payment_intent' => 'pi_test',
        'subscription' => 'sub_test',
        'mode' => 'subscription',
    ], $stripeSub);

    $this->post(route('webhooks.stripe'))->assertOk();

    expect(Subscription::where('stripe_subscription_id', 'sub_test')->count())->toBe(1);
});

// --- charge.refunded ---

it('marks an order as refunded on charge.refunded', function () {
    $order = Order::factory()->paid()->create([
        'user_id' => User::factory()->create()->id,
        'stripe_payment_intent_id' => 'pi_refund123',
    ]);

    fakeStripeEvent('charge.refunded', [
        'id' => 'ch_test',
        'object' => 'charge',
        'payment_intent' => 'pi_refund123',
    ]);

    $this->post(route('webhooks.stripe'))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded);
});

// --- invoice.payment_succeeded ---

it('updates subscription to active on invoice.payment_succeeded', function () {
    $sub = Subscription::factory()->create([
        'stripe_subscription_id' => 'sub_invoice_test',
        'status' => SubscriptionStatus::PastDue,
    ]);

    $periodStart = now()->subDays(5)->timestamp;
    $periodEnd = now()->addDays(25)->timestamp;

    fakeStripeEvent('invoice.payment_succeeded', [
        'id' => 'in_test',
        'object' => 'invoice',
        'subscription' => 'sub_invoice_test',
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
    ]);

    $this->post(route('webhooks.stripe'))->assertOk();

    expect($sub->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($sub->fresh()->current_period_start->timestamp)->toBe($periodStart)
        ->and($sub->fresh()->current_period_end->timestamp)->toBe($periodEnd);
});

// --- invoice.payment_failed ---

it('sets subscription to past_due on invoice.payment_failed', function () {
    $sub = Subscription::factory()->create([
        'stripe_subscription_id' => 'sub_fail_test',
        'status' => SubscriptionStatus::Active,
    ]);

    fakeStripeEvent('invoice.payment_failed', [
        'id' => 'in_test',
        'object' => 'invoice',
        'subscription' => 'sub_fail_test',
        'period_start' => now()->subMonth()->timestamp,
        'period_end' => now()->timestamp,
    ]);

    $this->post(route('webhooks.stripe'))->assertOk();

    expect($sub->fresh()->status)->toBe(SubscriptionStatus::PastDue);
});

// --- customer.subscription.updated ---

it('syncs subscription status and period on customer.subscription.updated', function () {
    $sub = Subscription::factory()->create([
        'stripe_subscription_id' => 'sub_upd_test',
        'status' => SubscriptionStatus::Active,
        'cancel_at_period_end' => false,
    ]);

    $newPeriodEnd = now()->addDays(10)->timestamp;

    fakeStripeEvent('customer.subscription.updated', [
        'id' => 'sub_upd_test',
        'object' => 'subscription',
        'status' => 'past_due',
        'current_period_start' => now()->timestamp,
        'current_period_end' => $newPeriodEnd,
        'cancel_at_period_end' => true,
        'canceled_at' => null,
        'trial_end' => null,
        'items' => ['object' => 'list', 'data' => []],
    ]);

    $this->post(route('webhooks.stripe'))->assertOk();

    expect($sub->fresh()->status->value)->toBe('past_due')
        ->and($sub->fresh()->current_period_end->timestamp)->toBe($newPeriodEnd)
        ->and($sub->fresh()->cancel_at_period_end)->toBeTrue();
});

// --- customer.subscription.deleted ---

it('marks subscription as canceled on customer.subscription.deleted', function () {
    $sub = Subscription::factory()->create([
        'stripe_subscription_id' => 'sub_del_test',
        'status' => SubscriptionStatus::Active,
    ]);

    fakeStripeEvent('customer.subscription.deleted', [
        'id' => 'sub_del_test',
        'object' => 'subscription',
        'status' => 'canceled',
        'current_period_start' => now()->timestamp,
        'current_period_end' => now()->addMonth()->timestamp,
        'cancel_at_period_end' => false,
        'canceled_at' => null,
        'trial_end' => null,
        'items' => ['object' => 'list', 'data' => []],
    ]);

    $this->post(route('webhooks.stripe'))->assertOk();

    expect($sub->fresh()->status)->toBe(SubscriptionStatus::Canceled)
        ->and($sub->fresh()->canceled_at)->not->toBeNull();
});

// --- signature verification ---

it('returns 400 for invalid webhook signature', function () {
    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('constructEvent')->andThrow(
            new SignatureVerificationException('Invalid signature', null)
        );
    });

    $this->post(route('webhooks.stripe'))->assertStatus(400);
});
