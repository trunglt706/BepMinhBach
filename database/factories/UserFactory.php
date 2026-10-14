<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Dữ liệu giả, không dùng tên/thông tin của người thật.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => 'Người dùng thử '.Str::upper(Str::random(4)),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Volunteer,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }

    public function admin(): static
    {
        return $this->role(Role::Admin);
    }

    public function coordinator(): static
    {
        return $this->role(Role::Coordinator);
    }

    public function recorder(): static
    {
        return $this->role(Role::Recorder);
    }

    public function volunteer(): static
    {
        return $this->role(Role::Volunteer);
    }

    public function locked(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
