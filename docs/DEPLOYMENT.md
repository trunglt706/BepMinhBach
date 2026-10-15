# Triển khai Góp Gió

> Máy chủ **không bắt buộc có Node.js**: CSS/JS/font đã nằm sẵn trong `public/vendor` (có Node thì `deploy.sh` tự dựng lại).

## 0. Cập nhật phiên bản mới: `deploy/deploy.sh`

`deploy/deploy.sh` chỉ **cập nhật** trên máy chủ **đã cấu hình sẵn** (mục 0.1). Script không cài gói, không sửa Nginx,
CSDL, HTTPS, tường lửa, `.env`, dịch vụ systemd hay cron.

```bash
cd /var/www/gopgio && sudo bash deploy/deploy.sh
```

| Bước | Nội dung |
|---|---|
| 1 | Kiểm tra: chạy bằng root, có mã nguồn, có `.env`, có Composer; khóa để không chạy chồng 2 lần |
| 2 | Bật chế độ bảo trì (`artisan down --retry=30`) |
| 3 | `git fetch` + `git pull --ff-only` nhánh `BRANCH` (dừng nếu máy chủ có sửa đổi chưa commit) |
| 4 | `composer install --no-dev --optimize-autoloader` |
| 5 | `npm ci` + `npm run assets` (bỏ qua nếu `SKIP_NPM=1` hoặc không có Node) |
| 6 | Tạo thư mục `storage/…`; mã nguồn root sở hữu, chỉ đọc với web server; chỉ `storage/`, `bootstrap/cache/`, `public/uploads/` ghi được; `.env` quyền 640 |
| 7 | `migrate --force` |
| 8 | Làm mới cache: `optimize:clear`, `config:cache`, `route:cache`, `view:cache`, `event:cache` |
| 9 | Tắt chế độ bảo trì |
| 10 | `systemctl reload phpX.Y-fpm`; `queue:restart` + `systemctl restart` dịch vụ worker (`gopgio-queue` hoặc `bepminhbach-queue`) |
| 11 | `bmb:check-production` |

Lỗi ở bất kỳ bước nào: script tự tắt chế độ bảo trì để bản đang chạy vẫn phục vụ. **Luôn sao lưu CSDL trước khi cập nhật.**

**Biến tùy chọn** (môi trường hoặc file `deploy/deploy.env`): `APP_DIR` (mặc định thư mục cha của `deploy/`), `BRANCH` (main),
`APP_USER` (www-data), `PHP_FPM` (tự dò), `QUEUE_SERVICE` (tự dò), `SKIP_NPM=1`, `SKIP_PULL=1` (code đã đồng bộ bằng cách khác, ví dụ `rsync`).

### 0.1. Cấu hình máy chủ (làm một lần)
Làm theo mục 1–5 (yêu cầu, mã nguồn, `.env`, khởi tạo dữ liệu, Nginx/HTTPS), cộng thêm:

**`.env` production** — ngoài mục 3:
```dotenv
APP_NAME="Góp Gió"
QUEUE_CONNECTION=database
LOG_STACK=daily
LOG_DAILY_DAYS=14
MAIL_MAILER=smtp            # + MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS (tên miền thật)
```

**PHP-FPM** — `/etc/php/8.3/fpm/conf.d/99-gopgio.ini`:
```ini
upload_max_filesize = 12M
post_max_size = 64M
max_file_uploads = 20
memory_limit = 256M
max_execution_time = 60
expose_php = Off
opcache.enable = 1
```

**Worker queue** (thông báo Slack/Telegram/Chatwork, email quên mật khẩu) — `/etc/systemd/system/gopgio-queue.service`:
```ini
[Unit]
Description=Gop Gio queue worker
After=network.target mariadb.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/gopgio
ExecStart=/usr/bin/php /var/www/gopgio/artisan queue:work database --sleep=3 --tries=3 --timeout=60 --max-time=3600
Restart=always
RestartSec=5
NoNewPrivileges=true

[Install]
WantedBy=multi-user.target
```
```bash
sudo systemctl daemon-reload && sudo systemctl enable --now gopgio-queue
```

**Cron** — `/etc/cron.d/gopgio` (lịch dọn dẹp của Laravel mỗi phút + sao lưu 02:30, xem mục 7):
```cron
* * * * * www-data /usr/bin/php /var/www/gopgio/artisan schedule:run >/dev/null 2>&1
30 2 * * * root /usr/local/bin/gopgio-backup >/dev/null 2>&1
```

**Kênh thông báo:** máy chủ cần ra Internet qua HTTPS tới `hooks.slack.com`, `api.telegram.org`, `api.chatwork.com`. Token được mã hóa
bằng `APP_KEY` — **không đổi APP_KEY** sau khi đã cấu hình kênh (nếu đổi, nhập lại token ở từng kênh).

**Máy chủ cài trước 15/10/2026** (bản `deploy.sh` cũ cài toàn bộ hệ thống): thư mục, dịch vụ và cron dùng tên `bepminhbach`
(`/var/www/bepminhbach`, `bepminhbach-queue`, `/etc/cron.d/bepminhbach`…) — vẫn chạy bình thường, `deploy.sh` tự dò tên dịch vụ worker.
Cần tự thêm `APP_NAME="Góp Gió"` vào `.env` (đổi tên cookie phiên → mọi người bị đăng xuất một lần; ai bật "Ghi nhớ đăng nhập" tự đăng nhập lại).

Các mục dưới đây mô tả chi tiết từng phần cấu hình **thủ công** (VPS hoặc hosting cPanel).

## 1. Yêu cầu máy chủ
- PHP **8.2+** (đã kiểm thử 8.2 và 8.3), các extension: `pdo_mysql`, `mbstring`, `intl`, `fileinfo`, `gd` **có hỗ trợ WebP** (`php -r "var_dump(gd_info()['WebP Support']);"`), `exif` (nên có, để xoay ảnh chụp điện thoại đúng chiều), `openssl`, `tokenizer`, `xml`, `ctype`.
- MySQL 8 hoặc MariaDB 10.6+, bảng mã `utf8mb4`.
- HTTPS (Let's Encrypt hoặc chứng chỉ của hosting). Không đưa lên Internet khi chưa có HTTPS vì mật khẩu đi qua mạng.
- `upload_max_filesize` ≥ 10M, `post_max_size` ≥ 64M (bài viết có nhiều ảnh; trình duyệt đã thu nhỏ ảnh trước khi gửi nên thực tế nhẹ hơn nhiều), `memory_limit` ≥ 256M (giải mã ảnh lớn).
- **Bắt buộc** có worker queue (thông báo kênh, email quên mật khẩu) và cron `schedule:run` mỗi phút — xem mục 0.1.

## 2. Chuẩn bị mã nguồn
Trên máy có Composer (máy cá nhân hoặc máy chủ):
```bash
git clone <repo> gopgio && cd gopgio
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```
Nếu hosting không có SSH/Composer: chạy các lệnh trên ở máy cá nhân rồi tải cả thư mục (gồm `vendor/`) lên. **Không** tải `node_modules/`, `.git/`, `tests/`, `storage/logs/*.log`.

## 3. Cấu hình `.env` cho môi trường thật
```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ten-mien-cua-ban
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=gopgio
DB_USERNAME=gopgio      # tài khoản riêng, chỉ có quyền trên DB này — không dùng root
DB_PASSWORD=...

SESSION_SECURE_COOKIE=true   # mặc định đã bật khi APP_URL là https://
# Chỉ khi chạy sau proxy/CDN (Cloudflare, Nginx của hosting): IP/dải IP của proxy, cách nhau dấu phẩy
# TRUSTED_PROXIES=
```
- `DEMO_PASSWORD` để trống; **không** chạy `db:seed` trên môi trường thật (seeder cũng tự từ chối khi không phải local/testing).
- `.env` không được truy cập qua web: chỉ trỏ web root vào `public/` (xem bên dưới).

## 4. Khởi tạo dữ liệu
```bash
php artisan migrate --force
php artisan bmb:create-admin          # tạo quản trị viên đầu tiên (mật khẩu nhập ẩn)
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan bmb:check-production      # phải không còn dòng "LỖI"
```
Sau mỗi lần sửa `.env`: chạy lại `php artisan config:cache`.

## 5a. Hosting chia sẻ (cPanel, Apache)
- Đặt mã nguồn **ngoài** `public_html` (ví dụ `~/gopgio`), rồi trỏ tên miền/subdomain về `~/gopgio/public` (mục *Domains → Document Root*).
- Nếu hosting bắt buộc dùng `public_html`: chép nội dung `public/` vào `public_html`, sửa 2 đường dẫn trong `public_html/index.php` thành `__DIR__.'/../gopgio/vendor/autoload.php'` và `__DIR__.'/../gopgio/bootstrap/app.php'`. Khi đó ảnh tải lên nằm ở `public_html/uploads` — sao lưu thư mục này.
- `public/.htaccess` đã có rewrite + cache tĩnh; `public/uploads/.htaccess` chặn chạy mã trong thư mục ảnh. Kiểm tra hosting cho phép `.htaccess` (AllowOverride).
- Chọn PHP 8.2+ trong *MultiPHP Manager*, bật các extension ở mục 1.

## 5b. VPS (Ubuntu, Nginx + PHP-FPM)
```nginx
server {
    listen 443 ssl http2;
    server_name ten-mien-cua-ban;
    root /var/www/gopgio/public;
    index index.php;
    client_max_body_size 64m;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    # Thư mục ảnh tải lên: không bao giờ chạy mã
    location ^~ /uploads/ { location ~ \.(php|phtml|phar|html?|svg|js)$ { deny all; } add_header X-Content-Type-Options nosniff; }
    location ~* \.(woff2?|ttf)$ { expires 1y; add_header Cache-Control "public, immutable"; }
    location ~* \.(css|js|svg|ico)$ { expires 7d; }
    location ~ /\.(?!well-known) { deny all; }
}
server { listen 80; server_name ten-mien-cua-ban; return 301 https://$host$request_uri; }
```
Quyền thư mục: chủ sở hữu là user deploy, nhóm `www-data`; `storage/`, `bootstrap/cache/`, `public/uploads/` cho nhóm ghi (`chmod -R g+w`). Các file khác chỉ đọc với `www-data`.

## 6. Bảo mật đã có sẵn trong ứng dụng
- Header: `Content-Security-Policy` (chỉ chạy JS của chính site), `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`; `Strict-Transport-Security` tự gửi khi truy cập qua HTTPS (middleware `SecurityHeaders`).
- Cookie phiên `HttpOnly`, `SameSite=Lax`, `Secure` khi dùng HTTPS; CSRF; giới hạn tần suất (`BMB_RATE_*`).
- `php artisan bmb:check-production` kiểm tra: APP_ENV/APP_DEBUG/APP_KEY, HTTPS, cookie, LOG_LEVEL, quyền ghi thư mục, `.htaccess` của thư mục ảnh, cache cấu hình, kết nối DB, tài khoản demo còn hoạt động, có quản trị viên. Trả mã lỗi 1 nếu có mục LỖI (dùng được trong script deploy).

## 7. Sao lưu và khôi phục
Cần sao lưu **2 thứ**: cơ sở dữ liệu và thư mục ảnh `public/uploads/` (cùng file `.env`, lưu riêng ở nơi an toàn).

Script sao lưu hằng ngày `/usr/local/bin/gopgio-backup` (quyền 700, chạy bằng cron ở mục 0.1), giữ 14 bản gần nhất.
Thông tin đăng nhập CSDL để trong `/root/.gopgio/my.cnf` (quyền 600: `[client]`, `user=…`, `password=…`), không gõ trên dòng lệnh:
```bash
#!/usr/bin/env bash
set -euo pipefail
umask 077
STAMP=$(date +%F-%H%M)
DIR=/var/backups/gopgio
mkdir -p "$DIR"
mysqldump --defaults-extra-file=/root/.gopgio/my.cnf --single-transaction --quick --default-character-set=utf8mb4 gopgio | gzip > "$DIR/db-$STAMP.sql.gz"
tar -czf "$DIR/uploads-$STAMP.tar.gz" -C /var/www/gopgio/public uploads
find "$DIR" -name '*.gz' -mtime +14 -delete
```
Khôi phục:
```bash
php artisan down
gunzip < backup/db-YYYY-MM-DD.sql.gz | mysql -u gopgio -p gopgio
tar -xzf backup/uploads-YYYY-MM-DD.tar.gz -C public
php artisan cache:clear && php artisan up
```
Nên chép bản sao lưu ra ngoài máy chủ (Google Drive/máy khác) và **thử khôi phục** vào một DB thử ít nhất mỗi quý. Mật khẩu DB nên đặt trong `~/.my.cnf` (quyền 600) thay vì gõ trên dòng lệnh.

## 8. Cập nhật phiên bản mới
VPS: dùng `sudo bash deploy/deploy.sh` (mục 0). Hosting không chạy được script (cPanel…) thì làm tay:
```bash
php artisan down
git pull                     # hoặc tải mã mới lên
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan up
php artisan queue:restart     # worker gửi thông báo nạp mã mới
php artisan bmb:check-production
```
Luôn sao lưu DB trước khi chạy migration.

## 9. Kiểm tra sau khi triển khai
1. Mở `https://ten-mien-cua-ban/up` → trang "Application up".
2. Đăng nhập bằng tài khoản quản trị, tạo tài khoản cho điều phối viên.
3. Tạo một chương trình nháp, thêm 1 nguyên liệu, 1 công việc; xóa chương trình nháp đó.
4. Viết bài thử có ảnh (quản trị viên đăng ngay), xem ở trang chủ trên điện thoại, rồi xóa bài.
5. Trình duyệt → DevTools → Network: trang có `Content-Security-Policy`, `Strict-Transport-Security`; Console không có lỗi CSP.
6. Thử truy cập `https://ten-mien-cua-ban/.env` và `https://ten-mien-cua-ban/uploads/x.php` → phải là 403/404.
7. Kiểm tra worker: `systemctl status gopgio-queue`; gửi thử ở một kênh thông báo của chương trình.
