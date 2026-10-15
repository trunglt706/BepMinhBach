# Triển khai Góp Gió

> Máy chủ **không cần Node.js**: CSS/JS/font đã nằm sẵn trong `public/`.

## 0. Cách nhanh nhất: VPS + một lệnh (`deploy/deploy.sh`)

Dành cho VPS **Ubuntu 22.04 / 24.04** hoặc **Debian 12** mới cài (≥ 1 GB RAM, 10 GB ổ đĩa).

**Chuẩn bị (1 lần):**
1. Trỏ tên miền (bản ghi `A`) về IP của VPS, ví dụ `bep.example.org → 203.0.113.10`. Đợi vài phút cho DNS cập nhật.
2. SSH vào VPS bằng tài khoản có quyền `sudo`.

**Chạy:**
```bash
curl -fsSL https://raw.githubusercontent.com/trunglt706/BepMinhBach/main/deploy/deploy.sh -o deploy.sh
sudo DOMAIN=bep.example.org LE_EMAIL=ban@example.org bash deploy.sh
```
Script hỏi họ tên, email, mật khẩu cho **quản trị viên đầu tiên** (mật khẩu nhập ẩn). Muốn chạy hoàn toàn tự động:
```bash
sudo DOMAIN=bep.example.org LE_EMAIL=ban@example.org \
     ADMIN_NAME="Nguyễn Văn A" ADMIN_EMAIL=admin@example.org ADMIN_PASSWORD='mat-khau-dai-it-nhat-10-ky-tu' \
     bash deploy.sh
```

**Script làm gì** (chạy lại an toàn — lần sau chính là cập nhật):

| Bước | Nội dung |
|---|---|
| 1 | Cài Nginx, PHP 8.3-FPM (+ intl, gd/WebP, mbstring, mysql, zip, opcache…), MariaDB, Composer (kiểm chữ ký), Certbot, ufw. Thiếu PHP 8.3 trong kho mặc định thì tự thêm kho Ondřej Surý |
| 2 | Tạo CSDL `gopgio` (utf8mb4) + tài khoản CSDL riêng, mật khẩu ngẫu nhiên lưu ở `/root/.gopgio/db.env` (chỉ root đọc) |
| 3 | Lấy mã từ GitHub (`REPO_URL`, `BRANCH`) vào `/var/www/gopgio`; lần cập nhật bật chế độ bảo trì trước khi kéo mã |
| 4 | Tạo `.env` production: `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`, cookie Secure khi HTTPS, `APP_KEY` mới. **Không** chạy seeder demo |
| 5 | `composer install --no-dev`; mã nguồn chỉ đọc với web server, chỉ `storage/`, `bootstrap/cache/`, `public/uploads/` ghi được; `.env` quyền 640 |
| 6 | PHP-FPM: upload 12 MB/ảnh, post 64 MB, memory 256 MB, tắt `expose_php`, bật opcache |
| 7 | `migrate --force`, cache config/route/view |
| 8 | Nginx: web root `public/`, chặn file ẩn (`.env`, `.git`), thư mục ảnh không chạy mã, cache file tĩnh |
| 9 | HTTPS Let's Encrypt + chuyển hướng http→https (tự gia hạn). Cảnh báo nếu DNS chưa trỏ đúng; lỗi thì tạm chạy HTTP |
| 10 | ufw: chỉ mở cổng SSH **đang dùng thật** (dò từ sshd, tránh tự khóa mình), 80, 443 |
| 11 | Sao lưu 02:30 hằng ngày: CSDL (`mysqldump`) + ảnh → `/var/backups/gopgio`, giữ 14 ngày; chạy thử 1 bản ngay |
| 12 | Tạo quản trị viên đầu tiên nếu chưa có |
| 13 | Tắt bảo trì, chạy `bmb:check-production`, in tóm tắt; lưu cấu hình vào `/root/.gopgio/deploy.conf` |

**Kênh thông báo (Slack/Telegram/Chatwork):** máy chủ cần ra Internet qua HTTPS tới `hooks.slack.com`, `api.telegram.org`, `api.chatwork.com` (ufw mặc định không chặn chiều ra). Token được mã hóa bằng `APP_KEY` — **không đổi APP_KEY** sau khi đã cấu hình kênh (nếu đổi, nhập lại token ở từng kênh). Không cần queue worker.

**Cập nhật phiên bản mới** (sau khi đẩy mã lên nhánh `main`):
```bash
sudo bash /var/www/gopgio/deploy/deploy.sh
```
(không cần nhắc lại DOMAIN/LE_EMAIL — đã lưu ở lần đầu.)

**Biến tùy chọn:** `REPO_URL`, `BRANCH`, `APP_DIR`, `PHP_VERSION`, `SOURCE_DIR` (dùng thư mục mã đã tải lên thay vì git — ví dụ repo riêng tư), `SKIP_FIREWALL=1`, `SKIP_CERTBOT=1` (HTTPS do Cloudflare/proxy lo), `TRUSTED_PROXIES`. Có thể ghi các biến vào file `deploy.env` đặt cạnh script.

**Repo riêng tư / không dùng GitHub:** từ máy mình chạy `git archive --format=tar.gz -o bmb.tar.gz HEAD`, chép lên VPS (`scp`), giải nén vào `/root/bmb-src`, rồi `sudo SOURCE_DIR=/root/bmb-src DOMAIN=… LE_EMAIL=… bash /root/bmb-src/deploy/deploy.sh`.

**Nếu script dừng giữa chừng:** đọc dòng lỗi cuối, sửa nguyên nhân (thường là DNS chưa trỏ đúng hoặc VPS hết RAM khi cài gói) rồi chạy lại — các bước đã xong sẽ được bỏ qua/ghi đè an toàn; nếu đang cập nhật, trang sẽ được tắt chế độ bảo trì.

Các mục dưới đây mô tả cách làm **thủ công** (hosting cPanel hoặc muốn tự cấu hình) và giải thích chi tiết từng phần mà script đã tự làm.

## 1. Yêu cầu máy chủ
- PHP **8.2+** (đã kiểm thử 8.2 và 8.3), các extension: `pdo_mysql`, `mbstring`, `intl`, `fileinfo`, `gd` **có hỗ trợ WebP** (`php -r "var_dump(gd_info()['WebP Support']);"`), `exif` (nên có, để xoay ảnh chụp điện thoại đúng chiều), `openssl`, `tokenizer`, `xml`, `ctype`.
- MySQL 8 hoặc MariaDB 10.6+, bảng mã `utf8mb4`.
- HTTPS (Let's Encrypt hoặc chứng chỉ của hosting). Không đưa lên Internet khi chưa có HTTPS vì mật khẩu đi qua mạng.
- `upload_max_filesize` ≥ 10M, `post_max_size` ≥ 64M (bài viết có nhiều ảnh; trình duyệt đã thu nhỏ ảnh trước khi gửi nên thực tế nhẹ hơn nhiều), `memory_limit` ≥ 256M (giải mã ảnh lớn).
- Không cần queue worker hay cron: ứng dụng không chạy tác vụ nền.

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
```bash
# Hằng ngày (cron của hosting hoặc VPS), giữ 14 bản gần nhất
mysqldump --single-transaction --default-character-set=utf8mb4 -u gopgio -p gopgio | gzip > backup/db-$(date +%F).sql.gz
tar -czf backup/uploads-$(date +%F).tar.gz -C public uploads
find backup -name '*.gz' -mtime +14 -delete
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
```bash
php artisan down
git pull                     # hoặc tải mã mới lên
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
php artisan queue:restart     # worker gửi thông báo nạp mã mới
php artisan bmb:check-production
```
Luôn sao lưu DB trước khi chạy migration.

**Chạy nền (bắt buộc từ 15/10/2026)** — `deploy.sh` tự cấu hình; cài tay thì cần:
- Worker queue (thông báo Slack/Telegram/Chatwork, email quên mật khẩu): dịch vụ luôn chạy
  `php artisan queue:work database --tries=3 --timeout=60 --max-time=3600` (systemd/supervisor), `QUEUE_CONNECTION=database`.
- Cron mỗi phút: `* * * * * www-data php /đường-dẫn/artisan schedule:run` (dọn token đặt lại mật khẩu hết hạn, job lỗi cũ).
- Email thật: cấu hình `MAIL_*` (SMTP) và `MAIL_FROM_ADDRESS` thuộc tên miền của bạn — nếu để `MAIL_MAILER=log`, email "Quên mật khẩu" không tới người dùng.
- Log: `LOG_STACK=daily`, `LOG_DAILY_DAYS=14` để `storage/logs` không phình đầy ổ đĩa.

**Đổi tên thương hiệu Góp Gió (15/10/2026) trên máy đang chạy:** `deploy.sh` đặt `APP_NAME="Góp Gió"`. Tên cookie phiên sinh từ
`APP_NAME`, nên sau lần cập nhật này **mọi người bị đăng xuất một lần** (ai bật "Ghi nhớ đăng nhập" vẫn tự đăng nhập lại).
**Tên thư mục/dịch vụ:** tài liệu này dùng tên mới `gopgio` (`/var/www/gopgio`, `/root/.gopgio`, `/var/backups/gopgio`,
dịch vụ `gopgio-queue`, cron `/etc/cron.d/gopgio`, CSDL `gopgio`). `deploy.sh` tự nhận diện: máy **đã cài trước 15/10/2026**
(có `/root/.bepminhbach`) giữ nguyên mọi tên cũ `bepminhbach…` để cập nhật không làm hỏng máy đang chạy — khi đọc tài liệu
trên máy đó, thay `gopgio` bằng `bepminhbach`.

## 9. Kiểm tra sau khi triển khai
1. Mở `https://ten-mien-cua-ban/up` → trang "Application up".
2. Đăng nhập bằng tài khoản quản trị, tạo tài khoản cho điều phối viên.
3. Tạo một đợt nháp, thêm 1 nguyên liệu, 1 công việc; xóa đợt nháp đó.
4. Viết bài thử có ảnh, xem ở `/bai-viet` trên điện thoại, rồi xóa bài.
5. Trình duyệt → DevTools → Network: trang có `Content-Security-Policy`, `Strict-Transport-Security`; Console không có lỗi CSP.
6. Thử truy cập `https://ten-mien-cua-ban/.env` và `https://ten-mien-cua-ban/uploads/x.php` → phải là 403/404.
