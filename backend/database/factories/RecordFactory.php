<?php

namespace Database\Factories;

use App\Models\Record;
use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Record>
 */
class RecordFactory extends Factory
{
    protected $model = Record::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $genres = ['Rock', 'Jazz', 'Blues', 'Electronic', 'Classical', 'Hip-Hop', 'Pop', 'Metal', 'Reggae', 'Folk'];

        return [
            'title' => fake()->sentence(3),
            'artist_id' => Artist::factory(),
            'artist_name' => null,
            'label' => fake()->company(),
            'genre' => fake()->randomElement($genres),
            'year' => fake()->numberBetween(1950, date('Y')),
            'description' => fake()->paragraph(),
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the record has no artist relation (legacy).
     */
    public function withoutArtist(): static
    {
        return $this->state(fn (array $attributes) => [
            'artist_id' => null,
            'artist_name' => fake()->name(),
        ]);
    }
}
