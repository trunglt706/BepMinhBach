<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function hasRoleAtLeast(Role $role): bool
    {
        return $this->is_active && $this->role->atLeast($role);
    }

    public function isAdmin(): bool
    {
        return $this->hasRoleAtLeast(Role::Admin);
    }

    public function isCoordinator(): bool
    {
        return $this->hasRoleAtLeast(Role::Coordinator);
    }

    public function isRecorder(): bool
    {
        return $this->hasRoleAtLeast(Role::Recorder);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Tài khoản có thể làm người điều phối một đợt nấu. */
    public function scopeCoordinators(Builder $query): Builder
    {
        return $query->active()->whereIn('role', [Role::Coordinator->value, Role::Admin->value]);
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }
}
