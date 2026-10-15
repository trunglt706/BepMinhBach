<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Http\Requests\IngredientRequest;
use App\Http\Requests\ShortageRequest;
use App\Models\ActivityLog;
use App\Models\CookingBatch;
use App\Models\Ingredient;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Nguyên liệu của chương trình và báo thiếu nguyên liệu. */
class IngredientController extends Controller
{
    /**
     * Form thêm nguyên liệu.
     *
     * @param  CookingBatch  $batch  chương trình
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
     * @param  CookingBatch  $batch  chương trình
     */
    public function store(IngredientRequest $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        $ingredient = new Ingredient($request->validated());
        $ingredient->created_by = $request->user()->id;
        DB::transaction(function () use ($batch, $ingredient, $request) {
            $batch->ingredients()->save($ingredient);
            ActivityLog::record($batch, $request->user(), ActivityAction::IngredientCreated,
                'Thêm nguyên liệu “'.$ingredient->name.'”: dự kiến '.$this->quantityText($ingredient, 'expected_quantity').'.');
        });

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
        DB::transaction(function () use ($ingredient, $request) {
            $ingredient->fill($request->validated());
            $ingredient->updated_by = $request->user()->id;
            // Chỉ báo khi đổi tên/lượng/đơn vị; đổi đơn giá (tài chính) hoặc ghi chú (chữ tự do) không gửi ra kênh.
            $changed = $ingredient->isDirty(['name', 'expected_quantity', 'actual_quantity', 'unit']);
            $ingredient->save();

            if ($changed) {
                ActivityLog::record($ingredient->batch, $request->user(), ActivityAction::IngredientUpdated, sprintf(
                    'Sửa nguyên liệu “%s”: dự kiến %s, thực tế đã dùng %s.', $ingredient->name,
                    $this->quantityText($ingredient, 'expected_quantity'), $this->quantityText($ingredient, 'actual_quantity'),
                ));
            }
        });

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

        DB::transaction(function () use ($ingredient, $request) {
            $ingredient->delete();
            ActivityLog::record($ingredient->batch, $request->user(), ActivityAction::IngredientDeleted,
                'Xóa nguyên liệu “'.$ingredient->name.'”.');
        });

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

    /**
     * "12,5 kg" hoặc "—" nếu chưa có (dùng cho lịch sử và thông báo kênh).
     *
     * @param  Ingredient  $ingredient  nguyên liệu
     * @param  string  $field  expected_quantity | actual_quantity
     */
    private function quantityText(Ingredient $ingredient, string $field): string
    {
        return $ingredient->{$field} === null ? Format::EMPTY : Format::quantity($ingredient->{$field}).' '.$ingredient->unit;
    }
}
