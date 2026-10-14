<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Đăng nhập bằng email + mật khẩu. Giới hạn số lần sai nằm ở LoginController. */
class LoginRequest extends FormRequest
{
    /**
     * Ai cũng được gửi form đăng nhập (route đã giới hạn cho khách chưa đăng nhập).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => 'email', 'password' => 'mật khẩu'];
    }

    /**
     * Thông tin đăng nhập dùng cho Auth::validate/attempt (không gồm "remember").
     *
     * @return array{email: string, password: string}
     */
    public function credentials(): array
    {
        return $this->safe()->only(['email', 'password']);
    }
}
