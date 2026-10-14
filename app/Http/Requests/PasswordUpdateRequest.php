<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** Người dùng tự đổi mật khẩu của mình (phải nhập đúng mật khẩu hiện tại). */
class PasswordUpdateRequest extends FormRequest
{
    /**
     * Chỉ người đã đăng nhập (route đã có middleware auth).
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => 'mật khẩu hiện tại',
            'password' => 'mật khẩu mới',
        ];
    }
}
