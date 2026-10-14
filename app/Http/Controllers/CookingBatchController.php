<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\BatchStatus;
use App\Http\Requests\BatchFilterRequest;
use App\Http\Requests\CookingBatchRequest;
use App\Models\ActivityLog;
use App\Models\CookingBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Đợt nấu: danh sách, tạo/sửa/xóa (nháp), trang chi tiết và bản in checklist. */
class CookingBatchController extends Controller
{
    /**
     * Danh sách đợt nấu có lọc và phân trang.
     *
     * @param  BatchFilterRequest  $request  bộ lọc đã chuẩn hóa (giá trị lạ bị bỏ qua)
     */
    public function index(BatchFilterRequest $request): View
    {
        Gate::authorize('viewAny', CookingBatch::class);

        $filter = $request->filter();

        $batches = $filter->apply(CookingBatch::query())
            ->with('coordinator:id,name')
            ->withTaskProgress()
            ->orderByDesc('starts_at')
            ->paginate(12)
            ->withQueryString();

        return view('batches.index', compact('batches', 'filter'));
    }

    /**
     * Form tạo đợt nấu (người tạo là điều phối viên mặc định).
     */
    public function create(): View
    {
        Gate::authorize('create', CookingBatch::class);

        return view('batches.create', [
            'batch' => new CookingBatch(['coordinator_id' => auth()->id()]),
            'coordinators' => $this->coordinatorOptions(),
        ]);
    }

    /**
     * Tạo đợt nấu ở trạng thái nháp hoặc đã lên kế hoạch.
     * Đợt mới có trang riêng nên AJAX cũng chuyển sang trang đó (một lần), thông báo được flash.
     *
     * @param  CookingBatchRequest  $request  dữ liệu đã kiểm tra (save_as ∈ {nháp, kế hoạch})
     */
    public function store(CookingBatchRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();

        $batch = DB::transaction(function () use ($data, $request) {
            $batch = new CookingBatch($data);
            $batch->status = BatchStatus::from($data['save_as']);
            $batch->created_by = $request->user()->id;
            $batch->save();

            ActivityLog::record($batch, $request->user(), ActivityAction::BatchCreated, 'Tạo đợt nấu ('.$batch->status->label().').');

            return $batch;
        });

        $show = route('batches.show', $batch);

        return $this->done($request, redirect($show), 'Đã lưu đợt nấu. Tiếp theo: thêm nguyên liệu và công việc.', ['redirect' => $show]);
    }

    /**
     * Trang chi tiết đợt: công việc, nguyên liệu, số suất; chi phí và lịch sử chỉ cho người xem được tài chính.
     *
     * @param  Request  $request  yêu cầu hiện tại (lấy người dùng)
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function show(Request $request, CookingBatch $batch): View
    {
        Gate::authorize('view', $batch);

        $batch->load([
            'coordinator:id,name',
            'creator:id,name',
            'resultsUpdater:id,name',
            'ingredients' => fn ($q) => $q->orderByDesc('needs_restock')->orderBy('name'),
            'ingredients.restockReporter:id,name',
            'tasks' => fn ($q) => $q->orderByRaw('due_at is null')->orderBy('due_at')->orderBy('id'),
            'tasks.assignee:id,name',
            'tasks.completer:id,name',
        ]);

        // Policy của từng dòng cần $item->batch — gán sẵn để tránh N+1.
        $batch->tasks->each->setRelation('batch', $batch);
        $batch->ingredients->each->setRelation('batch', $batch);

        $canViewFinancials = $request->user()->can('viewFinancials', $batch);
        if ($canViewFinancials) {
            $batch->load(['expenseItems' => fn ($q) => $q->orderBy('created_at'), 'expenseItems.recorder:id,name']);
            $batch->expenseItems->each->setRelation('batch', $batch);
        }

        $activity = $canViewFinancials
            ? $batch->activityLogs()->with('user:id,name')->latest('created_at')->latest('id')->limit(20)->get()
            : collect();

        return view('batches.show', compact('batch', 'canViewFinancials', 'activity'));
    }

    /**
     * Form sửa thông tin đợt.
     *
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function edit(CookingBatch $batch): View
    {
        Gate::authorize('update', $batch);

        return view('batches.edit', [
            'batch' => $batch,
            'coordinators' => $this->coordinatorOptions($batch->coordinator_id),
        ]);
    }

    /**
     * Lưu thông tin đợt; chỉ ghi log khi thực sự có thay đổi (không ghi nội dung ghi chú).
     *
     * @param  CookingBatchRequest  $request  dữ liệu đã kiểm tra + quyền update
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function update(CookingBatchRequest $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($request, $batch) {
            $batch->fill($request->validated());
            $changed = $batch->isDirty();
            $batch->save();

            if ($changed) {
                ActivityLog::record($batch, $request->user(), ActivityAction::BatchUpdated, 'Sửa thông tin đợt nấu.');
            }
        });

        return $this->done($request, redirect()->route('batches.show', $batch), 'Đã lưu thay đổi.');
    }

    /**
     * Xóa đợt nháp chưa có dữ liệu (xem CookingBatchPolicy::delete). Bài viết gắn với đợt chỉ bị bỏ liên kết.
     *
     * @param  Request  $request  yêu cầu (không có dữ liệu nhập)
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function destroy(Request $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $batch);

        $batch->delete();

        $index = route('batches.index');

        return $this->done($request, redirect($index), 'Đã xóa đợt nấu nháp “'.$batch->name.'”.', ['redirect' => $index]);
    }

    /**
     * Bản in checklist A4 (dùng khi mất mạng trong bếp).
     *
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function print(CookingBatch $batch): View
    {
        Gate::authorize('view', $batch);

        $batch->load([
            'coordinator:id,name',
            'ingredients' => fn ($q) => $q->orderBy('name'),
            'tasks' => fn ($q) => $q->orderByRaw('due_at is null')->orderBy('due_at')->orderBy('id'),
            'tasks.assignee:id,name',
        ]);

        return view('batches.print', compact('batch'));
    }

    /**
     * Điều phối viên đang hoạt động; giữ lại người điều phối hiện tại kể cả khi đã đổi vai trò.
     *
     * @param  int|null  $currentId  người điều phối hiện tại
     * @return Collection<int, User>
     */
    private function coordinatorOptions(?int $currentId = null): Collection
    {
        return User::coordinators()
            ->when($currentId, fn ($q) => $q->orWhere('id', $currentId))
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
    }
}
