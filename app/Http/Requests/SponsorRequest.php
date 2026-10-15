<?php

namespace App\Http\Requests;

use App\Enums\SponsorType;
use App\Models\Sponsor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Thêm / sửa đơn vị tài trợ, hỗ trợ (kèm logo). */
class SponsorRequest extends FormRequest
{
    /**
     * Sửa: SponsorPolicy::update; thêm: SponsorPolicy::create.
     */
    public function authorize(): bool
    {
        $sponsor = $this->route('sponsor');
        $sponsor ? Gate::authorize('update', $sponsor) : Gate::authorize('create', Sponsor::class);

        return true;
    }

    /**
     * Cắt khoảng trắng; website thiếu giao thức thì thêm https://.
     */
    protected function prepareForValidation(): void
    {
        $website = is_string($this->input('website')) ? trim($this->input('website')) : null;
        if ($website !== null && $website !== '' && ! preg_match('#^https?://#i', $website)) {
            $website = 'https://'.$website;
        }

        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'website' => $website ?: null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(SponsorType::class)],
            'description' => ['nullable', 'string', 'max:20000'], // HTML từ trình soạn thảo (được lọc khi lưu)
            // Chỉ http/https: không cho javascript:, data: … vào link công khai.
            'website' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'is_active' => ['boolean'],
            'logo' => ['nullable', 'file', 'max:'.config('bmb.post_images.max_upload_kb'),
                'mimetypes:'.implode(',', config('bmb.post_images.mimes')), 'mimes:jpg,jpeg,png,webp'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'tên đơn vị', 'type' => 'phân loại', 'description' => 'giới thiệu', 'website' => 'website', 'logo' => 'logo'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $type = 'Logo chỉ nhận ảnh JPG, PNG hoặc WebP.';

        return [
            'logo.max' => 'Logo tối đa '.round((int) config('bmb.post_images.max_upload_kb') / 1024).' MB.',
            'logo.mimetypes' => $type, 'logo.mimes' => $type, 'logo.file' => $type,
            'website.url' => 'Website phải là địa chỉ http:// hoặc https:// hợp lệ.',
        ];
    }
}
