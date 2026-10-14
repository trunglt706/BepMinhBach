<x-layouts.app title="Quản lý tài khoản">
    <x-page-header title="Quản lý tài khoản" subtitle="Tài khoản không bị xóa để giữ lịch sử; hãy khóa khi người dùng không còn tham gia.">
        <x-slot:actions>
            <a href="{{ route('users.create') }}" data-modal class="btn btn-primary"><i class="bi bi-person-plus" aria-hidden="true"></i> Tạo tài khoản</a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('users.index') }}" class="card card-body mb-3" role="search" aria-label="Lọc tài khoản">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label for="u_q" class="form-label small mb-1">Tìm theo tên hoặc email</label>
                <div class="position-relative">
                    <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-body-secondary" aria-hidden="true"></i>
                    <input type="search" id="u_q" name="q" value="{{ $q }}" class="form-control ps-5">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label for="u_role" class="form-label small mb-1">Vai trò</label>
                <select id="u_role" name="role" class="form-select">
                    <option value="">Tất cả</option>
                    @foreach(App\Enums\Role::cases() as $r)
                        <option value="{{ $r->value }}" @selected($role === $r)>{{ $r->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="u_state" class="form-label small mb-1">Trạng thái</label>
                <select id="u_state" name="state" class="form-select">
                    <option value="">Tất cả</option>
                    @foreach(App\Enums\AccountState::cases() as $s)
                        <option value="{{ $s->value }}" @selected($state === $s)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2 d-grid">
                <button type="submit" class="btn btn-primary" data-loading-text="Đang tìm…">Lọc</button>
            </div>
        </div>
    </form>

    <div id="users-list" data-region>
    @if($users->isEmpty())
        <div class="card"><x-empty-state icon="bi-people" title="Không tìm thấy tài khoản phù hợp" /></div>
    @else
        <div class="card">
            <div class="panel-header">
                <h2 class="panel-title"><i class="bi bi-people" aria-hidden="true"></i> Tài khoản <span class="panel-count">{{ $users->total() }}</span></h2>
            </div>
            <ul class="list-group list-group-flush" data-skeleton="list">
                @foreach($users as $u)
                    <li @class(['list-group-item py-3 d-flex gap-3 align-items-center', 'bg-body-tertiary' => ! $u->is_active])>
                        <x-avatar :name="$u->name" size="lg" :class="$u->is_active ? '' : 'opacity-50'" />
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-truncate">
                                {{ $u->name }} @if($u->is(auth()->user()))<span class="small text-body-secondary fw-normal">(bạn)</span>@endif
                            </div>
                            <div class="small text-body-secondary text-truncate">{{ $u->email }}</div>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                <span class="status-pill status-draft">{{ $u->role->label() }}</span>
                                @if($u->is_active)
                                    <span class="status-pill status-completed"><i class="bi bi-check-circle" aria-hidden="true"></i> Đang hoạt động</span>
                                @else
                                    <span class="status-pill status-cancelled"><i class="bi bi-lock" aria-hidden="true"></i> Đã khóa</span>
                                @endif
                            </div>
                        </div>
                        <div class="d-flex flex-column flex-sm-row gap-2 flex-shrink-0">
                            <a href="{{ route('users.edit', $u) }}" data-modal class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil" aria-hidden="true"></i> Sửa</a>
                            @can('toggleActive', $u)
                                <form method="POST" action="{{ route('users.toggle-active', $u) }}" data-ajax
                                      data-confirm="{{ $u->is_active ? 'Khóa tài khoản '.$u->name.'? Người này sẽ bị đăng xuất và không đăng nhập được.' : 'Mở khóa tài khoản '.$u->name.'?' }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm w-100 {{ $u->is_active ? 'btn-outline-danger' : 'btn-outline-primary' }}">
                                        <i class="bi {{ $u->is_active ? 'bi-lock' : 'bi-unlock' }}" aria-hidden="true"></i> {{ $u->is_active ? 'Khóa' : 'Mở khóa' }}
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="mt-4">{{ $users->links() }}</div>
    @endif
    </div>
</x-layouts.app>
