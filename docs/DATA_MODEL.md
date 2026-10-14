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

## posts
- `id`, `title` (≤ 200), `slug` (duy nhất; tạo từ tiêu đề, giữ nguyên sau khi đã đăng), `excerpt` (≤ 500, tùy chọn), `body` (văn bản thuần, ≤ 20.000)
- `status`: `draft` | `published`; `published_at` (lần đăng đầu; gỡ rồi đăng lại giữ nguyên)
- `cooking_batch_id` (tùy chọn, FK → cooking_batches, **null on delete**: xóa đợt nháp không xóa bài)
- `author_id` (FK → users, không hiện công khai), timestamps
- `body` là HTML **đã lọc** (allowlist, `App\Support\PostHtml`) — lọc ngay khi gán vào model; `body_text` là chữ thuần dùng cho tìm kiếm/tóm tắt/SEO.
- `view_count`: lượt xem (mỗi người xem tính 1 lần / 6 giờ, không tính bot/người quản lý); tăng bằng query builder nên không đổi `updated_at`.

## post_images
- `post_id` (FK, cascade), `kind`: `cover` (tối đa 1) | `gallery` (tối đa `BMB_POST_MAX_IMAGES`, mặc định 12)
- `path`: đường dẫn gốc không đuôi (`posts/2026/10/<ngẫu nhiên>`); file thật `public/uploads/{path}-{width}.webp`
- `widths` (JSON, ví dụ `[480, 960, 1600]` — không phóng to quá ảnh gốc), `width`, `height` (ảnh sau khi xoay đúng hướng), `caption` (≤ 200), `sort_order`
- Chỉ lưu WebP do máy chủ mã hóa lại (không lưu file gốc, không EXIF/GPS). Xóa bản ghi → xóa file sau khi giao dịch thành công.

## cooking_batches (bổ sung)
- `reopen_reason` (≤ 500), `reopened_at`: lý do mở lại gần nhất. Trước đây lý do nằm trong tóm tắt activity log; nay log không chứa chữ tự do.

## tags, post_tag
- `tags`: `id`, `name` (≤ 40), `slug` (duy nhất — "Cơm Chay" và "cơm chay" là một thẻ)
- `post_tag`: khóa chính (`post_id`, `tag_id`), xóa theo bài/thẻ. Mỗi bài 0–10 thẻ; thẻ không còn bài nào được dọn tự động.

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

## Bổ sung 14/10/2026
- `cooking_batches.public_id` char(16) unique — mã ngẫu nhiên (hex) cho URL công khai `/dot-nau/{public_id}`; tự sinh khi tạo đợt, migration gán cho đợt cũ.
- `cooking_batches.cover_path`, `cover_widths` (json), `cover_width`, `cover_height` — ảnh nền đợt (WebP 480/960/1600, thư mục `uploads/batches/Y/m`).
- `contact_messages`: `topic` (enum `ContactTopic`), `name` (100), `contact` (150, email viết thường hoặc SĐT không dấu cách), `message` (text ≤ 2000), `client_hash` char(64) = HMAC(IP), `sent_on` date, `handled_at`, `handled_by` → users (null on delete), timestamps. Index: (`client_hash`, `sent_on`), (`contact`, `sent_on`), (`handled_at`, `created_at`).

- `cooking_batches.starts_at` (datetime) → **`starts_on` (date)** — đợt nấu chỉ cần ngày bắt đầu (yêu cầu 14/10/2026). Migration `2026_10_14_001300` chép `DATE(starts_at)` rồi xóa cột cũ (đã thử với dữ liệu có sẵn + rollback trên MySQL 8.4 và SQLite). Model luôn lưu `Y-m-d` (accessor/mutator; không dùng cast `date` vì cast ghi kèm `00:00:00` → so sánh khoảng ngày sai trên SQLite); mọi truy vấn khoảng ngày truyền chuỗi `Y-m-d`.
- `batch_notification_channels`: `cooking_batch_id` (cascade), `driver` (`slack`/`telegram`/`chatwork`, unique theo đợt), `settings` (text, **mã hóa** `encrypted:array` bằng APP_KEY — webhook/token/chat id/room id), `join_url` (≤ 500, https đúng tên miền dịch vụ), `is_enabled`, `last_sent_at`, `last_failed_at`, `last_error` (≤ 255, đã che token), `created_by` → users. **Đổi APP_KEY thì phải nhập lại token.**
- `activity_logs.action` thêm `task.created`, `task.assigned`.
