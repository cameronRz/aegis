<?php

namespace Database\Factories;

use App\Enum\MessageRole;
use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMessage>
 */
class AiMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => AiConversation::factory(),
            'role' => MessageRole::User,
            'content' => $this->faker->paragraph(),
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn () => ['role' => MessageRole::Assistant]);
    }
}
