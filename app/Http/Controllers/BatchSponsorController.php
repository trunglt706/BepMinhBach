<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\SponsorType;
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
     * Gắn đơn vị kèm ghi chú đóng góp: chọn đơn vị có sẵn (sponsor_id) hoặc tạo nhanh đơn vị mới
     * (new_name + new_type; cần quyền tạo đơn vị).
     *
     * @param  Request  $request  sponsor_id | new_name + new_type, contribution
     * @param  CookingBatch  $batch  chương trình
     */
    public function store(Request $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        Gate::authorize('manageSponsors', $batch);
        $request->merge(['new_name' => is_string($request->input('new_name')) ? trim($request->input('new_name')) : null]);

        $data = $request->validate([
            'sponsor_id' => ['nullable', 'required_without:new_name', 'prohibits:new_name', 'integer',
                Rule::exists('sponsors', 'id')->where('is_active', true),
                Rule::unique('batch_sponsor', 'sponsor_id')->where('cooking_batch_id', $batch->id)],
            'new_name' => ['nullable', 'string', 'max:150',
                // Trùng tên với đơn vị đã có → chọn ở danh sách thay vì tạo bản trùng
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value && Sponsor::whereRaw('lower(name) = ?', [mb_strtolower($value)])->exists()) {
                        $fail('Đã có đơn vị tên này trong danh sách — hãy chọn ở ô “Chọn đơn vị có sẵn”.');
                    }
                }],
            'new_type' => ['nullable', 'required_with:new_name', Rule::enum(SponsorType::class)],
            'contribution' => ['nullable', 'string', 'max:200'],
        ], [
            'sponsor_id.unique' => 'Đơn vị này đã có trong chương trình.',
            'sponsor_id.required_without' => 'Hãy chọn một đơn vị có sẵn hoặc nhập tên đơn vị mới.',
            'sponsor_id.prohibits' => 'Chỉ chọn đơn vị có sẵn HOẶC nhập đơn vị mới, không làm cả hai.',
        ], ['sponsor_id' => 'đơn vị', 'new_name' => 'tên đơn vị', 'new_type' => 'phân loại', 'contribution' => 'đóng góp']);

        if (! empty($data['new_name'])) {
            Gate::authorize('create', Sponsor::class);
        }

        $sponsor = DB::transaction(function () use ($batch, $data, $request) {
            $sponsor = empty($data['new_name']) ? Sponsor::findOrFail($data['sponsor_id']) : $this->quickCreate($data, $request);
            $batch->sponsors()->attach($sponsor->id, ['contribution' => $data['contribution'] ?? null]);
            ActivityLog::record($batch, $request->user(), ActivityAction::BatchUpdated,
                'Thêm '.mb_strtolower($sponsor->type->label()).' “'.$sponsor->name.'”.');

            return $sponsor;
        });
        PublicPostCache::invalidate();

        return $this->done($request, redirect()->route('batches.show', $batch)->withFragment('don-vi'), 'Đã thêm “'.$sponsor->name.'” vào chương trình.'
            .(empty($data['new_name']) ? '' : ' Bổ sung logo, giới thiệu ở mục “Đơn vị đồng hành”.'));
    }

    /**
     * Tạo nhanh đơn vị mới (chỉ tên + phân loại, hiển thị công khai).
     *
     * @param  array<string, mixed>  $data  dữ liệu đã kiểm tra
     * @param  Request  $request  yêu cầu hiện tại
     */
    private function quickCreate(array $data, Request $request): Sponsor
    {
        $sponsor = new Sponsor(['name' => $data['new_name'], 'type' => $data['new_type'], 'is_active' => true]);
        $sponsor->created_by = $request->user()->id;
        $sponsor->save();

        return $sponsor;
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
