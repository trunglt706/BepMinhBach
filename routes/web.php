<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
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
    Route::get('/dot-nau/{batch:public_id}', [PublicPostController::class, 'batch'])
        ->where('batch', '[0-9a-f]{16}')->name('blog.batch');
    Route::get('/sitemap.xml', [PublicPostController::class, 'sitemap'])->name('sitemap');
});

// Liên hệ: trang xem dùng giới hạn chung; gửi thì giới hạn chặt theo IP (throttle:contact)
// và mỗi client chỉ 1 liên hệ / ngày (ContactRequest).
Route::middleware('throttle:public')->get('/lien-he', [PublicContactController::class, 'create'])->name('contact.create');
Route::middleware('throttle:contact')->post('/lien-he', [PublicContactController::class, 'store'])->name('contact.store');

// Nội bộ (sau khi đăng nhập): mọi trang nằm dưới /admin. Giới hạn số thao tác theo TỪNG tài khoản (config/bmb.php).
Route::prefix('admin')->middleware(['auth', 'active', 'throttle:user-actions'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/my-tasks', MyTaskController::class)->name('my-tasks');

    Route::get('/account/password', [AccountController::class, 'editPassword'])->name('account.password');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');

    // Đường dẫn tiếng Việt cũ của trang quản lý → đường dẫn tiếng Anh (giữ link đã lưu/đánh dấu)
    Route::permanentRedirect('/cong-viec-cua-toi', '/admin/my-tasks');
    Route::permanentRedirect('/tai-khoan/mat-khau', '/admin/account/password');
    Route::permanentRedirect('/lien-he', '/admin/contacts');

    // Đợt nấu ăn
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

    // Kênh thông báo của đợt (Slack / Telegram / Chatwork)
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

    // Bài viết công khai (điều phối viên trở lên)
    Route::resource('posts', PostController::class)->except('show');
    Route::post('/posts/{post}/publish', [PostController::class, 'togglePublish'])->name('posts.publish');

    // Liên hệ từ khách (điều phối viên trở lên)
    Route::get('/contacts', [ContactMessageController::class, 'index'])->name('contacts.index');
    Route::post('/contacts/{contact}/toggle', [ContactMessageController::class, 'toggle'])->name('contacts.toggle');
    Route::delete('/contacts/{contact}', [ContactMessageController::class, 'destroy'])->name('contacts.destroy');
});
