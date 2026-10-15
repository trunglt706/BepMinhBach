<?php

namespace Database\Factories;

use App\Enums\SponsorType;
use App\Models\Sponsor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sponsor>
 */
class SponsorFactory extends Factory
{
    protected $model = Sponsor::class;

    public function definition(): array
    {
        return [
            'name' => 'Đơn vị thử '.fake()->unique()->numberBetween(1, 99999),
            'type' => fake()->randomElement(SponsorType::cases()),
            'description' => 'Mô tả thử.',
            'website' => null,
            'is_active' => true,
        ];
    }
}
