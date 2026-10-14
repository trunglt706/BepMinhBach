<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BatchResultController;
use App\Http\Controllers\BatchStatusController;
use App\Http\Controllers\CookingBatchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseItemController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\MyTaskController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/cong-viec-cua-toi', MyTaskController::class)->name('my-tasks');

    Route::get('/tai-khoan/mat-khau', [AccountController::class, 'editPassword'])->name('account.password');
    Route::put('/tai-khoan/mat-khau', [AccountController::class, 'updatePassword'])->name('account.password.update');

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

    // Báo cáo
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/batches/{batch}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/batches/{batch}/export', [ReportController::class, 'exportBatch'])->name('reports.batch-export');

    // Quản lý tài khoản (Admin)
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
});
