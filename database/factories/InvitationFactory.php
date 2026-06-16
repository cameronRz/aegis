<?php

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'token' => bin2hex(random_bytes(32)),
            'invited_by' => User::factory(),
            'role' => 'user',
            'accepted_at' => null,
        ];
    }
}
