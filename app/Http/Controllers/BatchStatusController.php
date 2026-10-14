<?php

namespace App\Http\Controllers;

use App\Enums\BatchStatus;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\CookingBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BatchStatusController extends Controller
{
    /** Nháp ↔ Đã lên kế hoạch → Đang diễn ra. */
    public function change(Request $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        Gate::authorize('changeStatus', $batch);

        $allowed = array_map(fn (BatchStatus $s) => $s->value, $batch->status->simpleTransitions());
        $validated = $request->validate(
            ['to' => ['required', Rule::in($allowed)]],
            ['to.in' => 'Không thể chuyển đợt nấu sang trạng thái này từ trạng thái hiện tại.'],
        );

        $from = $batch->status;
        $batch->status = BatchStatus::from($validated['to']);

        DB::transaction(function () use ($batch, $from, $request) {
            $batch->save();
            ActivityLog::record($batch, $request->user(), 'batch.status',
                'Chuyển trạng thái: '.$from->label().' → '.$batch->status->label().'.');
        });

        return $this->done($request, redirect()->route('batches.show', $batch), 'Đợt nấu đã chuyển sang “'.$batch->status->label().'”.');
    }

    public function confirmComplete(CookingBatch $batch): View
    {
        Gate::authorize('complete', $batch);

        return view('batches.complete', [
            'batch' => $batch,
            'reminders' => $this->completionReminders($batch),
        ]);
    }

    public function complete(Request $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        Gate::authorize('complete', $batch);

        $validated = $request->validate([
            'completion_notes' => ['nullable', 'string', 'max:2000'],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Vui lòng đánh dấu ô xác nhận trước khi hoàn tất đợt.',
        ]);

        DB::transaction(function () use ($batch, $validated, $request) {
            $batch->status = BatchStatus::Completed;
            $batch->completed_at = now();
            $batch->completion_notes = $validated['completion_notes'] ?? null;
            $batch->save();

            ActivityLog::record($batch, $request->user(), 'batch.completed', 'Hoàn tất đợt nấu.');
        });

        return $this->done($request, redirect()->route('batches.show', $batch), 'Đã hoàn tất đợt nấu.');
    }

    public function confirmCancel(CookingBatch $batch): View
    {
        Gate::authorize('cancel', $batch);

        $openTasks = $batch->tasks()->where('status', '!=', TaskStatus::Done->value)->count();

        return view('batches.cancel', compact('batch', 'openTasks'));
    }

    public function cancel(Request $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        Gate::authorize('cancel', $batch);

        $validated = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Vui lòng đánh dấu ô xác nhận trước khi hủy đợt.',
        ]);

        DB::transaction(function () use ($batch, $validated, $request) {
            $openTasks = $batch->tasks()->where('status', '!=', TaskStatus::Done->value)->count();

            $batch->status = BatchStatus::Cancelled;
            $batch->cancelled_at = now();
            $batch->cancellation_reason = $validated['cancellation_reason'] ?? null;
            $batch->save();

            ActivityLog::record($batch, $request->user(), 'batch.cancelled',
                "Hủy đợt nấu. {$openTasks} công việc chưa xong được giữ nguyên và khóa thao tác.");
        });

        return $this->done($request, redirect()->route('batches.show', $batch), 'Đã hủy đợt nấu. Dữ liệu vẫn được giữ lại.');
    }

    public function confirmReopen(CookingBatch $batch): View
    {
        Gate::authorize('reopen', $batch);

        return view('batches.reopen', compact('batch'));
    }

    /** Đợt đã hoàn tất → Đang diễn ra; đợt đã hủy → Nháp. Bắt buộc ghi lý do để giữ lịch sử. */
    public function reopen(Request $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        Gate::authorize('reopen', $batch);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Vui lòng đánh dấu ô xác nhận trước khi mở lại đợt.',
        ], ['reason' => 'lý do mở lại']);

        DB::transaction(function () use ($batch, $validated, $request) {
            $from = $batch->status;
            $batch->status = $from === BatchStatus::Completed ? BatchStatus::InProgress : BatchStatus::Draft;
            $batch->completed_at = null;
            $batch->cancelled_at = null;
            $batch->save();

            ActivityLog::record($batch, $request->user(), 'batch.reopened',
                'Mở lại đợt ('.$from->label().' → '.$batch->status->label().'). Lý do: '.$validated['reason']);
        });

        return $this->done($request, redirect()->route('batches.show', $batch), 'Đã mở lại đợt nấu. Trạng thái hiện tại: “'.$batch->status->label().'”.');
    }

    /** Nhắc các mục còn thiếu; không tự điền số liệu. */
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
