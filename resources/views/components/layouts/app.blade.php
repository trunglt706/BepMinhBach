<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1b6343">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}Góp Gió</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @include('partials.head-assets')
</head>
<body>
<a class="visually-hidden-focusable btn btn-light position-fixed top-0 start-0 m-2" style="z-index: 2000" href="#main">Bỏ qua điều hướng</a>

@php
    $user = auth()->user();
    $navItems = [
        ['route' => 'dashboard', 'match' => ['dashboard'], 'icon' => 'bi-house-door', 'label' => 'Tổng quan', 'short' => 'Tổng quan', 'show' => true],
        ['route' => 'batches.index', 'match' => ['batches.*', 'ingredients.*', 'tasks.edit', 'expenses.*', 'channels.*'], 'icon' => 'bi-calendar3', 'label' => 'Chương trình', 'short' => 'Chương trình', 'show' => true,
            'badge' => $navLiveBatches ?? 0, 'badgeLabel' => 'chương trình đang diễn ra', 'badgeClass' => 'is-live'],
        ['route' => 'my-tasks', 'match' => ['my-tasks'], 'icon' => 'bi-check2-square', 'label' => 'Công việc của tôi', 'short' => 'Việc của tôi', 'show' => true,
            'badge' => $navTodayTasks ?? 0, 'badgeLabel' => 'việc của tôi hôm nay'],
        ['route' => 'reports.index', 'match' => ['reports.*'], 'icon' => 'bi-bar-chart-line', 'label' => 'Báo cáo', 'short' => 'Báo cáo', 'show' => $user->can('viewReports')],
    ];
@endphp

{{-- Sidebar (desktop) --}}
<aside class="app-sidebar d-print-none" aria-label="Điều hướng chính">
    <a class="brand px-2" href="{{ route('dashboard') }}">
        <span class="brand-mark"><i class="bi bi-wind" aria-hidden="true"></i></span>
        <span>Góp <span class="brand-accent">Gió</span></span>
    </a>
    <nav class="side-nav" id="side-nav" data-region>
        @foreach($navItems as $item)
            @continue(! $item['show'])
            @php($active = request()->routeIs(...$item['match']))
            <a href="{{ route($item['route']) }}" @class(['active' => $active]) @if($active) aria-current="page" @endif>
                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i> {{ $item['label'] }}
                @if(($item['badge'] ?? 0) > 0)
                    <span class="nav-badge {{ $item['badgeClass'] ?? '' }}" aria-label="{{ $item['badge'] }} {{ $item['badgeLabel'] ?? '' }}" title="{{ $item['badge'] }} {{ $item['badgeLabel'] ?? '' }}">{{ $item['badge'] }}</span>
                @endif
            </a>
        @endforeach
        <div class="side-nav-label">Công khai</div>
        @can('viewAny', App\Models\Post::class)
            <a href="{{ route('posts.index') }}" @class(['active' => request()->routeIs('posts.*')]) @if(request()->routeIs('posts.*')) aria-current="page" @endif>
                <i class="bi bi-newspaper" aria-hidden="true"></i> Bài viết
                @if(($navPendingPosts ?? 0) > 0)
                    <span class="nav-badge" aria-label="{{ $navPendingPosts }} bài chờ duyệt">{{ $navPendingPosts }}</span>
                @endif
            </a>
        @endcan
        @can('viewAny', App\Models\Sponsor::class)
            <a href="{{ route('sponsors.index') }}" @class(['active' => request()->routeIs('sponsors.*') && ! request()->routeIs('sponsors.public.*')]) @if(request()->routeIs('sponsors.index')) aria-current="page" @endif>
                <i class="bi bi-award" aria-hidden="true"></i> Đơn vị đồng hành
            </a>
        @endcan
        @can('viewAny', App\Models\ContactMessage::class)
            <a href="{{ route('contacts.index') }}" @class(['active' => request()->routeIs('contacts.*')]) @if(request()->routeIs('contacts.*')) aria-current="page" @endif>
                <i class="bi bi-envelope" aria-hidden="true"></i> Liên hệ
                @if(($navNewContacts ?? 0) > 0)
                    <span class="nav-badge" aria-label="{{ $navNewContacts }} liên hệ chưa xử lý">{{ $navNewContacts }}</span>
                @endif
            </a>
        @endcan
        <a href="{{ route('blog.index') }}" target="_blank" rel="noopener">
            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Xem trang công khai
        </a>
        @can('viewAny', App\Models\User::class)
            <div class="side-nav-label">Quản trị</div>
            <a href="{{ route('users.index') }}" @class(['active' => request()->routeIs('users.*')])>
                <i class="bi bi-people" aria-hidden="true"></i> Quản lý tài khoản
            </a>
        @endcan
    </nav>
    <div class="side-user">
        <div class="d-flex align-items-center gap-2 mb-2">
            <x-avatar :name="$user->name" />
            <div class="min-w-0">
                <div class="fw-semibold text-truncate">{{ $user->name }}</div>
                <div class="small text-body-secondary text-truncate">{{ $user->role->label() }}</div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('account.password') }}" data-modal class="btn btn-sm btn-outline-secondary flex-grow-1">Đổi mật khẩu</a>
            <form method="POST" action="{{ route('logout') }}" data-confirm="Bạn muốn đăng xuất khỏi Góp Gió?" data-confirm-label="Đăng xuất">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary btn-icon" title="Đăng xuất" aria-label="Đăng xuất" data-loading-text="Đang đăng xuất…">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    </div>
</aside>

<div class="app-main">
    {{-- Thanh trên (điện thoại/máy tính bảng) --}}
    <header class="app-topbar d-print-none">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-mark"><i class="bi bi-wind" aria-hidden="true"></i></span>
            <span>Góp <span class="brand-accent">Gió</span></span>
        </a>
        <div class="dropdown">
            <button class="btn btn-link p-0 d-flex align-items-center gap-1 text-body" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Tài khoản: {{ $user->name }}">
                <x-avatar :name="$user->name" />
                <i class="bi bi-chevron-down small" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li class="px-3 py-2">
                    <div class="fw-semibold">{{ $user->name }}</div>
                    <div class="small text-body-secondary">{{ $user->role->label() }}</div>
                </li>
                <li><hr class="dropdown-divider"></li>
                @can('viewAny', App\Models\Post::class)
                    <li><a class="dropdown-item" href="{{ route('posts.index') }}"><i class="bi bi-newspaper" aria-hidden="true"></i> Bài viết @if(($navPendingPosts ?? 0) > 0)<span class="badge rounded-pill text-bg-warning ms-1">{{ $navPendingPosts }}</span>@endif</a></li>
                @endcan
                @can('viewAny', App\Models\Sponsor::class)
                    <li><a class="dropdown-item" href="{{ route('sponsors.index') }}"><i class="bi bi-award" aria-hidden="true"></i> Đơn vị đồng hành</a></li>
                @endcan
                @can('viewAny', App\Models\ContactMessage::class)
                    <li><a class="dropdown-item" href="{{ route('contacts.index') }}"><i class="bi bi-envelope" aria-hidden="true"></i> Liên hệ
                        @if(($navNewContacts ?? 0) > 0)<span class="badge rounded-pill text-bg-warning ms-1">{{ $navNewContacts }}</span>@endif</a></li>
                @endcan
                <li><a class="dropdown-item" href="{{ route('blog.index') }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Xem trang công khai</a></li>
                @can('viewAny', App\Models\User::class)
                    <li><a class="dropdown-item" href="{{ route('users.index') }}"><i class="bi bi-people" aria-hidden="true"></i> Quản lý tài khoản</a></li>
                @endcan
                <li><a class="dropdown-item" href="{{ route('account.password') }}" data-modal><i class="bi bi-key" aria-hidden="true"></i> Đổi mật khẩu</a></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}" data-confirm="Bạn muốn đăng xuất khỏi Góp Gió?" data-confirm-label="Đăng xuất">
                        @csrf
                        <button type="submit" class="dropdown-item" data-loading-text="Đang đăng xuất…"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Đăng xuất</button>
                    </form>
                </li>
            </ul>
        </div>
    </header>

    <main id="main" class="app-content container-xxl" tabindex="-1">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible" role="status">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                <div>{{ session('success') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng thông báo"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                <div>{{ session('error') }}</div>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                <div>
                    <strong>Chưa lưu được.</strong> Vui lòng sửa các mục được đánh dấu bên dưới.
                    @if ($errors->has('to') || $errors->has('status'))
                        <ul class="mb-0 mt-1 ps-3">
                            @foreach (['to', 'status'] as $key)
                                @error($key)<li>{{ $message }}</li>@enderror
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif

        {{ $slot }}
    </main>
</div>

{{-- Thanh điều hướng dưới cho điện thoại: luôn chạm được "Việc của tôi". --}}
<nav class="bottom-nav d-print-none" id="bottom-nav" data-region aria-label="Điều hướng nhanh">
    @foreach($navItems as $item)
        @continue(! $item['show'])
        @php($active = request()->routeIs(...$item['match']))
        <a href="{{ route($item['route']) }}" @class(['active' => $active]) @if($active) aria-current="page" @endif>
            <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
            <span>{{ $item['short'] }}</span>
            @if(($item['badge'] ?? 0) > 0)
                <span class="nav-badge {{ $item['badgeClass'] ?? '' }}" aria-label="{{ $item['badge'] }} {{ $item['badgeLabel'] ?? '' }}" title="{{ $item['badge'] }} {{ $item['badgeLabel'] ?? '' }}">{{ $item['badge'] }}</span>
            @endif
        </a>
    @endforeach
</nav>

{{-- Modal dùng chung: nội dung form được tải bằng AJAX (xem app.js). --}}
<div class="modal fade" id="bmb-modal" tabindex="-1" aria-labelledby="bmb-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered modal-fullscreen-sm-down modal-lg"></div>
</div>

{{-- Hộp xác nhận thao tác khó hoàn tác (thay cho window.confirm). --}}
<div class="modal fade" id="bmb-confirm" tabindex="-1" aria-labelledby="bmb-confirm-title" aria-describedby="bmb-confirm-text" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-4">
                <div class="d-flex gap-3">
                    <span class="stat-icon tone-red flex-shrink-0"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
                    <div>
                        <h2 class="h5 mb-1" id="bmb-confirm-title">Bạn chắc chắn chứ?</h2>
                        <p class="mb-0 text-body-secondary" id="bmb-confirm-text"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Không, quay lại</button>
                <button type="button" class="btn btn-danger" data-confirm-ok>Đồng ý</button>
            </div>
        </div>
    </div>
</div>

<div class="toast-stack" id="bmb-toasts" aria-live="polite" aria-atomic="false"></div>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
