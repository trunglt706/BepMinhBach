@php
    use App\Enums\BatchStatus;
    use App\Http\Requests\ContactIndexRequest as F;
    use App\Support\Format;
    $states = [F::STATE_NEW => 'Chưa xử lý', F::STATE_HANDLED => 'Đã xử lý', F::STATE_ALL => 'Tất cả'];
    $filtered = $q !== '' || $state !== F::STATE_NEW || $topic;
@endphp
<x-layouts.app title="Liên hệ">
    <x-page-header title="Liên hệ" subtitle="Lời nhắn khách gửi từ trang công khai. Có thông tin cá nhân — chỉ dùng để liên lạc lại, xóa khi đã xử lý xong.">
        <x-slot:actions>
            <a href="{{ route('contact.create') }}" class="btn btn-outline-secondary" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Trang liên hệ</a>
            <x-filter-toggle target="contact-filter" :active="$filtered" />
        </x-slot:actions>
    </x-page-header>

    {{-- Thống kê toàn bộ liên hệ (không phụ thuộc bộ lọc bên dưới) --}}
    <section aria-labelledby="contact-stats-title" class="mb-3" id="contact-stats" data-region>
        <h2 id="contact-stats-title" class="visually-hidden">Thống kê liên hệ</h2>
        <div class="row g-2 g-md-3">
            <div class="col-6 col-lg">
                <x-stat label="Tổng liên hệ" icon="bi-envelope" tone="gray" :value="Format::number($stats['total'], '0')" hint="Từ trước tới nay" />
            </div>
            <div class="col-6 col-lg">
                <x-stat label="Chưa xử lý" icon="bi-hourglass-split" :tone="$stats['new'] ? 'amber' : 'green'" :value="Format::number($stats['new'], '0')"
                        :href="route('contacts.index')" hint="Bấm để xem" />
            </div>
            <div class="col-6 col-lg">
                <x-stat label="Đã xử lý" icon="bi-check2-circle" tone="green" :value="Format::number($stats['handled'], '0')"
                        :href="route('contacts.index', ['status' => F::STATE_HANDLED])" hint="Bấm để xem" />
            </div>
            <div class="col-6 col-lg">
                <x-stat label="Hôm nay" icon="bi-calendar-day" tone="blue" :value="Format::number($stats['today'], '0')"
                        :hint="Format::number($stats['week'], '0').' trong 7 ngày qua'" />
            </div>
        </div>
        @if($stats['total'] > 0)
            <div class="card mt-3">
                <div class="panel-header"><h3 class="panel-title h6 mb-0"><i class="bi bi-bar-chart-line" aria-hidden="true"></i> Theo chủ đề</h3></div>
                <div class="card-body">
                    <x-chart.bars caption="Số liên hệ theo chủ đề" empty="0" :rows="array_map(fn ($r) => [
                        'label' => $r['topic']->label(), 'value' => $r['count'], 'tone' => 'primary',
                    ], $stats['byTopic'])" />
                </div>
            </div>
        @endif
    </section>

    <form method="GET" action="{{ route('contacts.index') }}" id="contact-filter" class="card card-body mb-3" role="search" aria-label="Lọc liên hệ">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-lg-4">
                <label for="c_q" class="form-label small mb-1">Tìm theo tên, email hoặc số điện thoại</label>
                <div class="position-relative">
                    <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-body-secondary" aria-hidden="true"></i>
                    <input type="search" id="c_q" name="q" value="{{ $q }}" class="form-control ps-5" maxlength="100" placeholder="Ví dụ: Lan, 0901…">
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <label for="c_state" class="form-label small mb-1">Trạng thái</label>
                <select id="c_state" name="status" class="form-select">
                    @foreach($states as $value => $label)
                        <option value="{{ $value }}" @selected($state === $value)>{{ $label }}@if($value === F::STATE_NEW) ({{ $stats['new'] }})@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <label for="c_topic" class="form-label small mb-1">Chủ đề</label>
                <select id="c_topic" name="topic" class="form-select">
                    <option value="">Tất cả chủ đề</option>
                    @foreach($topics as $t)
                        <option value="{{ $t->value }}" @selected($topic === $t)>{{ $t->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1" data-loading-text="Đang lọc…">Lọc</button>
                @if($filtered)
                    <a href="{{ route('contacts.index') }}" class="btn btn-outline-secondary btn-icon" aria-label="Xóa bộ lọc" title="Xóa bộ lọc"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
                @endif
            </div>
        </div>
    </form>

    <div id="contacts-list" data-region>
    @if($messages->isEmpty())
        <div class="card">
            <x-empty-state icon="bi-envelope-open" :title="$state === F::STATE_NEW && ! $topic && $q === '' ? 'Không có liên hệ nào cần xử lý' : 'Không có liên hệ phù hợp'">
                @if($q !== '')
                    Không tìm thấy liên hệ có tên, email hoặc số điện thoại chứa “{{ $q }}”.
                    <x-slot:action><a href="{{ route('contacts.index', ['status' => F::STATE_ALL]) }}" class="btn btn-outline-primary">Xem tất cả liên hệ</a></x-slot:action>
                @else
                    Khách gửi lời nhắn ở trang <a href="{{ route('contact.create') }}" target="_blank" rel="noopener">Liên hệ</a>; lời nhắn mới sẽ hiện tại đây.
                @endif
            </x-empty-state>
        </div>
    @else
        <div class="card">
            <div class="panel-header">
                <h2 class="panel-title"><i class="bi bi-envelope" aria-hidden="true"></i> {{ $states[$state] }} <span class="panel-count">{{ $messages->total() }}</span></h2>
            </div>
            <ul class="list-group list-group-flush" data-skeleton="list">
                @foreach($messages as $m)
                    @php($isEmail = str_contains($m->contact, '@'))
                    <li class="list-group-item py-3 d-flex gap-3 align-items-start">
                        <span class="contact-topic-icon" aria-hidden="true"><i class="bi {{ $m->topic->icon() }}"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="fw-semibold">{{ $m->name }}</span>
                                <span class="tag-chip">{{ $m->topic->label() }}</span>
                                @if($m->handled_at)
                                    <span class="{{ BatchStatus::Completed->badgeClass() }}"><i class="bi bi-check2" aria-hidden="true"></i> Đã xử lý</span>
                                @endif
                            </div>
                            <p class="mb-2 contact-message">{{ $m->message }}</p>
                            <div class="meta-inline">
                                {{-- Chỉ in bản đã che; "Hiện" gọi máy chủ lấy bản đầy đủ (ghi nhật ký) rồi thay bằng link gọi/gửi mail. --}}
                                <span class="contact-masked" data-reveal-target>
                                    <i class="bi {{ $isEmail ? 'bi-envelope' : 'bi-telephone' }}" aria-hidden="true"></i>
                                    <span class="tabular" data-reveal-value>{{ App\Support\Mask::contact($m->contact) }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline" hidden
                                            data-reveal-url="{{ route('contacts.reveal', $m) }}"
                                            aria-label="Hiện đầy đủ {{ $isEmail ? 'email' : 'số điện thoại' }} của {{ $m->name }}">
                                        <i class="bi bi-eye" aria-hidden="true"></i> Hiện</button>
                                </span>
                                <span><i class="bi bi-clock" aria-hidden="true"></i> {{ Format::dateTime($m->created_at) }}</span>
                                @if($m->handled_at)
                                    <span><i class="bi bi-person-check" aria-hidden="true"></i> {{ $m->handler?->name ?? 'Tài khoản đã xóa' }} · {{ Format::dateTime($m->handled_at) }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-shrink-0 align-items-center">
                            @can('update', $m)
                                <form method="POST" action="{{ route('contacts.toggle', $m) }}" data-ajax>
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $m->handled_at ? 'btn-outline-secondary' : 'btn-outline-primary' }}" data-loading-text="Đang lưu…">
                                        <i class="bi {{ $m->handled_at ? 'bi-arrow-counterclockwise' : 'bi-check2' }}" aria-hidden="true"></i>
                                        {{ $m->handled_at ? 'Chưa xử lý' : 'Đã xử lý' }}
                                    </button>
                                </form>
                            @endcan
                            @can('delete', $m)
                                <x-row-menu :label="'liên hệ của '.$m->name" :delete-url="route('contacts.destroy', $m)"
                                            :confirm="'Xóa liên hệ của “'.$m->name.'”? Thông tin liên lạc sẽ bị xóa hẳn.'" />
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="mt-4">{{ $messages->links() }}</div>
    @endif
    </div>
</x-layouts.app>
