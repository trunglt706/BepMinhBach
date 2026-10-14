<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\CookingBatch;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'cooking_batch_id' => CookingBatch::factory(),
            'title' => fake()->randomElement(['Đi chợ', 'Sơ chế rau', 'Nấu cơm', 'Chia suất', 'Dọn bếp']),
            'status' => TaskStatus::Todo,
            'due_at' => now()->addDay(),
            'created_by' => fn (array $attrs) => CookingBatch::find($attrs['cooking_batch_id'])->coordinator_id,
        ];
    }
}
