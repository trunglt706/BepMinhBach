<?php

namespace App\Http\Controllers;

use App\Enums\BatchStatus;
use App\Enums\TaskStatus;
use App\Models\CookingBatch;
use App\Models\ExpenseItem;
use App\Support\BatchFilter;
use App\Support\Csv;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const DISCLAIMER = 'Số liệu do người dùng ghi nhận; cần đối chiếu thực tế. Đây không phải báo cáo kế toán hay kiểm toán độc lập.';

    public function index(Request $request): View
    {
        Gate::authorize('viewReports');

        $filter = BatchFilter::fromRequest($request);

        $batches = $this->reportQuery($filter)
            ->orderByDesc('starts_at')
            ->paginate(20)
            ->withQueryString();

        return view('reports.index', [
            'filter' => $filter,
            'batches' => $batches,
            'summary' => $this->summary($filter),
            'generatedAt' => now(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewReports');

        $filter = BatchFilter::fromRequest($request);
        $generatedAt = now();
        $summary = $this->summary($filter);

        return Csv::download('bao-cao-dot-nau-'.$generatedAt->format('Ymd-His').'.csv', function ($put) use ($filter, $generatedAt, $summary) {
            $put(['Báo cáo đợt nấu ăn — BếpMinhBạch']);
            $put(['Thời điểm tạo', $generatedAt->format('H:i d/m/Y')]);
            $put(['Phạm vi', $filter->describe()]);
            $put(['Lưu ý', self::DISCLAIMER]);
            $put(['Ô trống nghĩa là chưa nhập (khác với 0)']);
            $put(['Tổng số đợt', $summary['count'], 'Suất dự kiến', $summary['expected'],
                'Suất đã chuẩn bị', $summary['prepared'], 'Suất đã phân phát', $summary['distributed'],
                'Chi phí thực tế (VND)', $summary['expenseTotal']]);
            $put([]);
            $put([
                'Mã đợt', 'Tên đợt', 'Ngày giờ bắt đầu', 'Địa điểm', 'Trạng thái', 'Người điều phối',
                'Suất dự kiến', 'Suất đã chuẩn bị', 'Suất đã phân phát', 'Phân phát trừ chuẩn bị',
                'Chi phí dự kiến (VND)', 'Chi phí thực tế (VND)', 'Số khoản chi',
                'Việc đã xong', 'Tổng số việc', 'Ghi chú chênh lệch',
            ]);

            $this->reportQuery($filter)->orderBy('starts_at')->chunk(200, function ($batches) use ($put) {
                foreach ($batches as $batch) {
                    $put([
                        $batch->id,
                        $batch->name,
                        $batch->starts_at->format('Y-m-d H:i'),
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

    public function show(CookingBatch $batch): View
    {
        Gate::authorize('viewReports');

        $this->loadBatchDetails($batch);

        return view('reports.show', ['batch' => $batch, 'generatedAt' => now()]);
    }

    public function exportBatch(CookingBatch $batch): StreamedResponse
    {
        Gate::authorize('viewReports');

        $this->loadBatchDetails($batch);
        $generatedAt = now();

        return Csv::download('bao-cao-dot-'.$batch->id.'-'.$generatedAt->format('Ymd-His').'.csv', function ($put) use ($batch, $generatedAt) {
            $put(['Báo cáo đợt nấu — BếpMinhBạch']);
            $put(['Thời điểm tạo', $generatedAt->format('H:i d/m/Y')]);
            $put(['Lưu ý', self::DISCLAIMER]);
            $put([]);
            $put(['THÔNG TIN ĐỢT']);
            $put(['Tên đợt', $batch->name]);
            $put(['Ngày giờ bắt đầu', $batch->starts_at->format('Y-m-d H:i')]);
            $put(['Địa điểm', $batch->location_text]);
            $put(['Trạng thái', $batch->status->label()]);
            $put(['Người điều phối', $batch->coordinator?->name]);
            $put(['Suất dự kiến', $batch->expected_servings]);
            $put(['Suất đã chuẩn bị', $batch->prepared_servings]);
            $put(['Suất đã phân phát', $batch->distributed_servings]);
            $put(['Ghi chú chênh lệch', $batch->variance_note]);
            $put(['Chi phí dự kiến (VND)', $batch->estimated_cost_vnd]);
            $put(['Chi phí thực tế (VND)', $batch->expenseItems->isEmpty() ? null : $batch->expenseItems->sum('amount_vnd')]);
            $put(['Ghi chú rút kinh nghiệm', $batch->completion_notes]);
            $put([]);
            $put(['NGUYÊN LIỆU']);
            $put(['Tên', 'Đơn vị', 'Lượng dự kiến', 'Lượng thực tế đã dùng', 'Đang báo thiếu', 'Ghi chú']);
            foreach ($batch->ingredients as $ingredient) {
                $put([
                    $ingredient->name, $ingredient->unit,
                    Format::quantityInput($ingredient->expected_quantity),
                    Format::quantityInput($ingredient->actual_quantity),
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

    private function reportQuery(BatchFilter $filter): Builder
    {
        return $filter->apply(CookingBatch::query())
            ->with('coordinator:id,name')
            ->withTaskProgress()
            ->withCount('expenseItems')
            ->withSum('expenseItems', 'amount_vnd');
    }

    /** Tổng hợp trên toàn bộ phạm vi lọc (không chỉ trang hiện tại). */
    private function summary(BatchFilter $filter): array
    {
        $base = fn () => $filter->apply(CookingBatch::query());
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
