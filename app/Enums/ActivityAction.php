<?php

namespace App\Enums;

/**
 * Loại sự kiện trong lịch sử thay đổi (activity_logs.action).
 * Giá trị chuỗi giữ nguyên như dữ liệu đã lưu trước đây — không đổi giá trị của case đã có.
 */
enum ActivityAction: string
{
    case BatchCreated = 'batch.created';
    case BatchUpdated = 'batch.updated';
    case BatchStatusChanged = 'batch.status';
    case BatchCompleted = 'batch.completed';
    case BatchCancelled = 'batch.cancelled';
    case BatchReopened = 'batch.reopened';
    case BatchResultsUpdated = 'batch.results';
    case ExpenseCreated = 'expense.created';
    case ExpenseUpdated = 'expense.updated';
    case ExpenseDeleted = 'expense.deleted';
    case IngredientShortage = 'ingredient.shortage';
    case IngredientResolved = 'ingredient.resolved';
    case TaskStatusChanged = 'task.status';
    case TaskCreated = 'task.created';
    /** Giao / đổi / bỏ người phụ trách. */
    case TaskAssigned = 'task.assigned';
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserToggled = 'user.toggled';
    case PostCreated = 'post.created';
    case PostUpdated = 'post.updated';
    case PostPublished = 'post.published';
    /** Gỡ bài (bài trở về nháp). */
    case PostUnpublished = 'post.draft';
    case PostDeleted = 'post.deleted';
}
