# Architecture

## Mục tiêu
Ứng dụng Laravel server-rendered, dễ vận hành, ít chi phí và phù hợp nhóm tình nguyện. Không xây microservices cho MVP.

## Stack
- Laravel phiên bản tương thích với PHP có trên môi trường triển khai; xác minh trước khi chọn.
- Blade templates.
- Bootstrap; dùng JavaScript vừa đủ.
- MariaDB/MySQL.
- PHPUnit hoặc Pest theo repository.
- CSV export bằng response/stream phù hợp.
- PDF chỉ thêm thư viện khi xác minh tương thích và nhu cầu thực tế.

## Cấu trúc gợi ý
- `app/Models`
- `app/Http/Controllers`
- `app/Http/Requests`
- `app/Policies`
- `app/Services` (chỉ cho nghiệp vụ dùng chung/phức tạp)
- `resources/views/layouts`
- `resources/views/dashboard`
- `resources/views/cooking_batches`
- `resources/views/tasks`
- `resources/views/reports`
- `database/migrations`, `factories`, `seeders`
- `tests/Feature`, `tests/Unit`

Tuân theo cấu trúc Laravel hiện có nếu khác biệt nhưng hợp lý.

## Các miền nghiệp vụ
- User & Access
- Cooking Batch
- Ingredient Line
- Task Assignment
- Batch Result
- Expense Line
- Report/Export

## Quy tắc triển khai
- Controller mỏng; validation trong Form Request.
- Authorization bằng Policy/Gate hoặc cơ chế permission đã có.
- Eloquent relationships và query có eager loading phù hợp.
- Dùng database transaction khi cập nhật đồng thời nhiều dữ liệu có liên quan.
- Tránh N+1; không tải toàn bộ dữ liệu không giới hạn.
- Mọi thao tác thay đổi trạng thái qua action/service rõ ràng nếu có nhiều quy tắc.
- Dùng enum/constant cho trạng thái nếu phù hợp phiên bản PHP.
- Timestamps và timezone: lưu theo chuẩn Laravel; hiển thị Asia/Ho_Chi_Minh, xác nhận cấu hình deployment.
- Không hard-code secret; dùng `.env`, không commit credentials.
- Có seed tài khoản demo chỉ trong môi trường local/testing; không tạo mật khẩu mặc định yếu trên production.

## Gợi ý bảng dữ liệu
Xem `DATA_MODEL.md`. Agent được phép tinh chỉnh schema nếu có lý do rõ ràng và cập nhật tài liệu trước/sau thay đổi.

## Logging và vận hành
- Log lỗi kỹ thuật, không log mật khẩu/token hoặc thông tin người nhận không cần thiết.
- Có hướng dẫn backup/restore database ở mức vận hành cơ bản.
- Có `.env.example` không chứa bí mật.
- Không bật debug trên production.
