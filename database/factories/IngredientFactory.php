<?php

namespace Database\Factories;

use App\Models\CookingBatch;
use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    protected $model = Ingredient::class;

    public function definition(): array
    {
        return [
            'cooking_batch_id' => CookingBatch::factory(),
            'name' => fake()->randomElement(['Gạo', 'Đậu hũ', 'Rau muống', 'Nấm', 'Dầu ăn', 'Nước tương']),
            'expected_quantity' => fake()->randomFloat(1, 1, 50),
            'unit' => fake()->randomElement(Ingredient::UNITS),
            'created_by' => fn (array $attrs) => CookingBatch::find($attrs['cooking_batch_id'])->coordinator_id,
        ];
    }
}
