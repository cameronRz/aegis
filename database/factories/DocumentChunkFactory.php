<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentChunk>
 */
class DocumentChunkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'content' => $this->faker->paragraphs(2, true),
            'embedding' => null,
            'chunk_index' => $this->faker->numberBetween(0, 10),
        ];
    }
}
