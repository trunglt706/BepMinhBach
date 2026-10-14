@csrf
<x-input name="title" label="Tên việc" :value="$task->title" required maxlength="150" help="Ví dụ: Đi chợ mua rau, Nấu cơm, Chia suất" />
<div class="row">
    <div class="col-md-7">
        <x-select name="assignee_id" label="Người phụ trách" placeholder="— Chưa giao —"
                  :options="$assignees->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.$u->role->label().')'.($u->is_active ? '' : ' — đã khóa')])->all()"
                  :value="$task->assignee_id" help="Người được giao sẽ thấy việc này trong “Công việc của tôi”." />
    </div>
    <div class="col-md-5">
        <x-input name="due_at" label="Hạn hoàn thành" type="datetime-local" :value="$task->due_at?->format('Y-m-d\TH:i')" />
    </div>
</div>
<x-textarea name="description" label="Mô tả ngắn" :value="$task->description" rows="2" maxlength="2000" help="Người làm cần biết gì? Ví dụ: mua ở chợ X, trước 6 giờ." />
<x-textarea name="notes" label="Ghi chú nội bộ" :value="$task->notes" rows="2" maxlength="2000" />
