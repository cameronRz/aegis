<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\ApiErrorException;

class SubscriptionController extends Controller
{
    public function __construct(private readonly StripeService $stripe) {}

    public function index(Request $request): Response
    {
        $subscriptions = Subscription::where('user_id', $request->user()->id)
            ->with('product:id,name,billing_interval,billing_interval_count')
            ->latest()
            ->get();

        return Inertia::render('subscriptions/index', [
            'subscriptions' => $subscriptions,
        ]);
    }

    public function cancel(Request $request, Subscription $subscription): RedirectResponse
    {
        abort_if($subscription->user_id !== $request->user()->id, 403);

        try {
            $this->stripe->cancelSubscription($subscription->stripe_subscription_id, atPeriodEnd: true);
        } catch (ApiErrorException $e) {
            Log::channel('stripe')->error('Failed to cancel subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['subscription' => 'Unable to cancel subscription. Please try again.']);
        }

        $subscription->update(['cancel_at_period_end' => true]);

        return back();
    }
}
