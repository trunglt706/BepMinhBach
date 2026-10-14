<?php

namespace Database\Factories;

use App\Models\CookingBatch;
use App\Models\ExpenseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseItem>
 */
class ExpenseItemFactory extends Factory
{
    protected $model = ExpenseItem::class;

    public function definition(): array
    {
        return [
            'cooking_batch_id' => CookingBatch::factory(),
            'name' => fake()->randomElement(['Rau củ', 'Gạo', 'Gas', 'Hộp đựng']),
            'amount_vnd' => fake()->numberBetween(10, 500) * 1000,
            'recorded_by' => fn (array $attrs) => CookingBatch::find($attrs['cooking_batch_id'])->coordinator_id,
        ];
    }
}
