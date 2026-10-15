<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Models\CookingBatch;
use App\Models\ExpenseItem;
use App\Models\Ingredient;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Giới hạn số truy vấn SQL của các trang chính với dữ liệu nhiều (12 chương trình × 6 việc × 8 nguyên liệu):
 * phát hiện sớm lỗi N+1 (ví dụ kiểm tra phạm vi xem cho từng việc). Số liệu đo ngày 15/10/2026 ghi trong chú thích.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_pages_stay_within_query_budget(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $volunteer = User::factory()->volunteer()->create();
        $recorder = User::factory()->recorder()->create();
        foreach (range(1, 12) as $i) {
            $b = CookingBatch::factory()->status(BatchStatus::InProgress)->create(['coordinator_id' => $coordinator->id, 'created_by' => $coordinator->id]);
            Task::factory()->count(5)->for($b, 'batch')->create(['assignee_id' => $volunteer->id]);
            Task::factory()->for($b, 'batch')->create(['assignee_id' => $recorder->id]);
            Ingredient::factory()->count(8)->for($b, 'batch')->create(['needs_restock' => true]);
            ExpenseItem::factory()->count(3)->for($b, 'batch')->create();
        }
        $one = CookingBatch::first();

        $budgets = [
            [$coordinator, '/admin', 25],                          // đo: 19
            [$volunteer, '/admin', 25],                            // 18
            [$volunteer, '/admin/batches', 10],                    // 5
            [$volunteer, "/admin/batches/{$one->id}", 15],         // 10
            [$coordinator, "/admin/batches/{$one->id}", 18],       // 13
            [$recorder, '/admin/reports', 18],                     // 14
            [$recorder, "/admin/reports/batches/{$one->id}", 15],  // 11
            [$volunteer, '/admin/my-tasks', 12],                   // 7 (trước khi sửa N+1: 185)
            [null, '/', 6],
            [null, '/chuong-trinh/'.$one->public_id, 6],
        ];
        foreach ($budgets as [$user, $url, $max]) {
            $this->flushSession();
            $this->app['auth']->forgetGuards();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $user ? $this->actingAs($user)->get($url)->assertOk() : $this->get($url)->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();
            $this->assertLessThanOrEqual($max, $count, "{$url} ({$user?->role->value}) chạy {$count} truy vấn, giới hạn {$max}");
        }
    }
}
