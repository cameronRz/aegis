<?php

use App\Enum\ConversationStatus;
use App\Enum\SettingKey;
use App\Enum\Tier;
use App\Events\NewSupportMessage;
use App\Models\AppSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->withoutVite();

    // Client with use_support permission
    $useSupport = Permission::create(['name' => 'use_support', 'display_name' => 'Use Support', 'description' => null]);
    $clientRole = Role::factory()->create();
    $clientRole->permissions()->sync([$useSupport->id]);
    $this->client = User::factory()->create(['tier' => Tier::User]);
    $this->client->roles()->attach($clientRole->id, ['assigned_by' => null]);

    // Second client
    $this->otherClient = User::factory()->create(['tier' => Tier::User]);
    $this->otherClient->roles()->attach($clientRole->id, ['assigned_by' => null]);

    // Agent with handle_support permission
    $handleSupport = Permission::create(['name' => 'handle_support', 'display_name' => 'Handle Support', 'description' => null]);
    $agentRole = Role::factory()->create();
    $agentRole->permissions()->sync([$handleSupport->id]);
    $this->agent = User::factory()->create(['tier' => Tier::User]);
    $this->agent->roles()->attach($agentRole->id, ['assigned_by' => null]);

    // Admin (bypasses permission checks via isAdmin())
    $this->admin = User::factory()->create(['tier' => Tier::Admin]);

    // User with no relevant permissions
    $this->noPermUser = User::factory()->create(['tier' => Tier::User]);
});

// ── 6.2 Conversation tests ───────────────────────────────────────────────────

test('client can start a conversation and is redirected to show', function () {
    actingAs($this->client)
        ->post(route('support.conversations.store'))
        ->assertRedirect();

    expect(SupportConversation::where('user_id', $this->client->id)->exists())->toBeTrue();
});

test('second store call redirects to existing open conversation without creating a duplicate', function () {
    $existing = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Open,
    ]);

    actingAs($this->client)
        ->post(route('support.conversations.store'))
        ->assertRedirect(route('support.conversations.show', $existing));

    expect(SupportConversation::where('user_id', $this->client->id)->count())->toBe(1);
});

test('client cannot view another client conversation', function () {
    $conversation = SupportConversation::create([
        'user_id' => $this->otherClient->id,
        'status' => ConversationStatus::Open,
    ]);

    actingAs($this->client)
        ->get(route('support.conversations.show', $conversation))
        ->assertForbidden();
});

test('guest is redirected to login when accessing support', function () {
    $this->get(route('support.index'))
        ->assertRedirect(route('login'));
});

test('store returns 503 when support chat is disabled', function () {
    AppSetting::set(SettingKey::SupportChatEnabled, false);

    actingAs($this->client)
        ->post(route('support.conversations.store'))
        ->assertStatus(503);
});

test('client without use_support gets 403 on support index', function () {
    actingAs($this->noPermUser)
        ->get(route('support.index'))
        ->assertForbidden();
});

// ── 6.3 Message + broadcast tests ────────────────────────────────────────────

test('sending a message saves it to the DB and dispatches NewSupportMessage', function () {
    Event::fake();

    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Open,
    ]);

    actingAs($this->client)
        ->post(route('support.messages.store', $conversation), ['content' => 'Hello!'])
        ->assertRedirect();

    expect(SupportMessage::where('conversation_id', $conversation->id)->where('content', 'Hello!')->exists())
        ->toBeTrue();

    Event::assertDispatched(NewSupportMessage::class);
});

test('agent_id is set on the first agent reply', function () {
    Event::fake();

    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Open,
    ]);

    actingAs($this->agent)
        ->post(route('support.messages.store', $conversation), ['content' => 'How can I help?'])
        ->assertRedirect();

    expect($conversation->fresh()->agent_id)->toBe($this->agent->id);
});

test('agent_id is not overwritten on subsequent replies', function () {
    Event::fake();

    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'agent_id' => $this->agent->id,
        'status' => ConversationStatus::Open,
    ]);

    actingAs($this->admin)
        ->post(route('support.messages.store', $conversation), ['content' => 'Second reply'])
        ->assertRedirect();

    expect($conversation->fresh()->agent_id)->toBe($this->agent->id);
});

test('last_message_at is updated when a message is sent', function () {
    Event::fake();

    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Open,
    ]);

    actingAs($this->client)
        ->post(route('support.messages.store', $conversation), ['content' => 'Hi'])
        ->assertRedirect();

    expect($conversation->fresh()->last_message_at)->not->toBeNull();
});

test('closed conversation rejects new messages with 422', function () {
    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Closed,
    ]);

    actingAs($this->client)
        ->post(route('support.messages.store', $conversation), ['content' => 'Hi'])
        ->assertStatus(422);
});

test('message store returns 503 when support chat is disabled', function () {
    AppSetting::set(SettingKey::SupportChatEnabled, false);

    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Open,
    ]);

    actingAs($this->client)
        ->post(route('support.messages.store', $conversation), ['content' => 'Hi'])
        ->assertStatus(503);
});

// ── 1.1 Conversation ownership tests ─────────────────────────────────────────

test('client cannot post a message to another clients conversation', function () {
    $conversation = SupportConversation::create([
        'user_id' => $this->otherClient->id,
        'status' => ConversationStatus::Open,
    ]);

    actingAs($this->client)
        ->post(route('support.messages.store', $conversation), ['content' => 'Hi'])
        ->assertForbidden();
});

test('agent can post a message to any conversation', function () {
    Event::fake();

    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Open,
    ]);

    actingAs($this->agent)
        ->post(route('support.messages.store', $conversation), ['content' => 'Agent reply'])
        ->assertRedirect();

    expect(SupportMessage::where('conversation_id', $conversation->id)->exists())->toBeTrue();
});

// ── 1.2 Route-level middleware tests ─────────────────────────────────────────

test('user without support permission gets 403 on conversation show', function () {
    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Open,
    ]);

    actingAs($this->noPermUser)
        ->get(route('support.conversations.show', $conversation))
        ->assertForbidden();
});

test('unauthenticated user is redirected from conversation show', function () {
    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Open,
    ]);

    $this->get(route('support.conversations.show', $conversation))
        ->assertRedirect(route('login'));
});

// ── 6.4 Authorization tests ───────────────────────────────────────────────────

test('user without handle_support gets 403 on admin support index', function () {
    actingAs($this->noPermUser)
        ->get(route('admin.support.index'))
        ->assertForbidden();
});

test('agent with handle_support can view admin support index', function () {
    actingAs($this->agent)
        ->get(route('admin.support.index'))
        ->assertOk();
});

test('admin can view admin support index', function () {
    actingAs($this->admin)
        ->get(route('admin.support.index'))
        ->assertOk();
});

test('user without permission gets 403 sending a message', function () {
    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Open,
    ]);

    actingAs($this->noPermUser)
        ->post(route('support.messages.store', $conversation), ['content' => 'Hi'])
        ->assertForbidden();
});

// ── 6.5 Read receipt + unread count tests ────────────────────────────────────

test('show marks agent messages as read when client visits', function () {
    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Open,
    ]);

    SupportMessage::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $this->agent->id,
        'content' => 'Hello client',
        'read_at' => null,
    ]);

    actingAs($this->client)
        ->get(route('support.conversations.show', $conversation))
        ->assertOk();

    expect(
        SupportMessage::where('conversation_id', $conversation->id)
            ->where('sender_id', $this->agent->id)
            ->whereNull('read_at')
            ->count()
    )->toBe(0);
});

test('own messages are not marked as read when visiting show', function () {
    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Open,
    ]);

    $ownMessage = SupportMessage::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $this->client->id,
        'content' => 'Hello agent',
        'read_at' => null,
    ]);

    actingAs($this->client)
        ->get(route('support.conversations.show', $conversation))
        ->assertOk();

    expect($ownMessage->fresh()->read_at)->toBeNull();
});

test('agent visiting show marks client messages as read', function () {
    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Open,
    ]);

    SupportMessage::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $this->client->id,
        'content' => 'Need help',
        'read_at' => null,
    ]);

    actingAs($this->agent)
        ->get(route('admin.support.show', $conversation))
        ->assertOk();

    expect(
        SupportMessage::where('conversation_id', $conversation->id)
            ->whereNull('read_at')
            ->count()
    )->toBe(0);
});

// ── 6.6 Close tests ──────────────────────────────────────────────────────────

test('agent can close an open conversation', function () {
    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Open,
    ]);

    actingAs($this->agent)
        ->post(route('admin.support.close', $conversation))
        ->assertRedirect();

    expect($conversation->fresh()->status)->toBe(ConversationStatus::Closed);
});

test('closing an already-closed conversation is idempotent', function () {
    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Closed,
    ]);

    actingAs($this->agent)
        ->post(route('admin.support.close', $conversation))
        ->assertRedirect();

    expect($conversation->fresh()->status)->toBe(ConversationStatus::Closed);
});

// ── Rate limiting ─────────────────────────────────────────────────────────────

test('support message endpoint is rate limited to 60 requests per minute', function () {
    $route = Route::getRoutes()->getByName('support.messages.store');

    expect($route->middleware())->toContain('throttle:60,1');
});

// ── Real-time badge + broadcast channel tests ─────────────────────────────────

test('admin visiting support queue marks all unread client messages as read', function () {
    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Open,
    ]);

    SupportMessage::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $this->client->id,
        'content' => 'Help!',
        'read_at' => null,
    ]);

    actingAs($this->admin)
        ->get(route('admin.support.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('unreadSupportCount', 0));

    expect(SupportMessage::whereNull('read_at')->count())->toBe(0);
});

test('unread badge shows new messages that arrive after queue visit', function () {
    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Open,
    ]);

    // Visit queue first (marks existing messages as read)
    actingAs($this->admin)->get(route('admin.support.index'))->assertOk();

    // New message arrives after the queue visit
    SupportMessage::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $this->client->id,
        'content' => 'New message!',
        'read_at' => null,
    ]);

    // Badge now reflects the new unread message
    actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('unreadSupportCount', 1));
});

test('clicking a conversation clears its contribution to the badge', function () {
    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Open,
    ]);

    SupportMessage::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $this->client->id,
        'content' => 'New message!',
        'read_at' => null,
    ]);

    // Navigate to show: marks the message as read → badge goes to 0
    actingAs($this->admin)
        ->get(route('admin.support.show', $conversation))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('unreadSupportCount', 0));
});

test('NewSupportMessage broadcasts on conversation and admin user channels when client sends', function () {
    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Open,
    ]);

    $message = SupportMessage::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $this->client->id,
        'content' => 'Hello!',
    ]);

    $message->load('conversation');
    $event = new NewSupportMessage($message);
    $channelNames = collect($event->broadcastOn())->map(fn ($ch) => $ch->name)->all();

    expect($channelNames)->toContain("private-conversation.{$conversation->id}");
    expect($channelNames)->toContain("private-App.Models.User.{$this->admin->id}");
    expect($channelNames)->toContain("private-App.Models.User.{$this->agent->id}");
});

test('NewSupportMessage broadcasts on conversation and client user channel when agent sends', function () {
    $conversation = SupportConversation::create([
        'user_id' => $this->client->id,
        'status' => ConversationStatus::Open,
    ]);

    $message = SupportMessage::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $this->agent->id,
        'content' => 'How can I help?',
    ]);

    $message->load('conversation');
    $event = new NewSupportMessage($message);
    $channelNames = collect($event->broadcastOn())->map(fn ($ch) => $ch->name)->all();

    expect($channelNames)->toContain("private-conversation.{$conversation->id}");
    expect($channelNames)->toContain("private-App.Models.User.{$this->client->id}");
    expect($channelNames)->not->toContain("private-App.Models.User.{$this->agent->id}");
});
