# Data Model (đề xuất ban đầu)

Đây là mô hình khởi điểm, không phải ràng buộc cứng. Claude cần kiểm tra nhu cầu thực tế, Laravel version và repository trước khi tạo migration.

## users
- id
- name
- email (unique)
- password
- role hoặc bảng role/permission nếu dự án đã dùng package phù hợp
- is_active
- timestamps

## cooking_batches
- id
- name
- starts_at
- location_text nullable
- expected_servings unsigned integer
- prepared_servings nullable unsigned integer
- distributed_servings nullable unsigned integer
- coordinator_id FK users
- status (`draft`, `planned`, `in_progress`, `completed`, `cancelled`)
- notes nullable
- result_notes nullable
- completed_at nullable
- created_by FK users
- timestamps, soft deletes chỉ khi có lý do

## ingredients
Một dòng nguyên liệu thuộc một đợt:
- id
- cooking_batch_id FK
- name
- expected_quantity decimal(12,3) hoặc precision phù hợp
- actual_quantity nullable decimal(12,3)
- unit (string)
- needs_restock boolean default false
- notes nullable
- created_by / updated_by nếu audit cần thiết
- timestamps

Không phải kho tập trung; không có số dư tồn kho toàn hệ thống.

## tasks
- id
- cooking_batch_id FK
- title
- description nullable
- assignee_id nullable FK users
- due_at nullable
- status (`todo`, `in_progress`, `blocked`, `done`)
- blocked_note nullable
- completed_at nullable
- completed_by nullable FK users
- created_by FK users
- timestamps

## expense_items
- id
- cooking_batch_id FK
- name
- amount_vnd unsigned bigint hoặc decimal phù hợp DB; ưu tiên integer VND
- note nullable
- recorded_by FK users
- timestamps

## activity_logs (tùy chọn)
Chỉ triển khai nếu cần lịch sử kiểm tra các thay đổi quan trọng:
- id
- actor_id nullable
- subject_type / subject_id hoặc bảng audit cụ thể
- action
- safe summary
- timestamps

Không ghi payload nhạy cảm đầy đủ vào log. Không xây audit framework phức tạp cho mọi trường ngay từ đầu.

## Quan hệ
- User has many batches as coordinator/creator.
- CookingBatch has many ingredients, tasks, expense items.
- Task belongs to batch and may belong to an assignee.
- Expense item belongs to batch and recorder.

## Ràng buộc
- Số suất không âm.
- Chi phí không âm.
- Số lượng nguyên liệu không âm; precision phải hỗ trợ đơn vị thập phân.
- FK và index trên khóa ngoại, trạng thái, ngày giờ phục vụ lọc.
- Tên bảng/cột theo Laravel conventions.
- Không lưu danh sách hoặc thông tin định danh người nhận suất ăn trong MVP.
- Cân nhắc concurrency/optimistic handling nếu nhiều người cập nhật cùng kết quả.

## Schema đã triển khai (2026-10-14)
Khác biệt so với đề xuất ban đầu ở trên:
- `users.role` (string: `volunteer` | `recorder` | `coordinator` | `admin`, phân cấp) và `users.is_active`.
- `cooking_batches`: thêm `estimated_cost_vnd` (tùy chọn, OQ-03), `variance_note` (thay `result_notes`, giải thích chênh lệch), `completion_notes` (rút kinh nghiệm khi hoàn tất), `results_updated_by/at`, `results_version` (phát hiện hai người cùng sửa), `cancellation_reason`, `cancelled_at`. Không dùng soft delete: chỉ xóa cứng đợt nháp trống, còn lại dùng hủy.
- `ingredients`: thêm `restock_note`, `restock_reported_by/at`; `unit` tối đa 30 ký tự.
- `tasks`: thêm `notes`, `status_updated_by/at`.
- `expense_items.note` tối đa 500 ký tự.
- `activity_logs` đã triển khai ở mức tối thiểu: `cooking_batch_id` (nullable — thao tác tài khoản không gắn đợt), `user_id`, `action`, `summary` (≤ 500 ký tự, không chứa mật khẩu), `created_at`.
- Thời gian lưu theo `APP_TIMEZONE=Asia/Ho_Chi_Minh`.
