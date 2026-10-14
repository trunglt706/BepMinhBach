<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Tạo/sửa tài khoản (quản trị viên). Khi sửa, để trống mật khẩu nghĩa là giữ nguyên. */
class UserRequest extends FormRequest
{
    /**
     * Sửa: UserPolicy::update; tạo: UserPolicy::create.
     */
    public function authorize(): bool
    {
        $user = $this->route('user');
        $user ? Gate::authorize('update', $user) : Gate::authorize('create', User::class);

        return true;
    }

    /**
     * Email chữ thường, bỏ khoảng trắng (tránh tài khoản trùng do khác hoa/thường).
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => [
                'required',
                Rule::enum(Role::class),
                // Admin không tự hạ quyền của chính mình để tránh mất quyền quản trị.
                function (string $attribute, mixed $value, Closure $fail) use ($user) {
                    if ($user && $user->is($this->user()) && $value !== Role::Admin->value) {
                        $fail('Bạn không thể tự hạ vai trò quản trị của chính mình.');
                    }
                },
            ],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'họ tên',
            'email' => 'email',
            'role' => 'vai trò',
            'password' => 'mật khẩu',
        ];
    }
}
