<?php

namespace App\Models;

use App\Enums\Role;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Tài khoản người dùng nội bộ (không có tài khoản cho người nhận suất ăn).
 *
 * @property Role $role
 * @property bool $is_active
 */
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Email đặt lại mật khẩu bằng tiếng Việt, gửi nền qua queue.
     *
     * @param  string  $token  token đặt lại mật khẩu
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Tài khoản đang hoạt động và có vai trò từ mức cho trước trở lên.
     *
     * @param  Role  $role  vai trò tối thiểu
     */
    public function hasRoleAtLeast(Role $role): bool
    {
        return $this->is_active && $this->role->atLeast($role);
    }

    /**
     * Quản trị hệ thống (quản lý tài khoản).
     */
    public function isAdmin(): bool
    {
        return $this->hasRoleAtLeast(Role::Admin);
    }

    /**
     * Điều phối viên trở lên (quản lý chương trình, bài viết).
     */
    public function isCoordinator(): bool
    {
        return $this->hasRoleAtLeast(Role::Coordinator);
    }

    /**
     * Người ghi nhận trở lên (nhập số suất, chi phí, xem báo cáo).
     */
    public function isRecorder(): bool
    {
        return $this->hasRoleAtLeast(Role::Recorder);
    }

    /**
     * Chỉ tài khoản chưa bị khóa.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Tài khoản có thể làm người điều phối một chương trình (đang hoạt động, điều phối viên trở lên).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeCoordinators(Builder $query): Builder
    {
        return $query->active()->whereIn('role', Role::valuesAtLeast(Role::Coordinator));
    }

    /**
     * Công việc được giao cho người này.
     *
     * @return HasMany<Task, $this>
     */
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }
}
