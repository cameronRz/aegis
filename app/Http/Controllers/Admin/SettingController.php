<?php

namespace App\Http\Controllers\Admin;

use App\Enum\SettingKey;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function features(): Response
    {
        return Inertia::render('admin/settings/features', [
            'features' => [
                'aiAssistantEnabled' => AppSetting::get(SettingKey::AiAssistantEnabled, true),
                'supportChatEnabled' => AppSetting::get(SettingKey::SupportChatEnabled, true),
            ],
        ]);
    }

    public function updateFeatures(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ai_assistant_enabled' => ['nullable', 'boolean'],
            'support_chat_enabled' => ['nullable', 'boolean'],
        ]);

        foreach ($validated as $key => $value) {
            if ($value !== null) {
                AppSetting::set(SettingKey::from($key), $value);
            }
        }

        return redirect()->back();
    }
}
