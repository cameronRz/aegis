<?php

use App\Enum\MessageRole;
use App\Enum\Tier;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use OpenAI\Contracts\ClientContract;
use OpenAI\Responses\Chat\CreateStreamedResponse;
use OpenAI\Responses\Embeddings\CreateResponse as EmbeddingCreateResponse;
use OpenAI\Testing\ClientFake;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->withoutVite();
    Queue::fake();

    $this->admin = User::factory()->create(['tier' => Tier::Admin]);

    // Client user with use_ai_assistant permission
    $permission = Permission::create([
        'name' => 'use_ai_assistant',
        'display_name' => 'Use AI Assistant',
        'description' => null,
    ]);
    $role = Role::factory()->create();
    $role->permissions()->sync([$permission->id]);
    $this->clientUser = User::factory()->create(['tier' => Tier::User]);
    $this->clientUser->roles()->attach($role->id, ['assigned_by' => null]);

    // User without the permission
    $this->blockedUser = User::factory()->create(['tier' => Tier::User]);
});

// ── AiConversationController ─────────────────────────────────────────────────

test('client with permission can view AI index and a conversation is created', function () {
    actingAs($this->clientUser)
        ->get(route('ai.index'))
        ->assertOk();

    expect(AiConversation::where('user_id', $this->clientUser->id)->exists())->toBeTrue();
});

test('admin can view AI index', function () {
    actingAs($this->admin)
        ->get(route('ai.index'))
        ->assertOk();
});

test('user without permission is forbidden from AI index', function () {
    actingAs($this->blockedUser)
        ->get(route('ai.index'))
        ->assertForbidden();
});

test('returning to AI index reuses the existing conversation', function () {
    $conversation = AiConversation::factory()->create(['user_id' => $this->clientUser->id]);

    actingAs($this->clientUser)
        ->get(route('ai.index'))
        ->assertOk();

    expect(AiConversation::where('user_id', $this->clientUser->id)->count())->toBe(1);
    expect(AiConversation::where('user_id', $this->clientUser->id)->first()->id)->toBe($conversation->id);
});

test('client can create a new conversation', function () {
    AiConversation::factory()->create(['user_id' => $this->clientUser->id]);

    actingAs($this->clientUser)
        ->post(route('ai.conversations.store'))
        ->assertRedirect(route('ai.index'));

    expect(AiConversation::where('user_id', $this->clientUser->id)->count())->toBe(2);
});

test('user without permission cannot create a conversation', function () {
    actingAs($this->blockedUser)
        ->post(route('ai.conversations.store'))
        ->assertForbidden();
});

// ── AiMessageController ──────────────────────────────────────────────────────

test('user without permission cannot post a message', function () {
    $conversation = AiConversation::factory()->create(['user_id' => $this->blockedUser->id]);

    actingAs($this->blockedUser)
        ->post(route('ai.messages.store'), [
            'conversation_id' => $conversation->id,
            'content' => 'Hello',
        ])
        ->assertForbidden();
});

test('client cannot post to another users conversation', function () {
    $otherConversation = AiConversation::factory()->create();

    actingAs($this->clientUser)
        ->post(route('ai.messages.store'), [
            'conversation_id' => $otherConversation->id,
            'content' => 'Hello',
        ])
        ->assertSessionHasErrors(['conversation_id']);
});

test('message store validates required fields', function () {
    actingAs($this->clientUser)
        ->post(route('ai.messages.store'), [])
        ->assertSessionHasErrors(['conversation_id', 'content']);
});

test('message store returns SSE stream and persists user and assistant messages', function () {
    $conversation = AiConversation::factory()->create(['user_id' => $this->clientUser->id]);

    $fakeClient = new ClientFake([
        EmbeddingCreateResponse::fake(),
        CreateStreamedResponse::fake(),
    ]);
    $this->app->instance(ClientContract::class, $fakeClient);

    $response = actingAs($this->clientUser)
        ->post(route('ai.messages.store'), [
            'conversation_id' => $conversation->id,
            'content' => 'What is the return policy?',
        ]);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
    $response->streamedContent(); // trigger streaming closure so assistant message is written

    expect(AiMessage::where('conversation_id', $conversation->id)
        ->where('role', MessageRole::User)->exists()
    )->toBeTrue();

    expect(AiMessage::where('conversation_id', $conversation->id)
        ->where('role', MessageRole::Assistant)->exists()
    )->toBeTrue();
});

test('SSE stream output includes sources event followed by delta events', function () {
    $conversation = AiConversation::factory()->create(['user_id' => $this->clientUser->id]);

    $fakeClient = new ClientFake([
        EmbeddingCreateResponse::fake(),
        CreateStreamedResponse::fake(),
    ]);
    $this->app->instance(ClientContract::class, $fakeClient);

    $response = actingAs($this->clientUser)
        ->post(route('ai.messages.store'), [
            'conversation_id' => $conversation->id,
            'content' => 'Test question',
        ]);

    $content = $response->streamedContent();

    expect($content)->toContain('data: ');
    expect($content)->toContain('"type":"sources"');
    expect($content)->toContain('[DONE]');
});

// ── Rate limiting ─────────────────────────────────────────────────────────────

test('ai message endpoint is rate limited to 30 requests per minute', function () {
    $route = Route::getRoutes()->getByName('ai.messages.store');

    expect($route->middleware())->toContain('throttle:30,1');
});
