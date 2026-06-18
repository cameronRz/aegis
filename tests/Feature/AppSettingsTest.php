<?php

use App\Enum\SettingKey;
use App\Enum\Tier;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->create(['tier' => Tier::Admin]);
    $this->user = User::factory()->create(['tier' => Tier::User]);
});

// ── AppSetting model ─────────────────────────────────────────────────────────

test('AppSetting::get returns default when key is missing', function () {
    $value = AppSetting::get(SettingKey::SupportChatEnabled, true);

    expect($value)->toBeTrue();
});

test('AppSetting::set persists value and invalidates cache', function () {
    AppSetting::set(SettingKey::SupportChatEnabled, false);

    // Clear cache to force DB read
    Cache::forget('app_settings.'.SettingKey::SupportChatEnabled->value);

    $value = AppSetting::get(SettingKey::SupportChatEnabled, true);

    expect($value)->toBeFalse();
});

test('AppSetting::get returns cached value on second call', function () {
    AppSetting::set(SettingKey::AiAssistantEnabled, false);

    $first = AppSetting::get(SettingKey::AiAssistantEnabled, true);
    $second = AppSetting::get(SettingKey::AiAssistantEnabled, true);

    expect($first)->toBeFalse();
    expect($second)->toBeFalse();
});

test('AppSetting::set updates an existing key', function () {
    AppSetting::set(SettingKey::SupportChatEnabled, false);
    AppSetting::set(SettingKey::SupportChatEnabled, true);

    Cache::forget('app_settings.'.SettingKey::SupportChatEnabled->value);

    expect(AppSetting::get(SettingKey::SupportChatEnabled, false))->toBeTrue();
    expect(AppSetting::where('key', SettingKey::SupportChatEnabled->value)->count())->toBe(1);
});

// ── Admin SettingController ──────────────────────────────────────────────────

test('admin can view the features settings page', function () {
    actingAs($this->admin)
        ->get(route('admin.settings.features'))
        ->assertOk();
});

test('non-admin cannot access the features settings page', function () {
    actingAs($this->user)
        ->get(route('admin.settings.features'))
        ->assertForbidden();
});

test('guest is redirected from the features settings page', function () {
    $this->get(route('admin.settings.features'))
        ->assertRedirect(route('login'));
});

test('admin can toggle support chat enabled', function () {
    actingAs($this->admin)
        ->patch(route('admin.settings.features.update'), ['support_chat_enabled' => false])
        ->assertRedirect();

    Cache::forget('app_settings.'.SettingKey::SupportChatEnabled->value);
    expect(AppSetting::get(SettingKey::SupportChatEnabled, true))->toBeFalse();
});

test('admin can toggle AI assistant enabled', function () {
    actingAs($this->admin)
        ->patch(route('admin.settings.features.update'), ['ai_assistant_enabled' => false])
        ->assertRedirect();

    Cache::forget('app_settings.'.SettingKey::AiAssistantEnabled->value);
    expect(AppSetting::get(SettingKey::AiAssistantEnabled, true))->toBeFalse();
});

test('non-admin cannot update feature flags', function () {
    actingAs($this->user)
        ->patch(route('admin.settings.features.update'), ['support_chat_enabled' => false])
        ->assertForbidden();
});

// ── Feature flag enforcement in AiConversationController ────────────────────

test('AI controller returns 503 when AI assistant is disabled', function () {
    AppSetting::set(SettingKey::AiAssistantEnabled, false);

    actingAs($this->admin)
        ->get(route('ai.index'))
        ->assertStatus(503);
});
