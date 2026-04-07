<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameTile>
 */
class GameTileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tile_index' => $this->faker->unique()->numberBetween(0, 9),
            'prize_id' => null,
            'tile_image' => $this->faker->imageUrl(100, 100, 'game-tile'),
            'revealed_at' => null,
        ];
    }
}
