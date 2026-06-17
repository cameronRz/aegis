<?php

namespace App\Enum;

enum SettingKey: string
{
    case AiAssistantEnabled = 'ai_assistant_enabled';
    case SupportChatEnabled = 'support_chat_enabled';
}
