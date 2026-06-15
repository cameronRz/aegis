<?php

namespace App\Http\Controllers;

use App\Services\StripeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;

class BillingPortalController extends Controller
{
    public function __construct(private readonly StripeService $stripe) {}

    public function redirect(Request $request): Response
    {
        $user = $request->user();

        abort_if(! $user->stripe_customer_id, 422);

        try {
            $session = $this->stripe->createBillingPortalSession(
                $user->stripe_customer_id,
                returnUrl: route('subscriptions'),
            );
        } catch (ApiErrorException $e) {
            return back()->withErrors(['billing' => 'Unable to open billing portal. Please try again.']);
        }

        return Inertia::location($session->url);
    }
}
