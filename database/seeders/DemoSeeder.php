<?php

namespace Database\Seeders;

use App\Enums\BatchStatus;
use App\Enums\PostStatus;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\CookingBatch;
use App\Models\ExpenseItem;
use App\Models\Ingredient;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dữ liệu demo hoàn toàn giả: không có tên/số điện thoại/địa chỉ người thật,
 * không có bất kỳ thông tin nào về người nhận suất ăn.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('DEMO_PASSWORD') ?: 'demo12345';

        $accounts = [
            'admin' => ['Quản trị Demo', 'admin@bepminhbach.test', Role::Admin],
            'coordinator' => ['Điều phối Demo', 'dieuphoi@bepminhbach.test', Role::Coordinator],
            'recorder' => ['Ghi nhận Demo', 'ghinhan@bepminhbach.test', Role::Recorder],
            'volunteer1' => ['Tình nguyện viên Demo 1', 'tnv1@bepminhbach.test', Role::Volunteer],
            'volunteer2' => ['Tình nguyện viên Demo 2', 'tnv2@bepminhbach.test', Role::Volunteer],
        ];

        $users = [];
        foreach ($accounts as $key => [$name, $email, $role]) {
            $users[$key] = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $password, 'role' => $role, 'is_active' => true],
            );
        }

        if (CookingBatch::exists()) {
            $this->command?->info('Đã có đợt nấu — chỉ cập nhật tài khoản demo.');
            $this->seedPosts($users['coordinator']);

            return;
        }

        $coordinator = $users['coordinator'];
        $makeBatch = function (array $attrs, BatchStatus $status) use ($coordinator) {
            $batch = new CookingBatch($attrs + ['coordinator_id' => $coordinator->id, 'location_text' => 'Bếp demo']);
            $batch->status = $status;
            $batch->created_by = $coordinator->id;
            $batch->save();

            return $batch;
        };
        $addIngredients = function (CookingBatch $batch, array $rows) use ($coordinator) {
            foreach ($rows as [$name, $qty, $unit, $actual]) {
                $ingredient = new Ingredient(['name' => $name, 'expected_quantity' => $qty, 'unit' => $unit, 'actual_quantity' => $actual]);
                $ingredient->created_by = $coordinator->id;
                $batch->ingredients()->save($ingredient);
            }
        };
        $addTask = function (CookingBatch $batch, string $title, ?User $assignee, $dueAt, TaskStatus $status, ?string $blocked = null) use ($coordinator) {
            $task = new Task(['title' => $title, 'assignee_id' => $assignee?->id, 'due_at' => $dueAt]);
            $task->created_by = $coordinator->id;
            $task->cooking_batch_id = $batch->id;
            if ($status !== TaskStatus::Todo) {
                $task->applyStatus($status, $assignee ?? $coordinator, $blocked);
            }
            $task->save();
        };
        $addExpense = function (CookingBatch $batch, string $name, int $amount) use ($users) {
            $expense = new ExpenseItem(['name' => $name, 'amount_vnd' => $amount]);
            $expense->recorded_by = $users['recorder']->id;
            $batch->expenseItems()->save($expense);
        };

        // 1. Đợt đã hoàn tất tuần trước
        $done = $makeBatch([
            'name' => 'Cơm chay chủ nhật (demo)',
            'starts_at' => now()->subWeek()->setTime(6, 30),
            'expected_servings' => 150,
            'estimated_cost_vnd' => 2_000_000,
        ], BatchStatus::Completed);
        $addIngredients($done, [['Gạo', 15, 'kg', 14.5], ['Đậu hũ', 60, 'cái', 60], ['Rau cải', 8, 'kg', 9]]);
        $addTask($done, 'Đi chợ sáng', $users['volunteer1'], now()->subWeek()->setTime(5, 30), TaskStatus::Done);
        $addTask($done, 'Nấu cơm', $users['volunteer2'], now()->subWeek()->setTime(8, 0), TaskStatus::Done);
        $addExpense($done, 'Rau củ chợ sáng', 650_000);
        $addExpense($done, 'Gạo', 300_000);
        $addExpense($done, 'Gas', 450_000);
        $done->forceFill([
            'prepared_servings' => 148,
            'distributed_servings' => 140,
            'variance_note' => 'Còn 8 suất gửi lại bếp dùng bữa trưa cho tình nguyện viên.',
            'results_updated_by' => $users['recorder']->id,
            'results_updated_at' => now()->subWeek()->setTime(12, 0),
            'results_version' => 1,
            'completed_at' => now()->subWeek()->setTime(13, 0),
            'completion_notes' => 'Lần sau cần thêm một người chia suất.',
        ])->save();

        // 2. Đợt đang diễn ra hôm nay
        $today = $makeBatch([
            'name' => 'Cháo dinh dưỡng thứ năm (demo)',
            'starts_at' => now()->setTime(6, 0),
            'expected_servings' => 200,
        ], BatchStatus::InProgress);
        $addIngredients($today, [['Gạo nếp', 10, 'kg', null], ['Nấm rơm', 5, 'kg', null], ['Cà rốt', 6, 'kg', null]]);
        $today->ingredients()->where('name', 'Nấm rơm')->update([
            'needs_restock' => true,
            'restock_note' => 'Chợ chỉ còn khoảng 3 kg',
            'restock_reported_by' => $users['volunteer1']->id,
            'restock_reported_at' => now(),
        ]);
        $addTask($today, 'Sơ chế rau củ', $users['volunteer1'], now()->setTime(7, 0), TaskStatus::Done);
        $addTask($today, 'Nấu cháo', $users['volunteer2'], now()->setTime(9, 0), TaskStatus::InProgress);
        $addTask($today, 'Chuẩn bị hộp đựng', $users['volunteer1'], now()->setTime(10, 0), TaskStatus::Blocked, 'Chưa nhận được hộp từ nhà cung cấp');
        $addTask($today, 'Dọn bếp', null, now()->setTime(13, 0), TaskStatus::Todo);
        $addExpense($today, 'Gạo nếp và nấm', 820_000);

        // 3. Đợt đã lên kế hoạch tuần sau
        $planned = $makeBatch([
            'name' => 'Bánh mì sáng thứ bảy (demo)',
            'starts_at' => now()->addDays(5)->setTime(5, 30),
            'expected_servings' => 120,
            'estimated_cost_vnd' => 1_500_000,
        ], BatchStatus::Planned);
        $addIngredients($planned, [['Bánh mì', 120, 'cái', null], ['Chả chay', 4, 'kg', null]]);
        $addTask($planned, 'Đặt bánh mì', $users['volunteer2'], now()->addDays(3)->setTime(18, 0), TaskStatus::Todo);

        // 4. Đợt nháp
        $makeBatch([
            'name' => 'Bữa trưa cuối tháng (nháp demo)',
            'starts_at' => now()->addDays(14)->setTime(10, 0),
            'expected_servings' => 100,
        ], BatchStatus::Draft);

        $this->seedPosts($coordinator);

        $this->command?->info('Đã tạo dữ liệu demo. Mật khẩu các tài khoản demo lấy từ DEMO_PASSWORD (mặc định chỉ dùng ở local).');
    }

    /** Bài viết demo (nội dung hư cấu, không có thông tin người nhận). Chỉ tạo khi chưa có bài nào. */
    private function seedPosts(User $author): void
    {
        if (Post::exists()) {
            return;
        }

        $completed = CookingBatch::where('status', BatchStatus::Completed->value)->orderByDesc('starts_at')->first();
        $planned = CookingBatch::where('status', BatchStatus::Planned->value)->orderBy('starts_at')->first();

        $rows = [
            ['Nhìn lại đợt nấu tuần trước (demo)', $completed, ['nhìn lại', 'cơm chay'], now()->subDays(5),
                "Cảm ơn các bạn tình nguyện viên đã có mặt từ sáng sớm để sơ chế và nấu.\n\nSố suất đã chuẩn bị và phân phát được ghi trong thông tin đợt nấu bên cạnh."],
            ['Cảm ơn các nhà hảo tâm (demo)', $completed, ['cảm ơn'], now()->subDays(3),
                "Bếp đã nhận thêm gạo và rau củ cho các đợt tiếp theo.\n\nMọi khoản đóng góp được đội bếp ghi nhận nội bộ."],
            ['Thông báo đợt bánh mì sáng thứ bảy (demo)', $planned, ['thông báo', 'bánh mì'], now()->subDay(),
                "Đội bếp cần thêm 4 bạn hỗ trợ chia bánh từ 5 giờ 30 sáng.\n\nVui lòng liên hệ điều phối viên nếu bạn có thể tham gia."],
            ['Mẹo sơ chế rau củ nhanh cho bếp đông người (demo)', null, ['kinh nghiệm', 'sơ chế'], now()->subHours(6),
                "Chia việc theo từng loại rau, rửa trước rồi mới cắt.\n\nGhi chú này chỉ là kinh nghiệm của đội bếp, không thay thế hướng dẫn an toàn thực phẩm chính thức."],
        ];

        foreach ($rows as [$title, $batch, $tags, $publishedAt, $body]) {
            $post = new Post(['title' => $title, 'body' => $body, 'cooking_batch_id' => $batch?->id]);
            $post->author_id = $author->id;
            $post->status = PostStatus::Published;
            $post->published_at = $publishedAt;
            $post->save();
            $post->tags()->sync(Tag::findOrCreateMany($tags)->pluck('id'));
        }

        $draft = new Post(['title' => 'Bản nháp chưa đăng (demo)', 'body' => 'Khách không thấy bài này.']);
        $draft->author_id = $author->id;
        $draft->save();
    }
}
