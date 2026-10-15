<?php

namespace App\Http\Requests;

use App\Enums\NotificationDriver;
use App\Models\BatchNotificationChannel;
use App\Models\CookingBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Thêm/sửa kênh thông báo của chương trình.
 * - Chỉ kiểm tra thông số của loại kênh đang chọn (form có đủ ô của mọi loại khi tắt JS).
 * - Sửa: ô bí mật để trống = giữ giá trị đã lưu (không bao giờ điền lại token vào form).
 * - Link tham gia: chỉ https và đúng tên miền của dịch vụ.
 */
class NotificationChannelRequest extends FormRequest
{
    /**
     * Điều phối viên trở lên (CookingBatchPolicy::manageNotifications).
     */
    public function authorize(): bool
    {
        Gate::authorize('manageNotifications', $this->batch());

        return true;
    }

    /**
     * Chương trình của yêu cầu (thêm: từ route; sửa: từ kênh).
     */
    public function batch(): CookingBatch
    {
        $channel = $this->route('channel');

        return $channel instanceof BatchNotificationChannel ? $channel->batch : $this->route('batch');
    }

    /**
     * Kênh đang sửa (null khi thêm mới).
     */
    public function channel(): ?BatchNotificationChannel
    {
        $channel = $this->route('channel');

        return $channel instanceof BatchNotificationChannel ? $channel : null;
    }

    /**
     * Loại kênh: khi sửa luôn giữ loại cũ (đổi loại = xóa rồi thêm kênh mới).
     */
    public function driver(): ?NotificationDriver
    {
        return $this->channel()?->driver ?? NotificationDriver::tryFrom((string) $this->input('driver'));
    }

    /**
     * Cắt khoảng trắng các ô thông số và link.
     */
    protected function prepareForValidation(): void
    {
        $settings = [];
        foreach ((array) $this->input('settings', []) as $key => $value) {
            $settings[$key] = is_string($value) ? trim($value) : $value;
        }

        $driver = $this->channel()?->driver->value ?? $this->input('driver');
        // Mỗi loại kênh có ô link riêng (join_urls[slack]…) để khi tắt JS các ô không ghi đè nhau.
        $joinUrls = (array) $this->input('join_urls', []);
        $joinUrl = is_string($driver) && isset($joinUrls[$driver]) ? $joinUrls[$driver] : $this->input('join_url');

        $this->merge([
            'settings' => $settings,
            'join_url' => trim((string) $joinUrl) ?: null,
            'driver' => $driver,
        ]);
    }

    /**
     * Quy tắc kiểm tra theo loại kênh.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $driver = $this->driver();
        $channel = $this->channel();
        $batch = $this->batch();

        $rules = [
            'driver' => ['required', Rule::enum(NotificationDriver::class),
                Rule::unique('batch_notification_channels', 'driver')
                    ->where('cooking_batch_id', $batch->id)
                    ->ignore($channel?->id)],
            'join_url' => ['nullable', 'string', 'max:500', 'url:https'],
            'is_enabled' => ['nullable', 'boolean'],
            'settings' => ['array'],
        ];
        if ($driver) {
            $rules['join_url'][] = 'regex:'.$driver->joinUrlPattern();
            foreach ($driver->fields() as $key => $field) {
                // Sửa kênh: ô bí mật được phép để trống (giữ giá trị cũ)
                $keep = $channel && $field['secret'] && $channel->setting($key) !== null;
                $rules['settings.'.$key] = [$keep ? 'nullable' : 'required', 'string', 'max:300', ...$field['rules']];
            }
        }

        return $rules;
    }

    /**
     * Thông số đã kiểm tra, gộp với giá trị bí mật cũ khi ô để trống; chỉ giữ khóa của loại kênh đang chọn.
     *
     * @return array<string, string>
     */
    public function settings(): array
    {
        $driver = $this->driver();
        $input = (array) $this->validated('settings', []);
        $settings = [];
        foreach ($driver->fields() as $key => $field) {
            $value = $input[$key] ?? null;
            $settings[$key] = ($value === null || $value === '') ? (string) $this->channel()?->setting($key) : (string) $value;
        }

        return $settings;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['driver' => 'loại kênh', 'join_url' => 'link tham gia kênh'];
        foreach (NotificationDriver::cases() as $driver) {
            foreach ($driver->fields() as $key => $field) {
                $attributes['settings.'.$key] = $field['label'];
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $driver = $this->driver();

        return [
            'driver.unique' => 'Chương trình này đã có kênh '.($driver?->label() ?? '').'. Hãy sửa kênh đó thay vì thêm mới.',
            'join_url.url' => 'Link tham gia phải là địa chỉ https://.',
            'join_url.regex' => 'Link tham gia phải là link của '.($driver?->label() ?? 'dịch vụ đã chọn').', ví dụ '.($driver?->joinUrlExample() ?? '').'.',
            'settings.*.regex' => ':attribute chưa đúng định dạng. Xem hướng dẫn bên dưới ô nhập.',
        ];
    }
}
