# MVP Backlog

Trạng thái: `[ ]` chưa làm, `[-]` đang làm / làm một phần, `[x]` đã xác minh hoàn tất (có test tự động hoặc kiểm tra thực tế ghi trong `docs/IMPLEMENTATION_LOG.md`).

Cập nhật lần cuối: 2026-10-14. Bộ test: `php artisan test` → 188 test pass trên SQLite và trên MySQL 8.4 (`gopgio_test`). Báo cáo nghiệm thu: `docs/ACCEPTANCE_REPORT.md`.

## P0 — Nền tảng
- [x] BM-001 Khảo sát repository, runtime, dependencies, git status và test hiện có. *(Repo chỉ có tài liệu, chưa phải git repo; PHP 8.3 Laragon / 8.2 trên PATH; MySQL 8.4 đang chạy nhưng cần mật khẩu root.)*
- [x] BM-002 Thiết lập ứng dụng Laravel/Blade/Bootstrap. *(Laravel 12.70, Bootstrap 5.3.8 + Bootstrap Icons dạng file tĩnh trong `public/vendor`.)*
- [x] BM-003 Cấu hình `.env.example`, hướng dẫn cài đặt và database. *(MySQL 8.4 đã cấu hình; bộ test pass trên DB `gopgio_test`.)*
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
- [x] BM-050 Rà soát UX trên mobile và desktop. *(Chrome headless 360/768/1280px × 26 trang/trạng thái: không tràn ngang, không lỗi JS; đã sửa bảng in tràn, thanh công cụ soạn thảo bị cắt, thẻ tag < 24px. Thử trên điện thoại thật với tình nguyện viên: việc của buổi thử nghiệm — xem báo cáo nghiệm thu.)*
- [x] BM-051 Rà soát bảo mật/quyền riêng tư/IDOR. *(Thêm header bảo mật + CSP, cookie Secure khi HTTPS, `TRUSTED_PROXIES`, bỏ `javascript:` ở trang lỗi, lệnh `bmb:check-production`; `ProductionHardeningTest`; E2E xác nhận CSP không chặn trình soạn thảo/ảnh/PhotoSwipe.)*
- [x] BM-052 Chạy toàn bộ test và xử lý lỗi. *(147/147 pass trên SQLite và MySQL; Pint sạch.)*
- [-] BM-053 Hoàn thiện README, hướng dẫn backup/restore và triển khai. *(`docs/DEPLOYMENT.md` + script một lệnh `deploy/deploy.sh` cho VPS (OQ-12: VPS). **Còn lại:** chạy script trên VPS thật — chưa có quyền truy cập VPS nên chưa xác minh.)*
- [x] BM-054 Báo cáo nghiệm thu: đã làm, test, hạn chế và việc còn lại. *(`docs/ACCEPTANCE_REPORT.md`)*

## Bổ sung theo yêu cầu (2026-10-14)
- [x] BM-060 Giới hạn tần suất theo từng tài khoản (ghi/đọc) và theo IP cho trang công khai. *(`RateLimitTest`)*
- [x] BM-061 Bài viết công khai: quản lý (điều phối viên), danh sách + chi tiết cho khách, tìm theo từ khóa/thẻ/đợt nấu, thông tin đợt nấu dạng tổng hợp. *(`PostTest`)*
- [x] BM-062 Ảnh bài viết: ảnh đại diện + album (WebP nhiều kích thước, giới hạn số ảnh, kiểm tra kiểu theo nội dung), trình soạn thảo, ô nhập thẻ, SEO, xem ảnh lớn, lượt xem, bộ nhớ đệm trang công khai. *(`PostMediaTest`, `PublicPostCacheTest`)*
- [x] BM-063 Rà soát mã: PHPDoc đầy đủ, mọi validation trong FormRequest, activity log không chứa dữ liệu nhạy cảm, so sánh dùng Enum/hằng số. *(`RequestNormalizationTest`)*

## Bổ sung theo yêu cầu (2026-10-14, đợt 2)
- [x] BM-064 Trang nội bộ chuyển sang `/admin/*`; `/` là danh sách bài viết; `/bai-viet` chuyển 301 về `/` (giữ bộ lọc). *(`PublicHomeTest`)*
- [x] BM-065 Ảnh tải lười chỉ khi vào khung nhìn (IntersectionObserver + `<noscript>` cho SEO), ảnh ưu tiên `fetchpriority=high`, nền thay thế khi chưa có ảnh/ảnh lỗi, hiệu ứng rê chuột. *(`PublicHomeTest`, kiểm tra Chrome)*
- [x] BM-066 Trang liên hệ công khai: chủ đề gợi ý, giới hạn tần suất + 1 liên hệ/client/ngày, bẫy bot; quản lý nội bộ có tìm kiếm (tên, email/SĐT) và thống kê. Footer chỉ còn bản quyền; header thêm "Liên hệ", bỏ "Quản lý". *(`ContactTest`)*
- [x] BM-067 Ảnh nền đợt nấu; slider đợt đang diễn ra trên trang chủ (chỉ ngày, không giờ); trang đợt nấu công khai `/dot-nau/{public_id}` (thông tin + bài viết phân trang). *(`PublicBatchTest`)*
- [x] BM-068 Kiểm tra phía trình duyệt trước khi gửi mọi form (required, độ dài, email/SĐT, số, trình soạn thảo). *(`ContactTest`, kiểm tra Chrome: form trống không gửi request)*
- [x] BM-069 Header công khai có ô tìm bài viết; trang chủ bỏ form lọc + danh sách thẻ; 6 bài/trang; thẻ bài có tác giả + badge đợt nấu nổi trên ảnh. *(`PublicHomeTest`, `PostTest`)*
- [x] BM-071 Tinh chỉnh trang công khai: số bài cạnh tiêu đề, × trên ô tìm gửi ngay, người viết ở chi tiết bài, bỏ ảnh đầu bài, breadcrumb đợt theo mã hash, ảnh thẻ phủ kín, nút slider chỉ hiện khi rê chuột; kiểm tra lượt xem (đúng — người quản lý không được tính). *(`PublicHomeTest`)*
- [x] BM-072 Sửa layout chi tiết đợt (admin) bị lỗi do trùng class `.batch-hero` với trang công khai; ảnh nền làm banner đầu trang đợt (admin); gợi ý kích thước ảnh nền 1600×900; ngày bắt đầu đợt chỉ còn NGÀY (`starts_on` kiểu DATE, kể cả CSDL). *(`CookingBatchTest`, `ReportTest`, thử migration với dữ liệu có sẵn)*
- [x] BM-073 Kênh thông báo cho từng đợt nấu (Slack / Telegram / Chatwork): cấu hình theo thông số riêng từng kênh (token mã hóa), link "Tham gia kênh" cho mọi người, gửi thử, bật/tắt; mọi sự kiện quan trọng của đợt tự đẩy vào kênh (trừ chi phí và ghi chú tự do). *(`NotificationChannelTest`, kiểm tra Chrome)*
- [x] BM-074 Slug trang quản lý sang tiếng Anh (`/admin/my-tasks`, `/admin/account/password`, `/admin/contacts`; đường dẫn cũ chuyển 301); badge "việc của tôi hôm nay" và "đợt đang diễn ra" trên menu; chọn ngày bằng flatpickr; ô lịch cao vừa nội dung; xác nhận khi đăng xuất; bỏ cảnh báo "thay đổi chưa lưu". *(`PublicHomeTest`, `UiLayoutTest`, `AjaxFlowTest`, kiểm tra Chrome)*
- [-] BM-070 Triển khai VPS một lệnh `deploy/deploy.sh`. *(Viết xong, `bash -n` + test tĩnh; **chưa chạy trên VPS/container thật**.)*

## Ý tưởng sau MVP (chưa làm, không thuộc phạm vi)
- Thông báo qua email/Zalo (Slack/Telegram/Chatwork đã có — BM-073).
- Sao chép một đợt cũ thành đợt mới (mẫu nguyên liệu/công việc).
