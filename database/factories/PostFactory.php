<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'title' => fake()->randomElement(['Cảm ơn các bạn tình nguyện viên', 'Nhìn lại chương trình tuần qua', 'Chuẩn bị cho bữa cơm chủ nhật']).' '.fake()->unique()->numberBetween(1, 99999),
            'excerpt' => null,
            'body' => "Đoạn mở đầu của bài viết.\n\nĐoạn thứ hai kể về công việc trong bếp.",
            'author_id' => User::factory()->coordinator(),
        ];
    }

    public function published(?\DateTimeInterface $at = null): static
    {
        return $this->state(fn () => ['status' => PostStatus::Published, 'published_at' => $at ?? now()->subHour()]);
    }
}
