<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Http\Requests\BatchResultRequest;
use App\Models\ActivityLog;
use App\Models\CookingBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Nhập số suất thực tế (đã chuẩn bị / đã phân phát) của chương trình. */
class BatchResultController extends Controller
{
    /**
     * Form nhập số suất.
     *
     * @param  CookingBatch  $batch  chương trình
     */
    public function edit(CookingBatch $batch): View
    {
        Gate::authorize('recordResults', $batch);

        return view('batches.results', compact('batch'));
    }

    /**
     * Lưu số suất có kiểm tra phiên bản (khóa dòng): nếu người khác vừa lưu thì không ghi đè,
     * trả 409 (AJAX) hoặc quay lại form với giá trị mới nhất.
     *
     * @param  BatchResultRequest  $request  dữ liệu đã kiểm tra + quyền recordResults
     * @param  CookingBatch  $batch  chương trình
     */
    public function update(BatchResultRequest $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $saved = DB::transaction(function () use ($batch, $validated, $request) {
            $fresh = CookingBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();

            // Tránh ghi đè khi hai người cùng sửa số liệu.
            if ((int) $validated['results_version'] !== $fresh->results_version) {
                return false;
            }

            $old = [$fresh->prepared_servings, $fresh->distributed_servings];

            $fresh->prepared_servings = $validated['prepared_servings'] ?? null;
            $fresh->distributed_servings = $validated['distributed_servings'] ?? null;
            $fresh->variance_note = $validated['variance_note'] ?? null;
            $fresh->results_updated_by = $request->user()->id;
            $fresh->results_updated_at = now();
            $fresh->results_version++;
            $fresh->save();

            // Chỉ ghi số liệu; ghi chú chênh lệch là chữ tự do nên không đưa vào log.
            $fmt = fn (?int $v) => $v === null ? 'chưa nhập' : number_format($v, 0, ',', '.');
            ActivityLog::record($fresh, $request->user(), ActivityAction::BatchResultsUpdated, sprintf(
                'Cập nhật số suất: chuẩn bị %s → %s; phân phát %s → %s.',
                $fmt($old[0]), $fmt($fresh->prepared_servings), $fmt($old[1]), $fmt($fresh->distributed_servings),
            ));

            return true;
        });

        if (! $saved) {
            $conflict = 'Số liệu vừa được người khác cập nhật. Đây là giá trị mới nhất — vui lòng kiểm tra rồi nhập lại nếu cần.';
            // 409: giao diện tải lại form trong modal để người dùng thấy giá trị mới nhất.
            if ($request->expectsJson()) {
                return response()->json(['message' => $conflict, 'reload' => true], 409);
            }

            // Không giữ dữ liệu cũ để người dùng thấy giá trị mới nhất trước khi nhập lại.
            return redirect()->route('batches.results.edit', $batch)->with('error', $conflict);
        }

        return $this->done($request, redirect()->route('batches.show', $batch)->withFragment('ket-qua'),
            'Đã lưu số suất. Hãy kiểm tra các cảnh báo chênh lệch (nếu có).');
    }
}
