# BếpMinhBạch

Hệ thống web quản lý các đợt nấu ăn thiện nguyện và suất ăn cộng đồng. Mục tiêu là thay sổ tay/tin nhắn rời rạc bằng kế hoạch, checklist, phân công, số liệu thực tế và báo cáo dễ kiểm tra.

## Phạm vi MVP
- Đăng nhập và phân quyền theo vai trò.
- Quản lý đợt nấu ăn.
- Ước tính số suất.
- Danh sách nguyên liệu theo từng đợt.
- Checklist công việc, người phụ trách và hạn hoàn thành.
- Cập nhật tiến độ, báo thiếu nguyên liệu.
- Ghi nhận số suất hoàn thành và phân phát.
- Tổng hợp chi phí nguyên liệu.
- Báo cáo theo đợt; xuất CSV và PDF nếu môi trường hỗ trợ ổn định.
- In checklist để dùng khi mất mạng hoặc trong khu vực bếp.

## Không thuộc MVP
- Không phải nền tảng giao đồ ăn.
- Không quản lý kho nhiều địa điểm/lô/hạn sử dụng theo chuẩn ERP.
- Không xử lý quyên góp, thanh toán hoặc kế toán.
- Không cần ứng dụng iOS/Android riêng.
- Không quản lý hồ sơ sức khỏe hoặc danh tính người nhận suất ăn.

## Công nghệ mục tiêu
- Laravel + Blade
- Bootstrap
- MariaDB/MySQL
- PHPUnit hoặc Pest tùy cấu hình repository
- CSV export; PDF qua thư viện tương thích nếu cần

Claude Code phải đọc `CLAUDE.md` trước khi thực hiện công việc. Bộ tài liệu trong `docs/` mô tả yêu cầu sản phẩm và tiêu chí hoàn thành; nếu repository đã tồn tại, hãy kiểm tra thực tế trước khi áp dụng.

## Cài đặt và chạy (local)
Yêu cầu: PHP ≥ 8.2 (khuyến nghị 8.3 của Laragon) với `pdo_mysql`/`pdo_sqlite`, `mbstring`, `intl`; Composer; MySQL 8 hoặc MariaDB ≥ 10.6. **Không cần Node** để chạy — Bootstrap đã nằm sẵn trong `public/vendor/`.

```bash
composer install
cp .env.example .env
php artisan key:generate
# Sửa DB_DATABASE / DB_USERNAME / DB_PASSWORD trong .env (DB tạo với utf8mb4).
# Thử nhanh không cần MySQL: đặt DB_CONNECTION=sqlite và xoá các dòng DB_* khác.
php artisan migrate
php artisan db:seed          # chỉ local/testing: tạo tài khoản + dữ liệu demo giả
php artisan serve            # mở http://127.0.0.1:8000
```

Tài khoản demo (mật khẩu = `DEMO_PASSWORD` trong `.env`, mặc định `demo12345` chỉ dùng ở local):
`admin@bepminhbach.test`, `dieuphoi@bepminhbach.test`, `ghinhan@bepminhbach.test`, `tnv1@bepminhbach.test`, `tnv2@bepminhbach.test`.

Trên môi trường thật **không chạy seed demo**; tạo quản trị viên đầu tiên bằng `php artisan bmb:create-admin` (mật khẩu nhập ẩn).

**Ảnh bài viết:** cần extension PHP `gd` có hỗ trợ WebP (và `exif` để xoay đúng ảnh chụp điện thoại). Ảnh lưu ở `public/uploads/` (không cần `storage:link`, đã có `.htaccess` chặn chạy mã); thư mục này không vào git — **cần sao lưu cùng database**. Nên đặt `upload_max_filesize`/`post_max_size` ≥ 12M trong php.ini.

**Tài nguyên giao diện** (chỉ cần khi nâng cấp thư viện hoặc thêm icon mới):
```bash
npm install
npm run assets   # chép Bootstrap, Bootstrap Icons, Trix, PhotoSwipe, font vào public/vendor + tạo bộ icon rút gọn
npm run icons    # chỉ tạo lại bộ icon rút gọn (chạy khi dùng icon bi-* mới; test sẽ báo nếu quên)
```

Cấu hình thêm trong `.env` (xem `config/bmb.php`): `BMB_RATE_WRITES`, `BMB_RATE_READS`, `BMB_RATE_PUBLIC` (chống spam), `BMB_POST_MAX_IMAGES`, `BMB_POST_MAX_UPLOAD_KB`, `BMB_POST_VIEW_WINDOW`.

### Kiểm thử
```bash
php artisan test        # PHPUnit, SQLite in-memory
vendor/bin/pint --test  # kiểm tra định dạng code
# Chạy trên MySQL (dùng DB riêng, test sẽ XÓA dữ liệu của DB này):
DB_CONNECTION=mysql DB_DATABASE=bepminhbach_test php artisan test
```

### Vai trò
| Vai trò | Quyền chính |
|---|---|
| Tình nguyện viên | Xem đợt nấu, cập nhật việc được giao, báo vướng, báo thiếu nguyên liệu, in checklist |
| Người ghi nhận | + nhập số suất, chi phí, xem báo cáo/CSV |
| Điều phối viên | + tạo/sửa đợt, nguyên liệu, công việc, phân công, hoàn tất/hủy/mở lại đợt |
| Quản trị | + quản lý tài khoản (tạo, sửa vai trò, khóa/mở khóa) |

### Vận hành cơ bản
- Production: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `SESSION_SECURE_COOKIE=true`; chạy `php artisan config:cache route:cache view:cache`.
- Backup MySQL: `mysqldump --single-transaction -u USER -p bepminhbach > backup-$(date +%F).sql`. Khôi phục: `mysql -u USER -p bepminhbach < backup-YYYY-MM-DD.sql`. Nên backup tự động hằng ngày và thử khôi phục định kỳ.
- Cập nhật Bootstrap: sửa phiên bản trong `package.json`, `npm install && npm run assets`.

Nhật ký triển khai: `docs/IMPLEMENTATION_LOG.md`. Câu hỏi chờ xác nhận: `docs/OPEN_QUESTIONS.md`.

## Bắt đầu với Claude Code
1. Mở repository trong Claude Code.
2. Yêu cầu: “Đọc CLAUDE.md và toàn bộ tài liệu docs. Khảo sát repository hiện tại, đối chiếu với yêu cầu, lập kế hoạch MVP theo từng task và bắt đầu task ưu tiên cao nhất. Không phá hủy thay đổi hiện có.”
3. Chạy test theo hướng dẫn trong repository.
4. Cập nhật `tasks/BACKLOG.md` và `docs/IMPLEMENTATION_LOG.md` sau từng lát cắt hoàn chỉnh.
