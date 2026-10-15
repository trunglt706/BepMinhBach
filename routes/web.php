<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BatchResultController;
use App\Http\Controllers\BatchStatusController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\CookingBatchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseItemController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\MyTaskController;
use App\Http\Controllers\NotificationChannelController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\PublicPostController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserDetailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    // Quên / đặt lại mật khẩu (link gửi qua email). Gửi form giới hạn theo IP.
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.update');
});

// Trang công khai cho khách: bài viết đã đăng. Giới hạn theo IP chống spam/cào dữ liệu.
// ETag: trang không đổi thì trình duyệt nhận 304 (không tải lại nội dung). "private" vì trang có token CSRF riêng.
// Trang chủ "/" là danh sách bài viết; "/bai-viet" (đường dẫn cũ) chuyển hẳn về "/" và giữ nguyên bộ lọc.
Route::middleware(['throttle:public', 'cache.headers:private;max_age=0;must_revalidate;etag'])->group(function () {
    Route::get('/', [PublicPostController::class, 'index'])->name('blog.index');
    Route::get('/bai-viet', fn (Request $request) => redirect()->to(
        route('blog.index', $request->query()), 301,
    ))->name('blog.legacy');
    Route::get('/bai-viet/{post:slug}', [PublicPostController::class, 'show'])->name('blog.show');
    Route::get('/chuong-trinh/{batch:public_id}', [PublicPostController::class, 'batch'])
        ->where('batch', '[0-9a-f]{16}')->name('blog.batch');
    // Đường dẫn cũ (trước khi đổi "Đợt nấu ăn" → "Chương trình"): chuyển hẳn sang đường dẫn mới, giữ ?page=.
    Route::get('/dot-nau/{publicId}', fn (Request $request, string $publicId) => redirect()->to(
        url('/chuong-trinh/'.$publicId).($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301,
    ))->where('publicId', '[0-9a-f]{16}')->name('blog.batch.legacy');
    Route::get('/sitemap.xml', [PublicPostController::class, 'sitemap'])->name('sitemap');
});

// Liên hệ: trang xem dùng giới hạn chung; gửi thì giới hạn chặt theo IP (throttle:contact)
// và mỗi client chỉ 1 liên hệ / ngày (ContactRequest).
Route::middleware('throttle:public')->get('/lien-he', [PublicContactController::class, 'create'])->name('contact.create');
Route::middleware('throttle:contact')->post('/lien-he', [PublicContactController::class, 'store'])->name('contact.store');

// Nội bộ (sau khi đăng nhập): mọi trang nằm dưới /admin. Giới hạn số thao tác theo TỪNG tài khoản (config/bmb.php).
// auth.session: đổi/đặt lại mật khẩu thì mọi phiên khác của tài khoản bị đăng xuất (so mã băm mật khẩu trong phiên).
Route::prefix('admin')->middleware(['auth', 'auth.session', 'active', 'throttle:user-actions'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/my-tasks', MyTaskController::class)->name('my-tasks');

    Route::get('/account/password', [AccountController::class, 'editPassword'])->name('account.password');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');

    // Đường dẫn tiếng Việt cũ của trang quản lý → đường dẫn tiếng Anh (giữ link đã lưu/đánh dấu)
    Route::permanentRedirect('/cong-viec-cua-toi', '/admin/my-tasks');
    Route::permanentRedirect('/tai-khoan/mat-khau', '/admin/account/password');
    Route::permanentRedirect('/lien-he', '/admin/contacts');

    // Chương trình
    Route::resource('batches', CookingBatchController::class);
    Route::get('/batches/{batch}/print', [CookingBatchController::class, 'print'])->name('batches.print');

    Route::post('/batches/{batch}/status', [BatchStatusController::class, 'change'])->name('batches.status');
    Route::get('/batches/{batch}/complete', [BatchStatusController::class, 'confirmComplete'])->name('batches.complete');
    Route::post('/batches/{batch}/complete', [BatchStatusController::class, 'complete'])->name('batches.complete.store');
    Route::get('/batches/{batch}/cancel', [BatchStatusController::class, 'confirmCancel'])->name('batches.cancel');
    Route::post('/batches/{batch}/cancel', [BatchStatusController::class, 'cancel'])->name('batches.cancel.store');
    Route::get('/batches/{batch}/reopen', [BatchStatusController::class, 'confirmReopen'])->name('batches.reopen');
    Route::post('/batches/{batch}/reopen', [BatchStatusController::class, 'reopen'])->name('batches.reopen.store');

    // Kết quả và chi phí
    Route::get('/batches/{batch}/results', [BatchResultController::class, 'edit'])->name('batches.results.edit');
    Route::put('/batches/{batch}/results', [BatchResultController::class, 'update'])->name('batches.results.update');
    Route::resource('batches.expenses', ExpenseItemController::class)
        ->only(['create', 'store', 'edit', 'update', 'destroy'])
        ->shallow();

    // Nguyên liệu
    Route::resource('batches.ingredients', IngredientController::class)
        ->only(['create', 'store', 'edit', 'update', 'destroy'])
        ->shallow();
    Route::post('/ingredients/{ingredient}/shortage', [IngredientController::class, 'reportShortage'])->name('ingredients.shortage');
    Route::delete('/ingredients/{ingredient}/shortage', [IngredientController::class, 'resolveShortage'])->name('ingredients.shortage.resolve');

    // Công việc
    Route::resource('batches.tasks', TaskController::class)
        ->only(['create', 'store', 'edit', 'update', 'destroy'])
        ->shallow();
    Route::post('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');

    // Kênh thông báo của chương trình (Slack / Telegram / Chatwork)
    Route::resource('batches.channels', NotificationChannelController::class)
        ->only(['create', 'store', 'edit', 'update', 'destroy'])
        ->shallow();
    Route::post('/channels/{channel}/toggle', [NotificationChannelController::class, 'toggle'])->name('channels.toggle');
    Route::post('/channels/{channel}/test', [NotificationChannelController::class, 'test'])->name('channels.test');

    // Báo cáo
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/batches/{batch}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/batches/{batch}/export', [ReportController::class, 'exportBatch'])->name('reports.batch-export');

    // Quản lý tài khoản (Admin)
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    // Chi tiết tài khoản (4 tab) và đăng xuất khỏi thiết bị — {device} là mã băm của phiên, không phải mã phiên.
    Route::get('/users/{user}', [UserDetailController::class, 'show'])->name('users.show');
    Route::delete('/users/{user}/devices', [UserDetailController::class, 'destroyDevices'])->name('users.devices.destroy');
    Route::delete('/users/{user}/devices/{device}', [UserDetailController::class, 'destroyDevice'])
        ->where('device', '[0-9a-f]{64}')->name('users.devices.destroy-one');

    // Bài viết công khai (điều phối viên trở lên)
    Route::resource('posts', PostController::class)->except('show');
    // Quy trình duyệt: tác giả gửi/rút yêu cầu; quản trị viên duyệt (đăng) hoặc từ chối; tác giả/quản trị gỡ bài.
    Route::post('/posts/{post}/submit', [PostController::class, 'submit'])->name('posts.submit');
    Route::post('/posts/{post}/withdraw', [PostController::class, 'withdraw'])->name('posts.withdraw');
    Route::post('/posts/{post}/publish', [PostController::class, 'publish'])->name('posts.publish');
    Route::get('/posts/{post}/reject', [PostController::class, 'rejectForm'])->name('posts.reject');
    Route::post('/posts/{post}/reject', [PostController::class, 'reject'])->name('posts.reject.store');
    Route::post('/posts/{post}/unpublish', [PostController::class, 'unpublish'])->name('posts.unpublish');

    // Liên hệ từ khách (điều phối viên trở lên)
    Route::get('/contacts', [ContactMessageController::class, 'index'])->name('contacts.index');
    Route::post('/contacts/{contact}/toggle', [ContactMessageController::class, 'toggle'])->name('contacts.toggle');
    // Danh sách chỉ hiện email/số điện thoại đã che; bấm "Hiện" mới lấy bản đầy đủ (có ghi nhật ký).
    // POST (không phải GET) để trình duyệt/proxy không lưu đệm và link không bị mở nhầm từ lịch sử.
    Route::post('/contacts/{contact}/reveal', [ContactMessageController::class, 'reveal'])->name('contacts.reveal');
    Route::delete('/contacts/{contact}', [ContactMessageController::class, 'destroy'])->name('contacts.destroy');
});
