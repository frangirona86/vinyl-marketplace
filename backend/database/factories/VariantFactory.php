<?php

namespace Database\Factories;

use App\Models\Variant;
use App\Models\Record;
use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Variant>
 */
class VariantFactory extends Factory
{
    protected $model = Variant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $conditions = ['Mint', 'Near Mint', 'Very Good Plus', 'Very Good', 'Good', 'Fair', 'Poor'];
        $colors = ['Black', 'Red', 'Blue', 'Green', 'White', 'Yellow', 'Orange', 'Purple', 'Clear', 'Splatter'];

        return [
            'record_id' => Record::factory(),
            'artist_id' => Artist::factory(),
            'condition' => fake()->randomElement($conditions),
            'color' => fake()->randomElement($colors),
            'price' => fake()->randomFloat(2, 9.99, 299.99),
            'stock' => fake()->numberBetween(0, 50),
            'photos' => null,
        ];
    }

    /**
     * Indicate that the variant is in stock.
     */
    public function inStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => fake()->numberBetween(1, 50),
        ]);
    }

    /**
     * Indicate that the variant is out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }
}
