# Báo cáo nghiệm thu MVP — Góp Gió

- **Ngày lập:** 14/10/2026 · **Phiên bản:** nhánh `main` (sau commit `b4ebf18` + thay đổi BM-050/051/053/054 trong phiên này)
- **Phạm vi:** đối chiếu `docs/PRODUCT_REQUIREMENTS.md` (FR-01 → FR-09), `docs/DEFINITION_OF_DONE.md` và `tasks/BACKLOG.md`.
- **Kết luận:** MVP **sẵn sàng thử nghiệm có kiểm soát với một bếp** (dữ liệu thật, ít người dùng). Chưa triển khai lên máy chủ thật vì chưa chốt nơi triển khai (OQ-12), và chưa thử với người dùng thật trên điện thoại thật — xem mục 4.

## 1. Đối chiếu yêu cầu chức năng

| Yêu cầu | Trạng thái | Bằng chứng |
|---|---|---|
| FR-01 Tài khoản, vai trò, khóa tài khoản | Đạt | `AuthTest`, `UserManagementTest`; 4 vai trò phân cấp, Policies cho mọi thao tác ghi, tài khoản bị khóa bị đăng xuất ngay |
| FR-02 Đợt nấu + workflow trạng thái | Đạt | `CookingBatchTest` (18 test): Nháp → Kế hoạch → Đang nấu → Hoàn tất/Hủy, mở lại bắt buộc ghi lý do, chỉ xóa được đợt nháp trống |
| FR-03 Nguyên liệu | Đạt | `IngredientTest`; đơn vị cố định + "khác" có kiểm soát, không tự quy đổi |
| FR-04 Công việc, phân công, báo vướng/thiếu | Đạt | `TaskTest`; ghi người + thời điểm hoàn thành; báo vướng bắt buộc lý do |
| FR-05 Số suất + chênh lệch | Đạt | `ResultsAndExpensesTest`; số nguyên không âm, "chưa nhập" khác 0, cảnh báo chênh lệch + ghi chú, phát hiện sửa đồng thời |
| FR-06 Chi phí | Đạt | `ResultsAndExpensesTest`, `RequestNormalizationTest`; VND số nguyên, chấp nhận "1.500.000", tổng dự kiến/thực tế tách biệt |
| FR-07 Báo cáo, lọc, CSV | Đạt (PDF hoãn) | `ReportTest`; CSV UTF-8 BOM, chống formula injection, có thời gian tạo + phạm vi, cảnh báo "số liệu do người dùng ghi nhận". PDF: dùng "In → Lưu PDF" của trình duyệt (OQ-05, BM-044) |
| FR-08 Tổng quan | Đạt | `DashboardTest`, `ChartAndAssetTest`; mục "Cần xử lý", biểu đồ chỉ từ dữ liệu thật, trạng thái rỗng |
| FR-09 Checklist in A4 | Đạt | `ReportTest` (print view); không menu, ô đánh dấu, người phụ trách. Trên điện thoại bảng cuộn ngang trong khung (sửa trong phiên này) |
| Bổ sung: bài viết công khai, ảnh, SEO, chống spam | Đạt | `PostTest`, `PostMediaTest`, `PublicPostCacheTest`, `RateLimitTest`; trang công khai chỉ có số liệu tổng hợp, không có thông tin người nhận |

## 2. Kiểm thử đã chạy (14/10/2026)

| Loại | Kết quả |
|---|---|
| `php artisan test` — SQLite in-memory | **147 passed (1047 assertions)** |
| `php artisan test` — MySQL 8.4.3 (`gopgio_test`) | **147 passed (1047 assertions)** |
| `vendor/bin/pint --test` | Sạch |
| Chrome headless (bản cách ly: SQLite tạm + dữ liệu demo, cổng 8001) — 3 cỡ màn hình 360 / 768 / 1280 px × 26 trang và trạng thái (khách, quản trị, tình nguyện viên, modal, trang lỗi) = 78 lượt | Không tràn ngang; không lỗi JS; không vi phạm CSP. Phát hiện và sửa: bảng checklist in tràn 65px ở 360px; thanh công cụ trình soạn thảo bị cắt ở 360px; thẻ (#tag) cao 23px → 28px |
| E2E luồng ảnh dưới CSP (390px): đăng nhập điều phối viên → viết bài có ảnh bìa 3000×2000 + 2 ảnh album → trang công khai → PhotoSwipe | Xem trước `blob:` hiển thị, đăng bài thành công, `srcset` chọn bản 480px, PhotoSwipe mở "1 / 2", không lỗi. Ảnh thử (18 file) đã xóa sau khi kiểm tra |
| `php artisan bmb:check-production` trên máy dev | Báo đúng 3 LỖI của môi trường dev (APP_ENV, APP_DEBUG, 5 tài khoản demo) |

Danh sách test theo file: `AjaxFlowTest` 14, `AuthTest` 9, `ChartAndAssetTest` 4, `CookingBatchTest` 18, `DashboardTest` 3, `IngredientTest` 9, `PostMediaTest` 12, `PostTest` 10, `ProductionHardeningTest` 8, `PublicPostCacheTest` 4, `RateLimitTest` 4, `ReportTest` 7, `RequestNormalizationTest` 3, `ResultsAndExpensesTest` 10, `TaskTest` 10, `UiLayoutTest` 7, `UserManagementTest` 8, `Unit/ChartsTest` 3, `Unit/SupportTest` 4.

## 3. Bảo mật và quyền riêng tư (tóm tắt)
- Mọi trang nội bộ bắt buộc đăng nhập; authorization phía server bằng Policy/Gate trên từng đối tượng; có test từ chối theo vai trò cho mọi nhóm thao tác ghi (đợt, nguyên liệu, công việc, số suất/chi phí, báo cáo, tài khoản) và test IDOR đổi ID khi cập nhật công việc của người khác (T04).
- Không thu thập thông tin người nhận suất ăn; activity log không chứa ghi chú tự do, email, số điện thoại.
- Header bảo mật (CSP không `unsafe-inline` cho script, chống nhúng iframe, nosniff, HSTS khi HTTPS); cookie `HttpOnly`/`SameSite=Lax`/`Secure` khi HTTPS; CSRF; giới hạn tần suất theo tài khoản và IP.
- Ảnh tải lên: kiểm tra theo nội dung, mã hóa lại WebP (bỏ EXIF/GPS), thư mục ảnh không chạy được mã.
- Không tuyên bố chứng nhận hay bảo đảm an toàn thực phẩm.

## 4. Hạn chế và việc còn lại
| # | Nội dung | Ảnh hưởng | Đề xuất |
|---|---|---|---|
| 1 | **Chưa triển khai lên máy chủ thật** (OQ-12). `docs/DEPLOYMENT.md` có hướng dẫn cho cPanel và VPS nhưng chưa chạy thử trên hosting cụ thể | Chưa xác nhận `.htaccess`, cache tĩnh, giới hạn tải ảnh của hosting | Chốt hosting → làm theo mục 9 "Kiểm tra sau khi triển khai" của `DEPLOYMENT.md` |
| 2 | **Chưa thử với tình nguyện viên thật trên điện thoại thật**; kiểm tra bố cục bằng Chrome mô phỏng 360/768/1280 | Có thể còn vấn đề về cảm giác chạm, bàn phím ảo, mạng chậm | Buổi thử 30 phút với 2–3 tình nguyện viên: tạo đợt, nhận việc, báo vướng, nhập số suất |
| 3 | PDF (BM-044) hoãn theo OQ-05 | Người dùng tự "In → Lưu PDF" | Chỉ làm nếu bếp cần gửi file PDF định dạng cố định |
| 4 | Các câu hỏi OQ-01…OQ-15 đang áp dụng phương án mặc định | Có thể phải đổi quy tắc sau khi thử | Xác nhận với người vận hành trong buổi thử |
| 5 | Chưa có thông báo (email/Zalo), sao chép đợt cũ | Ngoài phạm vi MVP | Xem "Ý tưởng sau MVP" trong backlog |
| 6 | CSP cho phép `style-src 'unsafe-inline'` | Rủi ro thấp (không chạy được mã), cần cho biểu đồ | Có thể chuyển sang biến CSS + class nếu muốn siết thêm |

## 5. Sự cố môi trường trong phiên nghiệm thu
- Ổ C: từng đầy (còn 0,17 GB), khiến `vendor/` bị cài dở, `node_modules/` biến mất và `.env` bị tạo lại không có `APP_KEY`. Đã chạy lại `composer install`, `npm ci` và `php artisan key:generate` (phiên đăng nhập cũ hết hiệu lực; mật khẩu không bị ảnh hưởng). Thư mục `public/uploads/` của máy dev hiện chỉ còn `.htaccess` — nếu trước đó có ảnh bài viết thật thì cần khôi phục từ bản sao lưu.
- Test `ChartAndAssetTest` từng lỗi khi thiếu `node_modules` (đáng lẽ phải bỏ qua) → đã sửa.
