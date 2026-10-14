<?php

namespace Database\Factories;

use App\Enums\ContactTopic;
use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Liên hệ giả cho test (không dùng dữ liệu thật).
 *
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    /**
     * Giá trị mặc định.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'topic' => ContactTopic::Volunteer,
            'name' => 'Khách thử '.fake()->numberBetween(1, 999),
            'contact' => 'khach'.fake()->unique()->numberBetween(1, 99999).'@example.org',
            'message' => 'Tôi muốn tham gia hỗ trợ đợt nấu cuối tuần này.',
            'client_hash' => hash('sha256', fake()->uuid()),
            'sent_on' => today(),
        ];
    }
}
