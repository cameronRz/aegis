<?php

use App\Enum\Tier;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use App\Services\StripeService;
use Mockery\MockInterface;
use Stripe\Customer;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->create(['tier' => Tier::Admin]);

    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('createCustomer')->andReturn(Customer::constructFrom(['id' => 'cus_test123']));
    });
});

// --- store ---

it('admin can send an invitation and the email is queued', function () {
    Mail::fake();

    actingAs($this->admin)
        ->post(route('admin.invitations.store'), ['email' => 'newclient@example.com'])
        ->assertRedirect();

    $invitation = Invitation::where('email', 'newclient@example.com')->firstOrFail();
    expect($invitation->token)->toHaveLength(64)
        ->and($invitation->invited_by)->toBe($this->admin->id)
        ->and($invitation->accepted_at)->toBeNull();

    Mail::assertQueued(InvitationMail::class, fn ($mail) => $mail->invitation->is($invitation));
});

it('cannot invite an email that already has an account', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    actingAs($this->admin)
        ->post(route('admin.invitations.store'), ['email' => 'existing@example.com'])
        ->assertSessionHasErrors('email');
});

it('cannot send a duplicate invitation to the same pending email', function () {
    Invitation::factory()->create(['email' => 'pending@example.com']);

    actingAs($this->admin)
        ->post(route('admin.invitations.store'), ['email' => 'pending@example.com'])
        ->assertSessionHasErrors('email');
});

it('non-admin cannot send invitations', function () {
    $user = User::factory()->create(['tier' => Tier::User]);

    actingAs($user)
        ->post(route('admin.invitations.store'), ['email' => 'someone@example.com'])
        ->assertForbidden();
});

// --- show ---

it('show renders the accept page for a valid token', function () {
    $invitation = Invitation::factory()->create();

    $this->get(route('invitations.show', $invitation->token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invitations/accept')
            ->where('email', $invitation->email)
            ->where('token', $invitation->token)
        );
});

it('show returns 404 for an unknown token', function () {
    $this->get(route('invitations.show', 'nonexistenttoken'))->assertNotFound();
});

it('show returns 404 for an already accepted invitation', function () {
    $invitation = Invitation::factory()->create(['accepted_at' => now()]);

    $this->get(route('invitations.show', $invitation->token))->assertNotFound();
});

it('show returns 410 for an expired invitation', function () {
    $invitation = Invitation::factory()->create(['created_at' => now()->subDays(8)]);

    $this->get(route('invitations.show', $invitation->token))->assertGone();
});

// --- accept ---

it('accepting a valid invitation creates a user and logs them in', function () {
    $invitation = Invitation::factory()->create(['email' => 'newclient@example.com']);

    $this->post(route('invitations.accept', $invitation->token), [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'password' => 'Password1!Password1!',
        'password_confirmation' => 'Password1!Password1!',
    ])->assertRedirect(route('dashboard'));

    $user = User::where('email', 'newclient@example.com')->firstOrFail();
    expect($user->first_name)->toBe('Jane')
        ->and($user->last_name)->toBe('Smith')
        ->and($user->tier)->toBe(Tier::User);

    $invitation->refresh();
    expect($invitation->accepted_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
});

it('accept returns 404 for an already accepted invitation', function () {
    $invitation = Invitation::factory()->create(['accepted_at' => now()]);

    $this->post(route('invitations.accept', $invitation->token), [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'password' => 'Password1!Password1!',
        'password_confirmation' => 'Password1!Password1!',
    ])->assertNotFound();
});

it('accept returns 410 for an expired invitation', function () {
    $invitation = Invitation::factory()->create(['created_at' => now()->subDays(8)]);

    $this->post(route('invitations.accept', $invitation->token), [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'password' => 'Password1!Password1!',
        'password_confirmation' => 'Password1!Password1!',
    ])->assertGone();
});

// --- resend ---

it('admin can resend an invitation with a fresh token', function () {
    Mail::fake();

    $invitation = Invitation::factory()->create(['created_at' => now()->subDays(5)]);
    $originalToken = $invitation->token;

    actingAs($this->admin)
        ->post(route('admin.invitations.resend', $invitation))
        ->assertRedirect();

    $invitation->refresh();
    expect($invitation->token)->not->toBe($originalToken)
        ->and($invitation->created_at->isToday())->toBeTrue();

    Mail::assertQueued(InvitationMail::class);
});

// --- destroy ---

it('admin can revoke a pending invitation', function () {
    $invitation = Invitation::factory()->create();

    actingAs($this->admin)
        ->delete(route('admin.invitations.destroy', $invitation))
        ->assertRedirect();

    $this->assertDatabaseMissing('invitations', ['id' => $invitation->id]);
});
