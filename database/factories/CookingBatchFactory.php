<?php

namespace Database\Factories;

use App\Enums\BatchStatus;
use App\Models\CookingBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CookingBatch>
 */
class CookingBatchFactory extends Factory
{
    protected $model = CookingBatch::class;

    public function definition(): array
    {
        return [
            'name' => 'Chương trình thử '.fake()->unique()->numberBetween(1, 99999),
            'starts_on' => today()->addDays(fake()->numberBetween(1, 14))->toDateString(),
            'location_text' => 'Bếp thử nghiệm',
            'expected_servings' => fake()->numberBetween(50, 300),
            'coordinator_id' => User::factory()->coordinator(),
            'created_by' => fn (array $attrs) => $attrs['coordinator_id'],
            'status' => BatchStatus::Planned,
        ];
    }

    public function status(BatchStatus $status): static
    {
        return $this->state(fn () => array_filter([
            'status' => $status,
            'completed_at' => $status === BatchStatus::Completed ? now() : null,
            'cancelled_at' => $status === BatchStatus::Cancelled ? now() : null,
        ], fn ($v) => $v !== null));
    }
}
