<?php

namespace Database\Factories;

use App\Models\DiscogsAnalysis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DiscogsAnalysis>
 */
class DiscogsAnalysisFactory extends Factory
{
    protected $model = DiscogsAnalysis::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $have = $this->faker->numberBetween(10, 5000);
        $want = $this->faker->numberBetween(5, 3000);

        return [
            'discogs_id' => $this->faker->unique()->numberBetween(100000, 99999999),
            'title' => $this->faker->words(3, true),
            'artist_name' => $this->faker->name(),
            'year' => $this->faker->numberBetween(1960, 2024),
            'country' => $this->faker->country(),
            'label' => $this->faker->company(),
            'catalog_number' => strtoupper($this->faker->lexify('???')) . '-' . $this->faker->numberBetween(100, 999),
            'genres' => $this->faker->randomElements(['Electronic', 'Rock', 'Jazz', 'Hip Hop', 'Classical'], 2),
            'styles' => $this->faker->randomElements(['Techno', 'House', 'Ambient', 'Minimal', 'Dub'], 2),
            'format' => $this->faker->randomElement(['Vinyl', 'CD', 'Cassette']),
            'have' => $have,
            'want' => $want,
            'rating_average' => $this->faker->randomFloat(2, 3, 5),
            'rating_count' => $this->faker->numberBetween(0, 500),
            'num_for_sale' => $this->faker->numberBetween(0, 100),
            'lowest_price' => $this->faker->randomFloat(2, 5, 200),
            'lowest_price_currency' => 'USD',
            'demand_ratio' => $have > 0 ? round($want / $have, 4) : 0,
            'is_rare' => $have < 100,
            'is_in_demand' => $want > $have,
            'cover_image' => $this->faker->imageUrl(600, 600, 'music'),
            'thumb' => $this->faker->imageUrl(150, 150, 'music'),
            'fetched_at' => now(),
            'has_youtube' => false,
            'youtube_tracks' => [],
        ];
    }

    /**
     * With AI analysis
     */
    public function analyzed(): static
    {
        return $this->state(fn (array $attributes) => [
            'ai_score' => $this->faker->numberBetween(30, 95),
            'ai_recommendation' => $this->faker->randomElement(['BUY', 'HOLD', 'AVOID']),
            'ai_analysis' => $this->faker->paragraph(),
            'recommended_price_min' => $this->faker->randomFloat(2, 10, 50),
            'recommended_price_max' => $this->faker->randomFloat(2, 60, 200),
            'last_refreshed_at' => now(),
        ]);
    }

    /**
     * With YouTube tracks
     */
    public function withYoutube(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_youtube' => true,
            'youtube_tracks' => [
                [
                    'video_id' => $this->faker->regexify('[a-zA-Z0-9]{11}'),
                    'title' => $this->faker->words(5, true),
                    'channel' => $this->faker->company(),
                    'relevance' => $this->faker->numberBetween(50, 100),
                ],
            ],
            'youtube_fetched_at' => now(),
        ]);
    }

    /**
     * Rare vinyl
     */
    public function rare(): static
    {
        return $this->state(fn (array $attributes) => [
            'have' => $this->faker->numberBetween(5, 99),
            'is_rare' => true,
        ]);
    }

    /**
     * High demand vinyl
     */
    public function highDemand(): static
    {
        $have = $this->faker->numberBetween(50, 200);
        $want = $have * $this->faker->randomFloat(1, 2, 5);

        return $this->state(fn (array $attributes) => [
            'have' => $have,
            'want' => (int) $want,
            'demand_ratio' => round($want / $have, 4),
            'is_in_demand' => true,
        ]);
    }

    /**
     * Trending vinyl
     */
    public function trending(): static
    {
        $previousWant = $this->faker->numberBetween(50, 200);
        $currentWant = (int) ($previousWant * 1.3); // 30% increase

        return $this->state(fn (array $attributes) => [
            'want' => $currentWant,
            'previous_want' => $previousWant,
            'previous_have' => $attributes['have'] ?? 100,
        ]);
    }
}
