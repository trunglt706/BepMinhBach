@php use App\Support\Format; @endphp
{{-- Chi tiết tài khoản (quản trị viên): 4 tab bằng link ?tab= — chạy không cần JS, giữ phân trang từng tab. --}}
<x-layouts.app :title="'Tài khoản: '.$user->name">
    <x-page-header :title="$user->name" :subtitle="$user->email" :back="route('users.index')" back-label="Quản lý tài khoản">
        <x-slot:meta>
            <div class="d-flex flex-wrap gap-1 mt-2">
                <span class="status-pill status-draft">{{ $user->role->label() }}</span>
                @if($user->is_active)
                    <span class="status-pill status-completed"><i class="bi bi-check-circle" aria-hidden="true"></i> Đang hoạt động</span>
                @else
                    <span class="status-pill status-cancelled"><i class="bi bi-lock" aria-hidden="true"></i> Đã khóa</span>
                @endif
                @if($user->is(auth()->user()))<span class="status-pill status-planned">Tài khoản của bạn</span>@endif
            </div>
        </x-slot:meta>
        <x-slot:actions>
            <a href="{{ route('users.edit', $user) }}" data-modal class="btn btn-outline-secondary"><i class="bi bi-pencil" aria-hidden="true"></i> Sửa</a>
            @can('toggleActive', $user)
                <form method="POST" action="{{ route('users.toggle-active', $user) }}" data-ajax
                      data-confirm="{{ $user->is_active ? 'Khóa tài khoản '.$user->name.'? Người này sẽ bị đăng xuất và không đăng nhập được.' : 'Mở khóa tài khoản '.$user->name.'?' }}">
                    @csrf
                    <button type="submit" class="btn w-100 {{ $user->is_active ? 'btn-outline-danger' : 'btn-outline-primary' }}">
                        <i class="bi {{ $user->is_active ? 'bi-lock' : 'bi-unlock' }}" aria-hidden="true"></i> {{ $user->is_active ? 'Khóa' : 'Mở khóa' }}
                    </button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <nav class="segmented mb-3" aria-label="Các mục của tài khoản">
        @foreach($tabs as $key => $t)
            <a href="{{ route('users.show', [$user, 'tab' => $key]) }}" @class(['active' => $tab === $key]) @if($tab === $key) aria-current="page" @endif>
                <i class="bi {{ $t['icon'] }} me-1" aria-hidden="true"></i>{{ $t['label'] }}
                @if($key === 'devices' && $devices->isNotEmpty())<span class="ms-1 tabular">({{ $devices->count() }})</span>@endif
            </a>
        @endforeach
    </nav>

    @if($tab === 'info')
        <div class="row g-3">
            <div class="col-lg-6">
                <section class="card h-100" aria-labelledby="h-info">
                    <div class="panel-header"><h2 id="h-info" class="panel-title"><i class="bi bi-person-vcard" aria-hidden="true"></i> Thông tin cơ bản</h2></div>
                    <div class="card-body">
                        <dl class="row mb-0 small">
                            <dt class="col-5 text-body-secondary fw-semibold">Họ tên</dt><dd class="col-7">{{ $user->name }}</dd>
                            <dt class="col-5 text-body-secondary fw-semibold">Email</dt><dd class="col-7 text-break">{{ $user->email }}</dd>
                            <dt class="col-5 text-body-secondary fw-semibold">Vai trò</dt><dd class="col-7">{{ $user->role->label() }}<div class="text-body-secondary">{{ $user->role->description() }}</div></dd>
                            <dt class="col-5 text-body-secondary fw-semibold">Trạng thái</dt><dd class="col-7">{{ $user->is_active ? 'Đang hoạt động' : 'Đã khóa' }}</dd>
                            <dt class="col-5 text-body-secondary fw-semibold">Tạo lúc</dt><dd class="col-7">{{ Format::dateTime($user->created_at) }}</dd>
                            <dt class="col-5 text-body-secondary fw-semibold">Cập nhật lúc</dt><dd class="col-7">{{ Format::dateTime($user->updated_at) }}</dd>
                            <dt class="col-5 text-body-secondary fw-semibold">Hoạt động gần nhất</dt>
                            <dd class="col-7 mb-0">{{ $stats['lastActivity'] ? Format::dateTime($stats['lastActivity']) : Format::EMPTY }}</dd>
                        </dl>
                    </div>
                </section>
            </div>
            <div class="col-lg-6">
                <div class="row g-2 g-md-3">
                    <div class="col-6"><x-stat label="Chương trình điều phối" icon="bi-calendar3" tone="blue" :value="Format::number($stats['coordinated'], '0')" hint="Là người điều phối" /></div>
                    <div class="col-6"><x-stat label="Chương trình tham gia" icon="bi-people" tone="green" :value="Format::number($stats['batches'], '0')" hint="Có việc được giao" /></div>
                    <div class="col-6"><x-stat label="Việc được giao" icon="bi-list-check" tone="amber" :value="Format::number($stats['tasks'], '0')"
                                               :hint="Format::number($stats['tasksDone'], '0').' việc đã xong'" /></div>
                    <div class="col-6"><x-stat label="Thao tác đã ghi" icon="bi-clock-history" tone="gray" :value="Format::number($stats['activities'], '0')"
                                               :href="route('users.show', [$user, 'tab' => 'activity'])" hint="Xem nhật ký" /></div>
                </div>
            </div>
        </div>

    @elseif($tab === 'activity')
        <section class="card" aria-labelledby="h-activity">
            <div class="panel-header"><h2 id="h-activity" class="panel-title"><i class="bi bi-clock-history" aria-hidden="true"></i> Nhật ký hoạt động
                <span class="panel-count">{{ $logs->total() }}</span></h2></div>
            @if($logs->isEmpty())
                <x-empty-state icon="bi-clock-history" title="Chưa có hoạt động nào được ghi" compact />
            @else
                <ul class="list-group list-group-flush" data-skeleton="list">
                    @foreach($logs as $log)
                        <li class="list-group-item py-3">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <span>{{ $log->summary }}</span>
                                <span class="small text-body-secondary text-nowrap">{{ Format::dateTime($log->created_at) }}</span>
                            </div>
                            @if($log->batch)
                                <a href="{{ route('batches.show', $log->batch) }}" class="small"><i class="bi bi-calendar3" aria-hidden="true"></i> {{ $log->batch->name }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
        <div class="mt-4">{{ $logs->links() }}</div>

    @elseif($tab === 'notifications')
        <section class="card" aria-labelledby="h-notifications">
            <div class="panel-header"><h2 id="h-notifications" class="panel-title"><i class="bi bi-bell" aria-hidden="true"></i> Thông báo đã nhận
                <span class="panel-count">{{ $notifications->total() }}</span></h2></div>
            @if($notifications->isEmpty())
                <x-empty-state icon="bi-bell" title="Chưa có thông báo nào" compact>
                    Ghi nhận khi tài khoản được giao việc hoặc được gửi email đặt lại mật khẩu.
                </x-empty-state>
            @else
                <ul class="list-group list-group-flush" data-skeleton="list">
                    @foreach($notifications as $n)
                        <li class="list-group-item py-3 d-flex gap-3">
                            <span class="stat-icon tone-blue"><i class="bi {{ $n->data['icon'] ?? 'bi-bell' }}" aria-hidden="true"></i></span>
                            <div class="min-w-0 flex-grow-1">
                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                    <span class="fw-semibold">{{ $n->data['title'] ?? 'Thông báo' }}</span>
                                    <span class="small text-body-secondary text-nowrap">{{ Format::dateTime($n->created_at) }}</span>
                                </div>
                                @if(! empty($n->data['body']))<div class="small text-body-secondary">{{ $n->data['body'] }}</div>@endif
                                @if(! empty($n->data['url']))<a href="{{ $n->data['url'] }}" class="small">Mở</a>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
        <div class="mt-4">{{ $notifications->links() }}</div>

    @else
        <section class="card" aria-labelledby="h-devices">
            <div class="panel-header">
                <h2 id="h-devices" class="panel-title"><i class="bi bi-laptop" aria-hidden="true"></i> Thiết bị đang đăng nhập <span class="panel-count">{{ $devices->count() }}</span></h2>
                @can('manageSessions', $user)
                    <form method="POST" action="{{ route('users.devices.destroy', $user) }}" data-ajax
                          data-confirm="Đăng xuất {{ $user->name }} khỏi mọi thiết bị{{ $user->is(auth()->user()) ? ' khác' : '' }}? “Ghi nhớ đăng nhập” cũng bị vô hiệu." data-confirm-label="Đăng xuất">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Đăng xuất mọi thiết bị</button>
                    </form>
                @endcan
            </div>
            @if(! $sessionTracking)
                <div class="card-body small text-body-secondary">Chỉ theo dõi được thiết bị khi phiên lưu trong cơ sở dữ liệu (SESSION_DRIVER=database).</div>
            @elseif($devices->isEmpty())
                <x-empty-state icon="bi-laptop" title="Không có thiết bị nào đang đăng nhập" compact>
                    Máy chỉ còn “ghi nhớ đăng nhập” (phiên đã hết hạn) không hiện ở đây — dùng “Đăng xuất mọi thiết bị” để vô hiệu.
                </x-empty-state>
            @else
                <ul class="list-group list-group-flush">
                    @foreach($devices as $d)
                        <li class="list-group-item py-3 d-flex gap-3 align-items-center">
                            <span class="stat-icon tone-gray"><i class="bi {{ $d['device']['mobile'] ? 'bi-phone' : 'bi-laptop' }}" aria-hidden="true"></i></span>
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-semibold">{{ $d['device']['label'] }}
                                    @if($d['current'])<span class="status-pill status-completed ms-1">Phiên đang dùng</span>@endif</div>
                                <div class="small text-body-secondary">IP {{ $d['ip'] ?? Format::EMPTY }} · hoạt động lúc {{ Format::dateTime($d['lastActivity']) }}</div>
                            </div>
                            @if(! $d['current'] && auth()->user()->can('manageSessions', $user))
                                <form method="POST" action="{{ route('users.devices.destroy-one', [$user, $d['key']]) }}" data-ajax
                                      data-confirm="Đăng xuất thiết bị {{ $d['device']['label'] }}?" data-confirm-label="Đăng xuất">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="Đăng xuất thiết bị {{ $d['device']['label'] }}">
                                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span class="d-none d-sm-inline"> Đăng xuất</span>
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</x-layouts.app>
