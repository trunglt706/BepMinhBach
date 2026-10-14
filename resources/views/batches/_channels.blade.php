@php
    use App\Enums\NotificationDriver;
    use App\Support\Format;
    $canManage = auth()->user()->can('manageNotifications', $batch);
    $channels = $batch->notificationChannels->sortBy(fn ($c) => $c->driver->label())
        ->filter(fn ($c) => $canManage || $c->is_enabled);
    $canAdd = $canManage && $batch->notificationChannels->count() < count(NotificationDriver::cases());
@endphp
{{-- Kênh thông báo của đợt: mọi người thấy link tham gia; điều phối viên cấu hình, gửi thử, bật/tắt. --}}
<section id="thong-bao" class="card scroll-mt" aria-labelledby="h-thong-bao">
    <div class="panel-header">
        <h2 id="h-thong-bao" class="panel-title"><i class="bi bi-bell" aria-hidden="true"></i> Kênh thông báo</h2>
        @if($canAdd)
            <a href="{{ route('batches.channels.create', $batch) }}" data-modal class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Thêm kênh</a>
        @endif
    </div>

    @if($channels->isEmpty())
        <div class="card-body small text-body-secondary">
            @if($canManage)
                Chưa có kênh nào. Thêm kênh <strong>Slack</strong>, <strong>Telegram</strong> hoặc <strong>Chatwork</strong> để mọi thay đổi của đợt
                (trạng thái, giao việc, việc bị vướng, báo thiếu nguyên liệu…) tự gửi vào nhóm chat của đội bếp.
            @else
                Đợt này chưa có kênh thông báo. Điều phối viên sẽ báo khi có.
            @endif
        </div>
    @else
        <ul class="list-group list-group-flush">
            @foreach($channels as $channel)
                <li class="list-group-item py-3">
                    <div class="d-flex align-items-start gap-2">
                        <span class="channel-icon channel-{{ $channel->driver->value }}" aria-hidden="true"><i class="bi {{ $channel->driver->icon() }}"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="fw-semibold">{{ $channel->driver->label() }}</span>
                                @if($canManage)
                                    @if(! $channel->is_enabled)
                                        <span class="{{ App\Enums\BatchStatus::Draft->badgeClass() }}"><i class="bi bi-toggle-off" aria-hidden="true"></i> Đã tắt</span>
                                    @elseif($channel->isFailing())
                                        <span class="{{ App\Enums\BatchStatus::Cancelled->badgeClass() }}"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Lỗi gửi</span>
                                    @else
                                        <span class="{{ App\Enums\BatchStatus::Completed->badgeClass() }}"><i class="bi bi-toggle-on" aria-hidden="true"></i> Đang bật</span>
                                    @endif
                                @endif
                            </div>

                            @if($channel->join_url)
                                <a href="{{ $channel->join_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-primary mt-2">
                                    <i class="bi bi-link-45deg" aria-hidden="true"></i> Tham gia kênh {{ $channel->driver->label() }}
                                </a>
                            @else
                                <div class="small text-body-secondary mt-1">Chưa có link tham gia — nhờ điều phối viên thêm bạn vào nhóm.</div>
                            @endif

                            @if($canManage)
                                <div class="small mt-2">
                                    @if($channel->isFailing())
                                        <div class="text-danger"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> {{ $channel->last_error }}
                                            <span class="text-body-secondary">({{ Format::dateTime($channel->last_failed_at) }})</span></div>
                                    @elseif($channel->last_sent_at)
                                        <div class="text-body-secondary"><i class="bi bi-check2" aria-hidden="true"></i> Gửi gần nhất: {{ Format::dateTime($channel->last_sent_at) }}</div>
                                    @else
                                        <div class="text-body-secondary">Chưa gửi tin nào. Bấm “Gửi thử” để kiểm tra kết nối.</div>
                                    @endif
                                    @foreach($channel->driver->fields() as $key => $field)
                                        @continue($field['secret'])
                                        <div class="text-body-secondary">{{ $field['label'] }}: <span class="tabular">{{ $channel->displaySetting($key) }}</span></div>
                                    @endforeach
                                </div>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <form method="POST" action="{{ route('channels.test', $channel) }}" data-ajax>
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" data-loading-text="Đang gửi…"><i class="bi bi-send" aria-hidden="true"></i> Gửi thử</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                        @if($canManage)
                            <x-row-menu :label="'kênh '.$channel->driver->label()" :edit-url="route('channels.edit', $channel)"
                                        :delete-url="route('channels.destroy', $channel)"
                                        :confirm="'Xóa kênh '.$channel->driver->label().' của đợt này? Thông báo sẽ không gửi vào kênh nữa và token đã lưu bị xóa.'">
                                <li>
                                    <form method="POST" action="{{ route('channels.toggle', $channel) }}" data-ajax>
                                        @csrf
                                        <button type="submit" class="dropdown-item">
                                            <i class="bi {{ $channel->is_enabled ? 'bi-toggle-off' : 'bi-toggle-on' }}" aria-hidden="true"></i>
                                            {{ $channel->is_enabled ? 'Tạm tắt thông báo' : 'Bật lại thông báo' }}
                                        </button>
                                    </form>
                                </li>
                            </x-row-menu>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
        @if($canManage)
            <div class="panel-footer small text-body-secondary">
                Tự gửi khi: đổi trạng thái/hoàn tất/hủy/mở lại đợt, sửa thông tin đợt, thêm/giao việc, việc hoàn thành hoặc bị vướng,
                báo/gỡ thiếu nguyên liệu, cập nhật số suất, đăng bài về đợt. <strong>Không gửi chi phí</strong> và ghi chú tự do.
            </div>
        @endif
    @endif
</section>
