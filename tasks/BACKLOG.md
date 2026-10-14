# MVP Backlog

Trạng thái: `[ ]` chưa làm, `[-]` đang làm / làm một phần, `[x]` đã xác minh hoàn tất (có test tự động hoặc kiểm tra thực tế ghi trong `docs/IMPLEMENTATION_LOG.md`).

Cập nhật lần cuối: 2026-10-14. Bộ test: `php artisan test` → 83 test pass (478 assertions) trên SQLite và trên MySQL (`bepminhbach_test`).

## P0 — Nền tảng
- [x] BM-001 Khảo sát repository, runtime, dependencies, git status và test hiện có. *(Repo chỉ có tài liệu, chưa phải git repo; PHP 8.3 Laragon / 8.2 trên PATH; MySQL 8.4 đang chạy nhưng cần mật khẩu root.)*
- [x] BM-002 Thiết lập ứng dụng Laravel/Blade/Bootstrap. *(Laravel 12.70, Bootstrap 5.3.8 + Bootstrap Icons dạng file tĩnh trong `public/vendor`.)*
- [x] BM-003 Cấu hình `.env.example`, hướng dẫn cài đặt và database. *(MySQL 8.4 đã cấu hình; bộ test 83/83 pass trên DB `bepminhbach_test`.)*
- [x] BM-004 Xác thực, quản lý vai trò và kiểm soát quyền. *(Đăng nhập email, rate limit, khóa tài khoản, 4 vai trò phân cấp, Policies cho mọi thao tác ghi.)*
- [x] BM-005 Layout responsive, navigation và trạng thái giao diện cơ bản. *(Thiết kế lại 2026-10-14: sidebar desktop, bottom nav mobile, design system.)*
- [x] BM-006 Factories/seeders dữ liệu demo an toàn cho local/testing. *(DemoSeeder chỉ chạy ở local/testing; lệnh `bmb:create-admin` cho production.)*

## P1 — Đợt nấu ăn
- [x] BM-010 CRUD đợt nấu và workflow trạng thái. *(Nháp ↔ Đã lên kế hoạch → Đang diễn ra → Hoàn tất; Hủy; Mở lại có lý do. Xóa chỉ với đợt nháp trống.)*
- [x] BM-011 Danh sách, tìm kiếm, lọc, phân trang.
- [x] BM-012 Trang tổng quan và empty states.
- [x] BM-013 Feature tests cho quyền và validation đợt nấu. *(`tests/Feature/CookingBatchTest.php`)*

## P1 — Checklist
- [x] BM-020 Quản lý nguyên liệu theo đợt.
- [x] BM-021 Tạo công việc và phân công tình nguyện viên.
- [x] BM-022 Trang “Công việc của tôi”.
- [x] BM-023 Cập nhật tiến độ, báo vướng (bắt buộc lý do) và báo thiếu nguyên liệu.
- [x] BM-024 Tests cho nguyên liệu, task và authorization. *(`IngredientTest`, `TaskTest`)*

## P1 — Kết quả
- [x] BM-030 Ghi nhận suất dự kiến/chuẩn bị/phân phát. *(Có phát hiện sửa đồng thời bằng `results_version`.)*
- [x] BM-031 Ghi nhận chi phí nguyên liệu.
- [x] BM-032 Cảnh báo chênh lệch và ghi chú giải thích.
- [x] BM-033 Quy tắc hoàn tất/hủy đợt và bảo vệ lịch sử. *(Đợt hoàn tất/hủy bị khóa; activity log tối thiểu.)*
- [x] BM-034 Tests cho kết quả và chi phí. *(`ResultsAndExpensesTest`)*

## P2 — Báo cáo
- [x] BM-040 Báo cáo chi tiết theo đợt.
- [x] BM-041 Lọc báo cáo theo ngày/trạng thái.
- [x] BM-042 Xuất CSV an toàn. *(UTF-8 BOM, chống formula injection.)*
- [x] BM-043 Checklist in A4.
- [ ] BM-044 PDF — **hoãn** theo OQ-05: dùng “In → Lưu PDF” của trình duyệt. Chưa thêm thư viện.
- [x] BM-045 Tests báo cáo, export và print view. *(`ReportTest`)*

## P2 — Hoàn thiện
- [-] BM-050 Rà soát UX trên mobile và desktop. *(Người dùng đã kiểm tra bản đầu trên điện thoại; giao diện đã thiết kế lại toàn bộ — cần xem lại bản mới ở 360px/768px/desktop.)*
- [-] BM-051 Rà soát bảo mật/quyền riêng tư/IDOR. *(Đã có test IDOR, quyền theo vai trò, CSRF mặc định, escape Blade, CSV injection. Chưa rà soát cấu hình production.)*
- [x] BM-052 Chạy toàn bộ test và xử lý lỗi. *(78/78 pass; Pint sạch.)*
- [-] BM-053 Hoàn thiện README, hướng dẫn backup/restore và triển khai. *(Đã có cài đặt local, backup/restore cơ bản; hướng dẫn deploy cụ thể chờ biết hosting — OQ-12.)*
- [ ] BM-054 Báo cáo nghiệm thu: đã làm, test, hạn chế và việc còn lại — sau khi xong BM-050 và thử với MySQL.

## Ý tưởng sau MVP (chưa làm, không thuộc phạm vi)
- Thông báo (email/Zalo) khi được giao việc hoặc báo thiếu nguyên liệu.
- Sao chép một đợt cũ thành đợt mới (mẫu nguyên liệu/công việc).
