<?php

namespace Database\Factories;

use App\Enums\NotificationDriver;
use App\Models\BatchNotificationChannel;
use App\Models\CookingBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Kênh thông báo giả cho test (token giả, không gửi thật — test dùng Http::fake()).
 *
 * @extends Factory<BatchNotificationChannel>
 */
class BatchNotificationChannelFactory extends Factory
{
    /**
     * Mặc định: kênh Slack đang bật.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cooking_batch_id' => CookingBatch::factory(),
            'driver' => NotificationDriver::Slack,
            'settings' => ['webhook_url' => 'https://hooks.slack.com/services/T000TEST/B000TEST/abcdefghijklmnopqrstuvwx'],
            'join_url' => 'https://join.slack.com/t/bep-thu/shared_invite/zt-test',
            'is_enabled' => true,
        ];
    }

    /**
     * Kênh Telegram.
     */
    public function telegram(): static
    {
        return $this->state([
            'driver' => NotificationDriver::Telegram,
            'settings' => ['bot_token' => '123456789:AAHtestTokenForUnitTestsOnly_abcdefghi', 'chat_id' => '-1001234567890'],
            'join_url' => 'https://t.me/+testInvite',
        ]);
    }

    /**
     * Kênh Chatwork.
     */
    public function chatwork(): static
    {
        return $this->state([
            'driver' => NotificationDriver::Chatwork,
            'settings' => ['api_token' => 'abcdef0123456789abcdef0123456789', 'room_id' => '123456789'],
            'join_url' => 'https://www.chatwork.com/g/testroom',
        ]);
    }
}
