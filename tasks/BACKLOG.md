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

## Bổ sung theo yêu cầu (2026-10-15)
- [x] BM-075 Tinh chỉnh giao diện: lịch chọn ngày chỉ đọc + nút "Hôm nay"; ẩn thanh cuộn ngang ở menu/tab; bộ lọc thu gọn trên điện thoại (báo cáo, liên hệ, tài khoản, bài viết); "—" cho số liệu chưa có; "Cần xử lý" thu gọn; nút lên đầu trang (công khai). *(`UiLayoutTest`, `DashboardTest`, `ReportTest`)*
- [x] BM-076 Đơn giá nguyên liệu (tùy chọn), thành tiền và tổng tiền nguyên liệu; định dạng số lượng/số suất; đã phân phát ≤ đã chuẩn bị; biểu đồ báo cáo chi tiết chương trình. *(`IngredientTest`, `ResultsAndExpensesTest`, `ReportTest`)*
- [x] BM-077 Thông báo kênh cho mọi hoạt động chương trình, gửi nền qua queue (worker + cron trong `deploy.sh`). *(`NotificationChannelTest`)*
- [x] BM-078 Quên/đặt lại mật khẩu qua email; kiểm tra "Ghi nhớ đăng nhập"; đăng xuất phiên khác khi đổi mật khẩu (`auth.session`); mật khẩu tối thiểu 8. *(`PasswordResetTest`, `RememberMeTest`)*
- [x] BM-079 Phạm vi xem theo vai trò: điều phối viên/ghi nhận/tình nguyện viên chỉ thấy chương trình được phân công (danh sách, chi tiết, thống kê, báo cáo). *(`BatchVisibilityTest`)*
- [x] BM-080 Trang chi tiết tài khoản (quản trị viên): thông tin, nhật ký hoạt động, thông báo đã nhận, thiết bị đăng nhập + đăng xuất thiết bị. *(`UserDetailTest`)*
- [x] BM-081 Đổi thuật ngữ "Đợt nấu ăn" → "Chương trình" (link `/dot-nau` → `/chuong-trinh`, 301); đổi thương hiệu BếpMinhBạch → Góp Gió. *(`PublicBatchTest`, `SeoTest`)*
- [x] BM-082 SEO trang công khai (OG/Twitter mọi trang, JSON-LD trang chủ) và chặn lập chỉ mục khu quản lý/đăng nhập/trang lỗi. *(`SeoTest`)*
- [x] BM-083 Duyệt bài viết: điều phối viên quản lý bài của mình + gửi duyệt; quản trị viên duyệt/từ chối (kèm lý do)/gỡ. *(`PostReviewTest`, `PostTest`)*
- [x] BM-084 Liên hệ: che email/số điện thoại (công khai và quản lý), nút "Hiện" có ghi nhật ký. *(`ContactTest`)*
- [x] BM-085 Đơn vị tài trợ / hỗ trợ: danh bạ (phân loại, logo, tên, giới thiệu, website, ẩn/hiện), gắn vào chương trình kèm đóng góp; trang công khai `/don-vi-dong-hanh` (theo phân loại) và trang riêng từng đơn vị (các chương trình đã tham gia); khối "Đơn vị tham gia" theo phân loại trên trang chương trình công khai; sitemap. *(`SponsorTest`)*
- [x] BM-086 Rà soát QA/bảo mật/hiệu năng trước production: sửa N+1 "Công việc của tôi", lộ email ở quên mật khẩu, log xoay vòng, lịch dọn dẹp; giới hạn số truy vấn trang chính. *(`QueryBudgetTest`)*

## Ý tưởng sau MVP (chưa làm — chờ duyệt, đề xuất 15/10/2026)
Nguyên tắc: không thêm thanh toán online, không thu thập thông tin người nhận, ưu tiên tạo niềm tin với nhà hảo tâm.
Thứ tự khuyên làm: A3 → B3 → A1 → A2 → C2, sau đó B1/B2 tùy nhu cầu tuyển tình nguyện viên.

| # | Tính năng | Giá trị | Công sức |
|---|---|---|---|
| A1 | Ghi nhận khoản ủng hộ (thu) + cân đối thu–chi từng chương trình. Chỉ ghi sổ, không thanh toán online; người ủng hộ có thể ẩn danh | Minh bạch, là nền cho A2 | Vừa |
| A2 | Báo cáo minh bạch công khai (bật/tắt từng chương trình): tổng thu, tổng chi theo nhóm, số suất, đơn vị tham gia. Không hiện khoản chi chi tiết hay thông tin cá nhân | Rất cao với nhà hảo tâm | Vừa |
| A3 | "Con số tác động" trên trang chủ: tổng chương trình, suất đã phát, số tình nguyện viên, số đơn vị đồng hành | Cao, tạo thiện cảm ngay khi vào trang | Nhỏ |
| B1 | Đăng ký tình nguyện viên online cho từng chương trình: khách chọn ca/việc, điều phối viên duyệt rồi giao việc | Cao, giảm việc gom người qua tin nhắn | Vừa–Lớn |
| B2 | Điểm danh, giờ tham gia và giấy chứng nhận tình nguyện (PDF) | Giữ chân tình nguyện viên | Vừa |
| B3 | Sao chép chương trình / mẫu chương trình (nguyên liệu, công việc, đơn vị lặp lại) | Tiết kiệm thời gian điều phối | Nhỏ |
| B4 | Lịch chương trình (xem theo tháng) + nhắc việc qua email trước hạn | Giảm quên việc | Vừa |
| C1 | Đăng ký nhận bản tin cho nhà hảo tâm: email khi có bài mới/báo cáo minh bạch; có hủy đăng ký, xác nhận 2 bước | Tăng quay lại | Vừa |
| C2 | Xác thực 2 lớp (2FA) cho quản trị viên và điều phối viên | Bảo mật | Nhỏ–Vừa |
| C3 | Dùng được khi mạng yếu (PWA) trong ngày chạy chương trình: xem checklist, cập nhật việc, tự đồng bộ khi có mạng | Hữu ích tại hiện trường | Lớn |

Cần quyết định trước khi làm:
- A1/A2: có công khai tên người ủng hộ không (mặc định ẩn danh); công khai chi phí ở mức nào (chỉ tổng hay theo nhóm).
- B1: tình nguyện viên đăng ký online được tự tạo tài khoản, hay điều phối viên tạo sau khi duyệt.
- Có đổi mục "Nguyên liệu" thành "Vật phẩm" cho chương trình phát quà không.

Ý tưởng cũ (giữ lại):
- Thông báo qua email/Zalo (Slack/Telegram/Chatwork đã có — BM-073).
- Sao chép một chương trình cũ thành chương trình mới — gộp vào B3.
