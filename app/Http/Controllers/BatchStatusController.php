<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\BatchStatus;
use App\Enums\TaskStatus;
use App\Http\Requests\BatchCancelRequest;
use App\Http\Requests\BatchCompleteRequest;
use App\Http\Requests\BatchReopenRequest;
use App\Http\Requests\BatchStatusRequest;
use App\Models\ActivityLog;
use App\Models\CookingBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Vòng đời đợt nấu: chuyển trạng thái, hoàn tất (khóa), hủy, mở lại. */
class BatchStatusController extends Controller
{
    /**
     * Chuyển trạng thái đơn giản: Nháp ↔ Đã lên kế hoạch → Đang diễn ra.
     *
     * @param  BatchStatusRequest  $request  trạng thái đích hợp lệ từ trạng thái hiện tại
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function change(BatchStatusRequest $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        $from = $batch->status;
        $batch->status = $request->target();

        DB::transaction(function () use ($batch, $from, $request) {
            $batch->save();
            ActivityLog::record($batch, $request->user(), ActivityAction::BatchStatusChanged,
                'Chuyển trạng thái: '.$from->label().' → '.$batch->status->label().'.');
        });

        return $this->done($request, redirect()->route('batches.show', $batch), 'Đợt nấu đã chuyển sang “'.$batch->status->label().'”.');
    }

    /**
     * Màn hình xác nhận hoàn tất, kèm danh sách mục còn thiếu.
     *
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function confirmComplete(CookingBatch $batch): View
    {
        Gate::authorize('complete', $batch);

        return view('batches.complete', [
            'batch' => $batch,
            'reminders' => $this->completionReminders($batch),
        ]);
    }

    /**
     * Hoàn tất và khóa đợt nấu.
     *
     * @param  BatchCompleteRequest  $request  có tick xác nhận
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function complete(BatchCompleteRequest $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($batch, $request) {
            $batch->status = BatchStatus::Completed;
            $batch->completed_at = now();
            $batch->completion_notes = $request->validated('completion_notes');
            $batch->save();

            ActivityLog::record($batch, $request->user(), ActivityAction::BatchCompleted, 'Hoàn tất đợt nấu.');
        });

        return $this->done($request, redirect()->route('batches.show', $batch), 'Đã hoàn tất đợt nấu.');
    }

    /**
     * Màn hình xác nhận hủy đợt.
     *
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function confirmCancel(CookingBatch $batch): View
    {
        Gate::authorize('cancel', $batch);

        $openTasks = $batch->tasks()->where('status', '!=', TaskStatus::Done->value)->count();

        return view('batches.cancel', compact('batch', 'openTasks'));
    }

    /**
     * Hủy đợt nấu, giữ nguyên dữ liệu. Lý do hủy lưu ở đợt nấu, không đưa vào log.
     *
     * @param  BatchCancelRequest  $request  có tick xác nhận
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function cancel(BatchCancelRequest $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($batch, $request) {
            $openTasks = $batch->tasks()->where('status', '!=', TaskStatus::Done->value)->count();

            $batch->status = BatchStatus::Cancelled;
            $batch->cancelled_at = now();
            $batch->cancellation_reason = $request->validated('cancellation_reason');
            $batch->save();

            ActivityLog::record($batch, $request->user(), ActivityAction::BatchCancelled,
                "Hủy đợt nấu. {$openTasks} công việc chưa xong được giữ nguyên và khóa thao tác.");
        });

        return $this->done($request, redirect()->route('batches.show', $batch), 'Đã hủy đợt nấu. Dữ liệu vẫn được giữ lại.');
    }

    /**
     * Màn hình xác nhận mở lại đợt.
     *
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function confirmReopen(CookingBatch $batch): View
    {
        Gate::authorize('reopen', $batch);

        return view('batches.reopen', compact('batch'));
    }

    /**
     * Mở lại: đã hoàn tất → Đang diễn ra; đã hủy → Nháp.
     * Lý do (chữ tự do) lưu ở đợt nấu (reopen_reason); log chỉ ghi việc mở lại, không chép lý do.
     *
     * @param  BatchReopenRequest  $request  lý do + tick xác nhận
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function reopen(BatchReopenRequest $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($batch, $request) {
            $from = $batch->status;
            $batch->status = $from === BatchStatus::Completed ? BatchStatus::InProgress : BatchStatus::Draft;
            $batch->completed_at = null;
            $batch->cancelled_at = null;
            $batch->reopen_reason = $request->validated('reason');
            $batch->reopened_at = now();
            $batch->save();

            ActivityLog::record($batch, $request->user(), ActivityAction::BatchReopened,
                'Mở lại đợt ('.$from->label().' → '.$batch->status->label().'), có ghi lý do.');
        });

        return $this->done($request, redirect()->route('batches.show', $batch), 'Đã mở lại đợt nấu. Trạng thái hiện tại: “'.$batch->status->label().'”.');
    }

    /**
     * Các mục còn thiếu trước khi hoàn tất (chỉ nhắc, không tự điền số liệu).
     *
     * @param  CookingBatch  $batch  đợt nấu
     * @return list<string> câu nhắc hiển thị
     */
    private function completionReminders(CookingBatch $batch): array
    {
        $reminders = [];

        $openTasks = $batch->tasks()->where('status', '!=', TaskStatus::Done->value)->count();
        if ($openTasks > 0) {
            $reminders[] = "Còn {$openTasks} công việc chưa hoàn thành.";
        }

        $shortages = $batch->ingredients()->where('needs_restock', true)->count();
        if ($shortages > 0) {
            $reminders[] = "Còn {$shortages} nguyên liệu đang được báo thiếu.";
        }

        if ($batch->prepared_servings === null) {
            $reminders[] = 'Chưa nhập số suất đã chuẩn bị.';
        }

        if ($batch->distributed_servings === null) {
            $reminders[] = 'Chưa nhập số suất đã phân phát.';
        }

        if (! $batch->expenseItems()->exists()) {
            $reminders[] = 'Chưa nhập khoản chi phí nào.';
        }

        return $reminders;
    }
}
