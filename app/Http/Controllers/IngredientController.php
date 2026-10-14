<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Http\Requests\IngredientRequest;
use App\Http\Requests\ShortageRequest;
use App\Models\ActivityLog;
use App\Models\CookingBatch;
use App\Models\Ingredient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Nguyên liệu của đợt nấu và báo thiếu nguyên liệu. */
class IngredientController extends Controller
{
    /**
     * Form thêm nguyên liệu.
     *
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function create(CookingBatch $batch): View
    {
        Gate::authorize('manageChecklist', $batch);

        return view('ingredients.create', ['batch' => $batch, 'ingredient' => new Ingredient]);
    }

    /**
     * Thêm nguyên liệu; "Lưu và thêm tiếp" giữ modal mở.
     *
     * @param  IngredientRequest  $request  dữ liệu đã kiểm tra + quyền manageChecklist
     * @param  CookingBatch  $batch  đợt nấu
     */
    public function store(IngredientRequest $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        $ingredient = new Ingredient($request->validated());
        $ingredient->created_by = $request->user()->id;
        $batch->ingredients()->save($ingredient);

        if ($request->boolean('add_another')) {
            return $this->done($request, redirect()->route('batches.ingredients.create', $batch),
                'Đã thêm “'.$ingredient->name.'”. Bạn có thể thêm nguyên liệu tiếp theo.', ['keepOpen' => true]);
        }

        return $this->done($request, redirect()->route('batches.show', $batch)->withFragment('nguyen-lieu'),
            'Đã thêm nguyên liệu “'.$ingredient->name.'”.');
    }

    /**
     * Form sửa nguyên liệu.
     *
     * @param  Ingredient  $ingredient  nguyên liệu
     */
    public function edit(Ingredient $ingredient): View
    {
        Gate::authorize('update', $ingredient);

        return view('ingredients.edit', ['batch' => $ingredient->batch, 'ingredient' => $ingredient]);
    }

    /**
     * Lưu thay đổi nguyên liệu.
     *
     * @param  IngredientRequest  $request  dữ liệu đã kiểm tra + quyền update
     * @param  Ingredient  $ingredient  nguyên liệu
     */
    public function update(IngredientRequest $request, Ingredient $ingredient): JsonResponse|RedirectResponse
    {
        $ingredient->fill($request->validated());
        $ingredient->updated_by = $request->user()->id;
        $ingredient->save();

        return $this->done($request, redirect()->route('batches.show', $ingredient->cooking_batch_id)->withFragment('nguyen-lieu'),
            'Đã lưu nguyên liệu “'.$ingredient->name.'”.');
    }

    /**
     * Xóa nguyên liệu.
     *
     * @param  Request  $request  yêu cầu (không có dữ liệu nhập)
     * @param  Ingredient  $ingredient  nguyên liệu
     */
    public function destroy(Request $request, Ingredient $ingredient): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $ingredient);

        $ingredient->delete();

        return $this->done($request, redirect()->route('batches.show', $ingredient->cooking_batch_id)->withFragment('nguyen-lieu'),
            'Đã xóa nguyên liệu “'.$ingredient->name.'”.');
    }

    /**
     * Báo thiếu nguyên liệu. Ghi chú thiếu lưu ở nguyên liệu, không đưa vào log.
     *
     * @param  ShortageRequest  $request  ghi chú (tùy chọn) + quyền reportShortage
     * @param  Ingredient  $ingredient  nguyên liệu
     */
    public function reportShortage(ShortageRequest $request, Ingredient $ingredient): JsonResponse|RedirectResponse
    {
        $ingredient->needs_restock = true;
        $ingredient->restock_note = $request->validated('restock_note');
        $ingredient->restock_reported_by = $request->user()->id;
        $ingredient->restock_reported_at = now();
        $ingredient->save();

        ActivityLog::record($ingredient->batch, $request->user(), ActivityAction::IngredientShortage,
            'Báo thiếu nguyên liệu “'.$ingredient->name.'”.');

        return $this->done($request, back(), 'Đã báo thiếu “'.$ingredient->name.'”. Điều phối viên sẽ thấy cảnh báo.');
    }

    /**
     * Gỡ cảnh báo thiếu nguyên liệu.
     *
     * @param  Request  $request  yêu cầu (không có dữ liệu nhập)
     * @param  Ingredient  $ingredient  nguyên liệu
     */
    public function resolveShortage(Request $request, Ingredient $ingredient): JsonResponse|RedirectResponse
    {
        Gate::authorize('resolveShortage', $ingredient);

        $ingredient->needs_restock = false;
        $ingredient->restock_note = null;
        $ingredient->restock_reported_by = null;
        $ingredient->restock_reported_at = null;
        $ingredient->save();

        ActivityLog::record($ingredient->batch, $request->user(), ActivityAction::IngredientResolved,
            'Gỡ cảnh báo thiếu nguyên liệu “'.$ingredient->name.'”.');

        return $this->done($request, back(), 'Đã gỡ cảnh báo thiếu “'.$ingredient->name.'”.');
    }
}
