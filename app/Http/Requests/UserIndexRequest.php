<?php

namespace App\Http\Requests;

use App\Enums\AccountState;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Bộ lọc danh sách tài khoản. Giá trị lạ trong URL bị bỏ qua (về "Tất cả") thay vì báo lỗi,
 * vì đây là liên kết người dùng có thể sửa tay.
 */
class UserIndexRequest extends FormRequest
{
    /**
     * Chỉ quản trị viên (xem UserPolicy::viewAny).
     */
    public function authorize(): bool
    {
        Gate::authorize('viewAny', User::class);

        return true;
    }

    /**
     * Chuẩn hóa: cắt khoảng trắng, bỏ giá trị không thuộc enum.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => mb_substr(trim((string) $this->query('q', '')), 0, 100),
            'role' => Role::tryFrom((string) $this->query('role'))?->value,
            'state' => AccountState::tryFrom((string) $this->query('state'))?->value,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string'],
            'state' => ['nullable', 'string'],
        ];
    }

    /**
     * Từ khóa tìm theo tên/email ("" nếu không lọc).
     */
    public function keyword(): string
    {
        return (string) $this->validated('q');
    }

    /**
     * Vai trò cần lọc (null = tất cả).
     */
    public function role(): ?Role
    {
        return Role::tryFrom((string) $this->validated('role'));
    }

    /**
     * Trạng thái cần lọc (null = tất cả).
     */
    public function state(): ?AccountState
    {
        return AccountState::tryFrom((string) $this->validated('state'));
    }
}
