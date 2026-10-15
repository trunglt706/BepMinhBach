<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\CookingBatch;
use App\Models\Sponsor;
use App\Support\PublicPostCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Gắn / gỡ đơn vị tài trợ, hỗ trợ cho một chương trình (quyền CookingBatchPolicy::manageSponsors). */
class BatchSponsorController extends Controller
{
    /**
     * Form chọn đơn vị (chỉ đơn vị đang hiển thị và chưa gắn với chương trình).
     *
     * @param  CookingBatch  $batch  chương trình
     */
    public function create(CookingBatch $batch): View
    {
        Gate::authorize('manageSponsors', $batch);

        $options = Sponsor::active()->whereDoesntHave('batches', fn ($q) => $q->whereKey($batch->id))
            ->orderBy('type')->orderBy('name')->get(['id', 'name', 'type']);

        return view('batches.sponsors.create', compact('batch', 'options'));
    }

    /**
     * Gắn đơn vị kèm ghi chú đóng góp.
     *
     * @param  Request  $request  sponsor_id, contribution
     * @param  CookingBatch  $batch  chương trình
     */
    public function store(Request $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        Gate::authorize('manageSponsors', $batch);

        $data = $request->validate([
            'sponsor_id' => ['required', 'integer', Rule::exists('sponsors', 'id')->where('is_active', true),
                Rule::unique('batch_sponsor', 'sponsor_id')->where('cooking_batch_id', $batch->id)],
            'contribution' => ['nullable', 'string', 'max:200'],
        ], ['sponsor_id.unique' => 'Đơn vị này đã có trong chương trình.'], ['sponsor_id' => 'đơn vị', 'contribution' => 'đóng góp']);

        $sponsor = Sponsor::findOrFail($data['sponsor_id']);
        DB::transaction(function () use ($batch, $sponsor, $data, $request) {
            $batch->sponsors()->attach($sponsor->id, ['contribution' => $data['contribution'] ?? null]);
            ActivityLog::record($batch, $request->user(), ActivityAction::BatchUpdated,
                'Thêm '.mb_strtolower($sponsor->type->label()).' “'.$sponsor->name.'”.');
        });
        PublicPostCache::invalidate();

        return $this->done($request, redirect()->route('batches.show', $batch)->withFragment('don-vi'), 'Đã thêm “'.$sponsor->name.'” vào chương trình.');
    }

    /**
     * Gỡ đơn vị khỏi chương trình (không xóa đơn vị).
     *
     * @param  Request  $request  yêu cầu hiện tại
     * @param  CookingBatch  $batch  chương trình
     * @param  Sponsor  $sponsor  đơn vị
     */
    public function destroy(Request $request, CookingBatch $batch, Sponsor $sponsor): JsonResponse|RedirectResponse
    {
        Gate::authorize('manageSponsors', $batch);

        DB::transaction(function () use ($batch, $sponsor, $request) {
            abort_unless($batch->sponsors()->detach($sponsor->id) > 0, 404);
            ActivityLog::record($batch, $request->user(), ActivityAction::BatchUpdated, 'Gỡ đơn vị “'.$sponsor->name.'” khỏi chương trình.');
        });
        PublicPostCache::invalidate();

        return $this->done($request, redirect()->route('batches.show', $batch)->withFragment('don-vi'), 'Đã gỡ “'.$sponsor->name.'” khỏi chương trình.');
    }
}
