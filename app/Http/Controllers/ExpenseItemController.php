<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Http\Requests\ExpenseItemRequest;
use App\Models\ActivityLog;
use App\Models\CookingBatch;
use App\Models\ExpenseItem;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Khoản chi nguyên liệu của chương trình (ghi nhận nội bộ, không phải sổ sách kế toán).
 * Log ghi tên khoản + số tiền để đối chiếu; ghi chú của khoản chi (chữ tự do) không đưa vào log.
 */
class ExpenseItemController extends Controller
{
    /**
     * Form thêm khoản chi.
     *
     * @param  CookingBatch  $batch  chương trình
     */
    public function create(CookingBatch $batch): View
    {
        Gate::authorize('manageExpenses', $batch);

        return view('expenses.create', ['batch' => $batch, 'expense' => new ExpenseItem]);
    }

    /**
     * Thêm khoản chi; "Lưu và thêm tiếp" giữ modal mở.
     *
     * @param  ExpenseItemRequest  $request  dữ liệu đã kiểm tra (số tiền đã bỏ dấu phân cách)
     * @param  CookingBatch  $batch  chương trình
     */
    public function store(ExpenseItemRequest $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        $expense = new ExpenseItem($request->validated());
        $expense->recorded_by = $request->user()->id;

        DB::transaction(function () use ($batch, $expense, $request) {
            $batch->expenseItems()->save($expense);
            ActivityLog::record($batch, $request->user(), ActivityAction::ExpenseCreated,
                'Thêm khoản chi “'.$expense->name.'”: '.Format::vnd($expense->amount_vnd).'.');
        });

        if ($request->boolean('add_another')) {
            return $this->done($request, redirect()->route('batches.expenses.create', $batch),
                'Đã thêm khoản chi “'.$expense->name.'”. Bạn có thể nhập khoản tiếp theo.', ['keepOpen' => true]);
        }

        return $this->done($request, redirect()->route('batches.show', $batch)->withFragment('chi-phi'),
            'Đã thêm khoản chi “'.$expense->name.'”.');
    }

    /**
     * Form sửa khoản chi.
     *
     * @param  ExpenseItem  $expense  khoản chi
     */
    public function edit(ExpenseItem $expense): View
    {
        Gate::authorize('update', $expense);

        return view('expenses.edit', ['batch' => $expense->batch, 'expense' => $expense]);
    }

    /**
     * Lưu thay đổi khoản chi (log ghi số tiền cũ → mới).
     *
     * @param  ExpenseItemRequest  $request  dữ liệu đã kiểm tra
     * @param  ExpenseItem  $expense  khoản chi
     */
    public function update(ExpenseItemRequest $request, ExpenseItem $expense): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($expense, $request) {
            $oldAmount = $expense->amount_vnd;
            $expense->update($request->validated());
            ActivityLog::record($expense->batch, $request->user(), ActivityAction::ExpenseUpdated, sprintf(
                'Sửa khoản chi “%s”: %s → %s.', $expense->name, Format::vnd($oldAmount), Format::vnd($expense->amount_vnd),
            ));
        });

        return $this->done($request, redirect()->route('batches.show', $expense->cooking_batch_id)->withFragment('chi-phi'),
            'Đã lưu khoản chi “'.$expense->name.'”.');
    }

    /**
     * Xóa khoản chi.
     *
     * @param  Request  $request  yêu cầu (không có dữ liệu nhập)
     * @param  ExpenseItem  $expense  khoản chi
     */
    public function destroy(Request $request, ExpenseItem $expense): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $expense);

        DB::transaction(function () use ($expense, $request) {
            $expense->delete();
            ActivityLog::record($expense->batch, $request->user(), ActivityAction::ExpenseDeleted,
                'Xóa khoản chi “'.$expense->name.'”: '.Format::vnd($expense->amount_vnd).'.');
        });

        return $this->done($request, redirect()->route('batches.show', $expense->cooking_batch_id)->withFragment('chi-phi'),
            'Đã xóa khoản chi “'.$expense->name.'”.');
    }
}
