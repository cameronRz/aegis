<?php

namespace App\Http\Controllers;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Random\RandomException;

class InvitationController extends Controller
{
    public function index(): InertiaResponse
    {
        $invitations = Invitation::pending()
            ->with('inviter:id,first_name,last_name')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/invitations/index', [
            'invitations' => $invitations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = $request->input('email');

        if (User::where('email', $email)->exists()) {
            return back()->withErrors(['email' => 'A user with this email address already exists.']);
        }

        if (Invitation::where('email', $email)->pending()->exists()) {
            return back()->withErrors(['email' => 'An invitation has already been sent to this email address.']);
        }

        $invitation = Invitation::create([
            'email' => $email,
            'token' => bin2hex(random_bytes(32)),
            'invited_by' => $request->user()->id,
            'role' => 'user',
        ]);

        Mail::to($email)->queue(new InvitationMail($invitation));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invitation sent.']);

        return back();
    }

    /**
     * @throws RandomException
     */
    public function resend(Invitation $invitation): RedirectResponse
    {
        $invitation->token = bin2hex(random_bytes(32));
        $invitation->created_at = now();
        $invitation->save();

        Mail::to($invitation->email)->queue(new InvitationMail($invitation));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invitation resent.']);

        return back();
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invitation revoked.']);

        return back();
    }

    public function show(string $token): InertiaResponse|Response
    {
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation || $invitation->isAccepted()) {
            abort(404);
        }

        if ($invitation->isExpired()) {
            abort(410);
        }

        return Inertia::render('invitations/accept', [
            'token' => $token,
            'email' => $invitation->email,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function accept(Request $request, string $token, StripeService $stripe): RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation || $invitation->isAccepted()) {
            abort(404);
        }

        if ($invitation->isExpired()) {
            abort(410);
        }

        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $invitation->email,
            'password' => Hash::make($request->input('password')),
        ]);

        try {
            $customer = $stripe->createCustomer($user);
            $user->stripe_customer_id = $customer->id;
            $user->save();
        } catch (\Exception $e) {
            Log::channel('stripe')->error('Failed to create Stripe customer on invite accept', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        $invitation->update(['accepted_at' => now()]);

        $clientRole = Role::where('name', 'Client')->first();
        if ($clientRole) {
            $user->roles()->attach($clientRole->id, ['assigned_by' => null]);
        }

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
