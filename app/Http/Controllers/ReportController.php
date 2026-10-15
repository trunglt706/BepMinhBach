<?php

namespace App\Http\Controllers;

use App\Enums\BatchStatus;
use App\Enums\TaskStatus;
use App\Http\Requests\BatchFilterRequest;
use App\Models\CookingBatch;
use App\Models\ExpenseItem;
use App\Support\BatchFilter;
use App\Support\Csv;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Báo cáo nội bộ (người ghi nhận trở lên): tổng hợp theo bộ lọc, chi tiết từng chương trình, xuất CSV. */
class ReportController extends Controller
{
    /** Lưu ý hiển thị trên mọi báo cáo và file CSV. */
    public const DISCLAIMER = 'Số liệu do người dùng ghi nhận; cần đối chiếu thực tế. Đây không phải báo cáo kế toán hay kiểm toán độc lập.';

    /**
     * Báo cáo theo bộ lọc: số liệu tổng hợp + bảng từng chương trình (phân trang).
     *
     * @param  BatchFilterRequest  $request  bộ lọc đã chuẩn hóa
     */
    public function index(BatchFilterRequest $request): View
    {
        Gate::authorize('viewReports');

        $filter = $request->filter();

        $batches = $this->reportQuery($filter)
            ->orderByDesc('starts_on')
            ->paginate(20)
            ->withQueryString();

        return view('reports.index', [
            'filter' => $filter,
            'batches' => $batches,
            'summary' => $this->summary($filter),
            'generatedAt' => now(),
        ]);
    }

    /**
     * Xuất CSV báo cáo theo bộ lọc (UTF-8 BOM, chống chèn công thức, xem AppSupportCsv).
     *
     * @param  BatchFilterRequest  $request  bộ lọc đã chuẩn hóa
     */
    public function export(BatchFilterRequest $request): StreamedResponse
    {
        Gate::authorize('viewReports');

        $filter = $request->filter();
        $generatedAt = now();
        $summary = $this->summary($filter);

        return Csv::download('bao-cao-chuong-trinh-'.$generatedAt->format('Ymd-His').'.csv', function ($put) use ($filter, $generatedAt, $summary) {
            $put(['Báo cáo chương trình — Góp Gió']);
            $put(['Thời điểm tạo', $generatedAt->format('H:i d/m/Y')]);
            $put(['Phạm vi', $filter->describe()]);
            $put(['Lưu ý', self::DISCLAIMER]);
            $put(['Ô trống nghĩa là chưa nhập (khác với 0)']);
            $put(['Tổng số chương trình', $summary['count'], 'Suất dự kiến', $summary['expected'],
                'Suất đã chuẩn bị', $summary['prepared'], 'Suất đã phân phát', $summary['distributed'],
                'Chi phí thực tế (VND)', $summary['expenseTotal']]);
            $put([]);
            $put([
                'Mã chương trình', 'Tên chương trình', 'Ngày bắt đầu', 'Địa điểm', 'Trạng thái', 'Người điều phối',
                'Suất dự kiến', 'Suất đã chuẩn bị', 'Suất đã phân phát', 'Phân phát trừ chuẩn bị',
                'Chi phí dự kiến (VND)', 'Chi phí thực tế (VND)', 'Số khoản chi',
                'Việc đã xong', 'Tổng số việc', 'Ghi chú chênh lệch',
            ]);

            $this->reportQuery($filter)->orderBy('starts_on')->chunk(200, function ($batches) use ($put) {
                foreach ($batches as $batch) {
                    $put([
                        $batch->id,
                        $batch->name,
                        $batch->starts_on->format('Y-m-d'),
                        $batch->location_text,
                        $batch->status->label(),
                        $batch->coordinator?->name,
                        $batch->expected_servings,
                        $batch->prepared_servings,
                        $batch->distributed_servings,
                        $batch->prepared_servings !== null && $batch->distributed_servings !== null
                            ? $batch->distributed_servings - $batch->prepared_servings : null,
                        $batch->estimated_cost_vnd,
                        $batch->expense_items_count > 0 ? (int) $batch->expense_items_sum_amount_vnd : null,
                        $batch->expense_items_count,
                        $batch->tasks_done_count,
                        $batch->tasks_count,
                        $batch->variance_note,
                    ]);
                }
            });
        });
    }

    /**
     * Báo cáo chi tiết một chương trình (bản xem/in).
     *
     * @param  CookingBatch  $batch  chương trình
     */
    public function show(CookingBatch $batch): View
    {
        Gate::authorize('viewReports');
        Gate::authorize('view', $batch); // chỉ chương trình trong phạm vi được xem

        $this->loadBatchDetails($batch);

        return view('reports.show', ['batch' => $batch, 'generatedAt' => now()]);
    }

    /**
     * Xuất CSV chi tiết một chương trình.
     *
     * @param  CookingBatch  $batch  chương trình
     */
    public function exportBatch(CookingBatch $batch): StreamedResponse
    {
        Gate::authorize('viewReports');
        Gate::authorize('view', $batch);

        $this->loadBatchDetails($batch);
        $generatedAt = now();

        return Csv::download('bao-cao-chuong-trinh-'.$batch->id.'-'.$generatedAt->format('Ymd-His').'.csv', function ($put) use ($batch, $generatedAt) {
            $put(['Báo cáo chương trình — Góp Gió']);
            $put(['Thời điểm tạo', $generatedAt->format('H:i d/m/Y')]);
            $put(['Lưu ý', self::DISCLAIMER]);
            $put([]);
            $put(['THÔNG TIN CHƯƠNG TRÌNH']);
            $put(['Tên chương trình', $batch->name]);
            $put(['Ngày bắt đầu', $batch->starts_on->format('Y-m-d')]);
            $put(['Địa điểm', $batch->location_text]);
            $put(['Trạng thái', $batch->status->label()]);
            $put(['Người điều phối', $batch->coordinator?->name]);
            $put(['Suất dự kiến', $batch->expected_servings]);
            $put(['Suất đã chuẩn bị', $batch->prepared_servings]);
            $put(['Suất đã phân phát', $batch->distributed_servings]);
            $put(['Ghi chú chênh lệch', $batch->variance_note]);
            $put(['Chi phí dự kiến (VND)', $batch->estimated_cost_vnd]);
            $put(['Chi phí thực tế (VND)', $batch->expenseItems->isEmpty() ? null : $batch->expenseItems->sum('amount_vnd')]);
            $put(['Tiền nguyên liệu theo đơn giá (VND)', $batch->ingredientCostVnd()]);
            $put(['Ghi chú rút kinh nghiệm', $batch->completion_notes]);
            $put([]);
            $put(['NGUYÊN LIỆU']);
            $put(['Tên', 'Đơn vị', 'Lượng dự kiến', 'Lượng thực tế đã dùng', 'Đơn giá (VND)', 'Thành tiền (VND)', 'Đang báo thiếu', 'Ghi chú']);
            foreach ($batch->ingredients as $ingredient) {
                $put([
                    $ingredient->name, $ingredient->unit,
                    Format::quantityInput($ingredient->expected_quantity),
                    Format::quantityInput($ingredient->actual_quantity),
                    $ingredient->unit_price_vnd, $ingredient->costVnd(),
                    $ingredient->needs_restock, $ingredient->notes,
                ]);
            }
            $put([]);
            $put(['CÔNG VIỆC']);
            $put(['Tên việc', 'Người phụ trách', 'Hạn', 'Trạng thái', 'Hoàn thành lúc', 'Lý do bị vướng']);
            foreach ($batch->tasks as $task) {
                $put([
                    $task->title, $task->assignee?->name, $task->due_at?->format('Y-m-d H:i'),
                    $task->status->label(), $task->completed_at?->format('Y-m-d H:i'), $task->blocked_note,
                ]);
            }
            $put([]);
            $put(['CHI PHÍ']);
            $put(['Khoản chi', 'Số tiền (VND)', 'Ghi chú', 'Người ghi', 'Ngày ghi']);
            foreach ($batch->expenseItems as $expense) {
                $put([
                    $expense->name, $expense->amount_vnd, $expense->note,
                    $expense->recorder?->name, $expense->created_at->format('Y-m-d H:i'),
                ]);
            }
        });
    }

    /**
     * Truy vấn chương trình của báo cáo kèm tiến độ công việc và tổng chi phí.
     *
     * @param  BatchFilter  $filter  bộ lọc
     * @return Builder<CookingBatch>
     */
    private function reportQuery(BatchFilter $filter): Builder
    {
        return $filter->apply(CookingBatch::visibleTo(Auth::user()))
            ->with('coordinator:id,name')
            ->withTaskProgress()
            ->withCount('expenseItems')
            ->withSum('expenseItems', 'amount_vnd');
    }

    /**
     * Tổng hợp trên toàn bộ phạm vi lọc (không chỉ trang hiện tại). Ô "chưa nhập" không tính là 0.
     *
     * @param  BatchFilter  $filter  bộ lọc
     * @return array<string, int> số chương trình, số suất, chi phí và số chương trình đã nhập từng loại
     */
    private function summary(BatchFilter $filter): array
    {
        $base = fn () => $filter->apply(CookingBatch::visibleTo(Auth::user()));
        $expenses = ExpenseItem::whereIn('cooking_batch_id', $base()->select('id'));

        return [
            'count' => $base()->count(),
            'cancelled' => $base()->where('status', BatchStatus::Cancelled->value)->count(),
            'expected' => (int) $base()->sum('expected_servings'),
            'prepared' => (int) $base()->sum('prepared_servings'),
            'preparedCount' => $base()->whereNotNull('prepared_servings')->count(),
            'distributed' => (int) $base()->sum('distributed_servings'),
            'distributedCount' => $base()->whereNotNull('distributed_servings')->count(),
            'expenseTotal' => (int) (clone $expenses)->sum('amount_vnd'),
            'expenseBatchCount' => (clone $expenses)->distinct()->count('cooking_batch_id'),
        ];
    }

    /**
     * Nạp dữ liệu cần cho báo cáo chi tiết một chương trình.
     *
     * @param  CookingBatch  $batch  chương trình (được nạp quan hệ tại chỗ)
     */
    private function loadBatchDetails(CookingBatch $batch): void
    {
        $batch->load([
            'coordinator:id,name',
            'ingredients' => fn ($q) => $q->orderBy('name'),
            'tasks' => fn ($q) => $q->orderByRaw('due_at is null')->orderBy('due_at')->orderBy('id'),
            'tasks.assignee:id,name',
            'expenseItems' => fn ($q) => $q->orderBy('created_at'),
            'expenseItems.recorder:id,name',
        ]);
        $batch->loadCount([
            'tasks as tasks_done_count' => fn ($q) => $q->where('status', TaskStatus::Done->value),
        ]);
    }
}
