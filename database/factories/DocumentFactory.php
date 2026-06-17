<?php

namespace Database\Factories;

use App\Enum\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        $filename = $this->faker->word().'.txt';

        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'original_filename' => $filename,
            'disk_path' => 'documents/'.$filename,
            'mime_type' => 'text/plain',
            'status' => DocumentStatus::Processing,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn () => ['status' => DocumentStatus::Ready]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => DocumentStatus::Failed]);
    }
}
