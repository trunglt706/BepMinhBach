<?php

namespace App\Http\Controllers;

use App\Enums\NotificationDriver;
use App\Http\Requests\NotificationChannelRequest;
use App\Models\BatchNotificationChannel;
use App\Models\CookingBatch;
use App\Support\BatchNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Kênh thông báo của chương trình (Slack / Telegram / Chatwork): thêm, sửa, bật/tắt, gửi thử, xóa.
 * Chỉ điều phối viên trở lên. Thông số bí mật không bao giờ được trả lại ra form/trang.
 */
class NotificationChannelController extends Controller
{
    /**
     * Form thêm kênh. Loại kênh có thể chọn sẵn qua ?driver=telegram.
     *
     * @param  Request  $request  yêu cầu hiện tại
     * @param  CookingBatch  $batch  chương trình
     */
    public function create(Request $request, CookingBatch $batch): View
    {
        Gate::authorize('manageNotifications', $batch);

        $used = $batch->notificationChannels()->pluck('driver')->map(fn ($d) => $d instanceof NotificationDriver ? $d : NotificationDriver::from($d));
        $available = array_values(array_filter(NotificationDriver::cases(), fn ($d) => ! $used->contains($d)));

        return view('channels.create', [
            'batch' => $batch,
            'channel' => new BatchNotificationChannel(['is_enabled' => true]),
            'drivers' => $available,
            'selected' => NotificationDriver::tryFrom((string) $request->query('driver')) ?? ($available[0] ?? null),
        ]);
    }

    /**
     * Lưu kênh mới.
     *
     * @param  NotificationChannelRequest  $request  dữ liệu đã kiểm tra + quyền
     * @param  CookingBatch  $batch  chương trình
     */
    public function store(NotificationChannelRequest $request, CookingBatch $batch): JsonResponse|RedirectResponse
    {
        $channel = $batch->notificationChannels()->make([
            'driver' => $request->driver(),
            'settings' => $request->settings(),
            'join_url' => $request->validated('join_url'),
            'is_enabled' => $request->boolean('is_enabled', true),
        ]);
        $channel->created_by = $request->user()->id;
        $channel->save();

        return $this->done($request, redirect()->route('batches.show', $batch)->withFragment('thong-bao'),
            'Đã thêm kênh '.$channel->driver->label().'. Bấm “Gửi thử” để kiểm tra kết nối.');
    }

    /**
     * Form sửa kênh (ô bí mật để trống, chỉ hiện 4 ký tự cuối của giá trị đã lưu).
     *
     * @param  BatchNotificationChannel  $channel  kênh
     */
    public function edit(BatchNotificationChannel $channel): View
    {
        Gate::authorize('manageNotifications', $channel->batch);

        return view('channels.edit', [
            'batch' => $channel->batch,
            'channel' => $channel,
            'drivers' => [$channel->driver],
            'selected' => $channel->driver,
        ]);
    }

    /**
     * Lưu thay đổi kênh.
     *
     * @param  NotificationChannelRequest  $request  dữ liệu đã kiểm tra + quyền
     * @param  BatchNotificationChannel  $channel  kênh
     */
    public function update(NotificationChannelRequest $request, BatchNotificationChannel $channel): JsonResponse|RedirectResponse
    {
        $channel->fill([
            'settings' => $request->settings(),
            'join_url' => $request->validated('join_url'),
            'is_enabled' => $request->boolean('is_enabled'),
        ]);
        // Đổi thông số kết nối: bỏ trạng thái lỗi cũ (sẽ biết kết quả ở lần gửi tiếp theo / gửi thử)
        if ($channel->isDirty('settings')) {
            $channel->forceFill(['last_error' => null, 'last_failed_at' => null]);
        }
        $channel->save();

        return $this->done($request, redirect()->route('batches.show', $channel->cooking_batch_id)->withFragment('thong-bao'),
            'Đã lưu kênh '.$channel->driver->label().'.');
    }

    /**
     * Bật / tắt gửi thông báo vào kênh.
     *
     * @param  Request  $request  yêu cầu hiện tại
     * @param  BatchNotificationChannel  $channel  kênh
     */
    public function toggle(Request $request, BatchNotificationChannel $channel): JsonResponse|RedirectResponse
    {
        Gate::authorize('manageNotifications', $channel->batch);

        $channel->update(['is_enabled' => ! $channel->is_enabled]);

        return $this->done($request, redirect()->route('batches.show', $channel->cooking_batch_id)->withFragment('thong-bao'),
            ($channel->is_enabled ? 'Đã bật' : 'Đã tắt').' thông báo qua '.$channel->driver->label().'.');
    }

    /**
     * Gửi ngay một tin thử để kiểm tra kết nối; báo lỗi cụ thể nếu không gửi được.
     *
     * @param  Request  $request  yêu cầu hiện tại
     * @param  BatchNotificationChannel  $channel  kênh
     * @param  BatchNotifier  $notifier  dịch vụ gửi
     */
    public function test(Request $request, BatchNotificationChannel $channel, BatchNotifier $notifier): JsonResponse|RedirectResponse
    {
        Gate::authorize('manageNotifications', $channel->batch);

        $error = $notifier->send($channel, $channel->batch, 'Tin thử: kênh thông báo đã kết nối với chương trình.', $request->user()->name);
        $back = redirect()->route('batches.show', $channel->cooking_batch_id)->withFragment('thong-bao');

        if ($error !== null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Chưa gửi được: '.$error], 422)
                : $back->with('error', 'Chưa gửi được: '.$error);
        }

        return $this->done($request, $back, 'Đã gửi tin thử vào '.$channel->driver->label().'.');
    }

    /**
     * Xóa kênh (xóa luôn thông số đã mã hóa).
     *
     * @param  Request  $request  yêu cầu hiện tại
     * @param  BatchNotificationChannel  $channel  kênh
     */
    public function destroy(Request $request, BatchNotificationChannel $channel): JsonResponse|RedirectResponse
    {
        Gate::authorize('manageNotifications', $channel->batch);

        $channel->delete();

        return $this->done($request, redirect()->route('batches.show', $channel->cooking_batch_id)->withFragment('thong-bao'),
            'Đã xóa kênh '.$channel->driver->label().'.');
    }
}
