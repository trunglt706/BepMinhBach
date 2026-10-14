#!/usr/bin/env bash
# =============================================================================
#  BếpMinhBạch — triển khai MỘT LỆNH lên VPS Ubuntu (22.04 / 24.04) hoặc Debian 12.
#
#  Lần đầu: cài Nginx, PHP-FPM, MariaDB, Composer, Certbot; tạo CSDL + tài khoản CSDL riêng;
#  lấy mã nguồn; tạo .env production; migrate; cấu hình Nginx + HTTPS; tường lửa; sao lưu hằng ngày;
#  tạo tài khoản quản trị đầu tiên.
#  Lần sau: chạy lại đúng lệnh này = cập nhật phiên bản mới (bật chế độ bảo trì, kéo mã, migrate, tắt bảo trì).
#
#  Cách dùng (trên VPS, quyền root):
#    curl -fsSL https://raw.githubusercontent.com/trunglt706/BepMinhBach/main/deploy/deploy.sh -o deploy.sh
#    sudo DOMAIN=bep.example.org LE_EMAIL=ban@example.org bash deploy.sh
#
#  Biến cấu hình (truyền qua môi trường hoặc file deploy.env cạnh script):
#    DOMAIN          tên miền đã trỏ về IP VPS (bỏ trống: chạy bằng IP, chỉ HTTP — chỉ nên dùng để thử)
#    LE_EMAIL        email nhận thông báo Let's Encrypt (cần có để bật HTTPS tự động)
#    REPO_URL        mặc định https://github.com/trunglt706/BepMinhBach.git
#    BRANCH          mặc định main
#    SOURCE_DIR      dùng mã nguồn có sẵn trên máy (thư mục đã tải lên) thay cho git
#    APP_DIR         mặc định /var/www/bepminhbach
#    DB_NAME/DB_USER mặc định bepminhbach (mật khẩu CSDL tự sinh, lưu ở /root/.bepminhbach/db.env)
#    PHP_VERSION     mặc định 8.3
#    ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD   tạo quản trị viên không cần hỏi (bỏ trống: hỏi khi chạy)
#    SKIP_FIREWALL=1 không cấu hình ufw (ví dụ VPS đã có tường lửa của nhà cung cấp)
#    SKIP_CERTBOT=1  không xin chứng chỉ (ví dụ đã có HTTPS qua Cloudflare/proxy)
#    TRUSTED_PROXIES IP/dải IP proxy đứng trước (Cloudflare…), ghi vào .env
# =============================================================================
set -Eeuo pipefail
umask 027

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SECRETS_DIR=/root/.bepminhbach
SAVED_CONF="$SECRETS_DIR/deploy.conf"
# shellcheck disable=SC1091
[ -f "$SCRIPT_DIR/deploy.env" ] && . "$SCRIPT_DIR/deploy.env"
# Lần chạy trước đã lưu cấu hình (SAVED_*): chạy lại chỉ cần "sudo bash deploy.sh".
# shellcheck disable=SC1090
[ -f "$SAVED_CONF" ] && . "$SAVED_CONF"

DOMAIN="${DOMAIN:-${SAVED_DOMAIN:-}}"
LE_EMAIL="${LE_EMAIL:-${SAVED_LE_EMAIL:-}}"
REPO_URL="${REPO_URL:-${SAVED_REPO_URL:-https://github.com/trunglt706/BepMinhBach.git}}"
BRANCH="${BRANCH:-${SAVED_BRANCH:-main}}"
SOURCE_DIR="${SOURCE_DIR:-}"
APP_DIR="${APP_DIR:-${SAVED_APP_DIR:-/var/www/bepminhbach}}"
DB_NAME="${DB_NAME:-bepminhbach}"
DB_USER="${DB_USER:-bepminhbach}"
PHP_VERSION="${PHP_VERSION:-${SAVED_PHP_VERSION:-8.3}}"
ADMIN_NAME="${ADMIN_NAME:-}"
ADMIN_EMAIL="${ADMIN_EMAIL:-}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"
SKIP_FIREWALL="${SKIP_FIREWALL:-${SAVED_SKIP_FIREWALL:-0}}"
SKIP_CERTBOT="${SKIP_CERTBOT:-${SAVED_SKIP_CERTBOT:-0}}"
TRUSTED_PROXIES="${TRUSTED_PROXIES:-${SAVED_TRUSTED_PROXIES:-}}"

BACKUP_DIR=/var/backups/bepminhbach
APP_USER=www-data
export DEBIAN_FRONTEND=noninteractive COMPOSER_ALLOW_SUPERUSER=1

# ---------------------------------------------------------------------------- tiện ích
c_info=$'\033[1;34m'; c_ok=$'\033[1;32m'; c_warn=$'\033[1;33m'; c_err=$'\033[1;31m'; c_off=$'\033[0m'
step() { printf '\n%s==> %s%s\n' "$c_info" "$*" "$c_off"; }
ok()   { printf '%s    ✓ %s%s\n' "$c_ok" "$*" "$c_off"; }
warn() { printf '%s    ! %s%s\n' "$c_warn" "$*" "$c_off"; }
die()  { printf '%s✗ %s%s\n' "$c_err" "$*" "$c_off" >&2; exit 1; }

MAINTENANCE=0
on_error() {
    local line=$1
    printf '%s✗ Lỗi ở dòng %s. Xem thông báo phía trên.%s\n' "$c_err" "$line" "$c_off" >&2
    if [ "$MAINTENANCE" = 1 ] && [ -f "$APP_DIR/artisan" ]; then
        warn "Tắt chế độ bảo trì để trang cũ vẫn chạy."
        (cd "$APP_DIR" && php artisan up >/dev/null 2>&1) || true
    fi
}
trap 'on_error $LINENO' ERR

svc() { # svc <start|restart|reload|enable> <tên dịch vụ> — chạy được cả khi không có systemd (container)
    local action=$1 name=$2
    if pidof systemd >/dev/null 2>&1 && command -v systemctl >/dev/null; then
        if [ "$action" = enable ]; then systemctl enable --now "$name" >/dev/null; else systemctl "$action" "$name"; fi
    else
        [ "$action" = enable ] && action=start
        service "$name" "$action" >/dev/null 2>&1 || service "$name" restart
    fi
}

artisan() { (cd "$APP_DIR" && runuser -u "$APP_USER" -- php artisan "$@"); }

set_env() { # set_env KEY VALUE — thay hoặc thêm dòng trong .env (giá trị có khoảng trắng được đặt trong ngoặc kép)
    local key=$1 value=$2 file="$APP_DIR/.env"
    [[ "$value" =~ [[:space:]\#\"] ]] && value="\"${value//\"/\\\"}\""
    if grep -qE "^#?\s*${key}=" "$file"; then
        local escaped
        escaped=$(printf '%s' "$value" | sed -e 's/[\/&|]/\\&/g')
        sed -i -E "s|^#?\s*${key}=.*|${key}=${escaped}|" "$file"
    else
        printf '%s=%s\n' "$key" "$value" >> "$file"
    fi
}
get_env() { { grep -E "^${1}=" "$APP_DIR/.env" 2>/dev/null || true; } | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

# Chuỗi ngẫu nhiên chữ+số (dùng PHP: tránh lỗi SIGPIPE của "head -c" khi bật pipefail).
random_secret() { php -r 'echo bin2hex(random_bytes(intdiv((int) $argv[1], 2)));' "${1:-32}"; }

# ---------------------------------------------------------------------------- 0. kiểm tra
step "Kiểm tra môi trường"
[ "$(id -u)" -eq 0 ] || die "Cần chạy bằng root: sudo bash deploy.sh"
. /etc/os-release
case "${ID}:${VERSION_ID}" in
    ubuntu:22.04|ubuntu:24.04|debian:12) ok "Hệ điều hành: $PRETTY_NAME" ;;
    *) warn "Chưa kiểm thử trên $PRETTY_NAME (hỗ trợ: Ubuntu 22.04/24.04, Debian 12). Vẫn tiếp tục." ;;
esac
exec 9>/var/lock/bepminhbach-deploy.lock
flock -n 9 || die "Một lần triển khai khác đang chạy."
if [ -n "$DOMAIN" ] && ! [[ "$DOMAIN" =~ ^[A-Za-z0-9.-]+$ ]]; then die "DOMAIN không hợp lệ: $DOMAIN"; fi
FIRST_INSTALL=1; [ -f "$APP_DIR/.env" ] && FIRST_INSTALL=0
[ "$FIRST_INSTALL" = 1 ] && ok "Cài đặt mới vào $APP_DIR" || ok "Cập nhật bản đang chạy ở $APP_DIR"

# ---------------------------------------------------------------------------- 1. gói hệ thống
step "Cài gói hệ thống (Nginx, PHP ${PHP_VERSION}, MariaDB, Composer…)"
apt-get update -qq
apt-get install -y -qq ca-certificates curl gnupg git unzip rsync cron nginx mariadb-server lsb-release >/dev/null
if ! apt-cache show "php${PHP_VERSION}-fpm" >/dev/null 2>&1; then
    warn "Kho mặc định không có PHP ${PHP_VERSION} — thêm kho PHP của Ondřej Surý"
    if [ "$ID" = ubuntu ]; then
        apt-get install -y -qq software-properties-common >/dev/null
        add-apt-repository -y ppa:ondrej/php >/dev/null
    else
        curl -fsSL https://packages.sury.org/php/apt.gpg -o /usr/share/keyrings/sury-php.gpg
        echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/sury-php.list
        chmod 644 /usr/share/keyrings/sury-php.gpg /etc/apt/sources.list.d/sury-php.list
    fi
    apt-get update -qq
fi
PHP_PKGS=(fpm cli common mysql mbstring intl xml gd curl zip bcmath opcache)
apt-get install -y -qq "${PHP_PKGS[@]/#/php${PHP_VERSION}-}" >/dev/null
if [ "$SKIP_CERTBOT" != 1 ] && [ -n "$DOMAIN" ] && [ -n "$LE_EMAIL" ]; then
    apt-get install -y -qq certbot python3-certbot-nginx >/dev/null
fi
[ "$SKIP_FIREWALL" != 1 ] && apt-get install -y -qq ufw >/dev/null
php -r 'exit(function_exists("imagewebp") ? 0 : 1);' || die "PHP GD chưa hỗ trợ WebP (cần cho ảnh bài viết)."
ok "PHP $(php -r 'echo PHP_VERSION;'), $(nginx -v 2>&1 | cut -d/ -f2), MariaDB $(mariadb --version | grep -m1 -oE '[0-9]+\.[0-9]+\.[0-9]+' || true)"

if ! command -v composer >/dev/null; then
    EXPECTED=$(curl -fsSL https://composer.github.io/installer.sig)
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    ACTUAL=$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")
    [ "$EXPECTED" = "$ACTUAL" ] || die "Chữ ký trình cài Composer không khớp."
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi
ok "Composer $(composer --version 2>/dev/null | grep -m1 -oE '[0-9]+\.[0-9]+\.[0-9]+' || true)"

svc enable mariadb
svc enable "php${PHP_VERSION}-fpm"
svc enable nginx
svc enable cron

# ---------------------------------------------------------------------------- 2. CSDL
step "Cơ sở dữ liệu"
mkdir -p "$SECRETS_DIR"; chmod 700 "$SECRETS_DIR"
if [ -f "$SECRETS_DIR/db.env" ]; then
    # shellcheck disable=SC1091
    . "$SECRETS_DIR/db.env"
else
    DB_PASSWORD=$(random_secret 32)
    printf 'DB_NAME=%s\nDB_USER=%s\nDB_PASSWORD=%s\n' "$DB_NAME" "$DB_USER" "$DB_PASSWORD" > "$SECRETS_DIR/db.env"
    chmod 600 "$SECRETS_DIR/db.env"
fi
mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
# File đăng nhập cho mysqldump của script sao lưu (không lộ mật khẩu trên dòng lệnh)
printf '[client]\nuser=%s\npassword=%s\n' "$DB_USER" "$DB_PASSWORD" > "$SECRETS_DIR/my.cnf"
chmod 600 "$SECRETS_DIR/my.cnf"
ok "CSDL ${DB_NAME} + tài khoản ${DB_USER} (mật khẩu ở $SECRETS_DIR/db.env)"

# ---------------------------------------------------------------------------- 3. mã nguồn
step "Lấy mã nguồn"
if [ "$FIRST_INSTALL" = 0 ] && [ -f "$APP_DIR/artisan" ] && [ -f "$APP_DIR/vendor/autoload.php" ]; then
    artisan down --retry=30 >/dev/null 2>&1 && MAINTENANCE=1 && ok "Bật chế độ bảo trì"
fi
mkdir -p "$APP_DIR"
if [ -n "$SOURCE_DIR" ]; then
    [ -f "$SOURCE_DIR/artisan" ] || die "SOURCE_DIR không phải thư mục dự án: $SOURCE_DIR"
    rsync -a --delete \
        --exclude='.git/' --exclude='.env' --exclude='vendor/' --exclude='node_modules/' \
        --exclude='storage/' --exclude='public/uploads/' --exclude='bootstrap/cache/*.php' \
        "$SOURCE_DIR/" "$APP_DIR/"
    ok "Đồng bộ từ $SOURCE_DIR"
elif [ -d "$APP_DIR/.git" ]; then
    git -C "$APP_DIR" fetch --quiet origin "$BRANCH"
    git -C "$APP_DIR" reset --quiet --hard "origin/$BRANCH"
    ok "Cập nhật lên $(git -C "$APP_DIR" log -1 --format='%h %s')"
else
    [ -z "$(ls -A "$APP_DIR" 2>/dev/null)" ] || die "$APP_DIR đã có dữ liệu nhưng không phải git repo. Dùng SOURCE_DIR hoặc dọn thư mục."
    git clone --quiet --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
    ok "Clone $(git -C "$APP_DIR" log -1 --format='%h %s')"
fi
# storage/ có thể chưa có (lần đầu hoặc đồng bộ bằng rsync)
mkdir -p "$APP_DIR"/storage/{app/public,framework/{cache/data,sessions,views,testing},logs} "$APP_DIR/bootstrap/cache" "$APP_DIR/public/uploads"
[ -f "$APP_DIR/public/uploads/.htaccess" ] || git -C "$APP_DIR" checkout -- public/uploads/.htaccess 2>/dev/null || true

# ---------------------------------------------------------------------------- 4. .env
step "Cấu hình .env production"
SCHEME=http
if [ -n "$DOMAIN" ] && { [ -d "/etc/letsencrypt/live/$DOMAIN" ] || [ -n "$LE_EMAIL" ] || [ "$SKIP_CERTBOT" = 1 ]; }; then
    SCHEME=https
fi
HOST="${DOMAIN:-$(curl -fsS --max-time 5 https://api.ipify.org 2>/dev/null || hostname -I | awk '{print $1}')}"
[ -f "$APP_DIR/.env" ] || cp "$APP_DIR/.env.example" "$APP_DIR/.env"
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "${SCHEME}://${HOST}"
set_env LOG_LEVEL warning
set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "$DB_NAME"
set_env DB_USERNAME "$DB_USER"
set_env DB_PASSWORD "$DB_PASSWORD"
set_env DEMO_PASSWORD ""
set_env SESSION_SECURE_COOKIE "$([ "$SCHEME" = https ] && echo true || echo false)"
[ -n "$TRUSTED_PROXIES" ] && set_env TRUSTED_PROXIES "$TRUSTED_PROXIES"
chown root:"$APP_USER" "$APP_DIR/.env"; chmod 640 "$APP_DIR/.env"
ok "APP_URL=${SCHEME}://${HOST}"

# ---------------------------------------------------------------------------- 5. thư viện + quyền thư mục
step "Cài thư viện PHP (composer, không gói dev)"
(cd "$APP_DIR" && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet)
chown -R root:root "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} +
find "$APP_DIR" -type f -exec chmod 644 {} +
chown -R "$APP_USER":"$APP_USER" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/public/uploads"
chmod -R u+rwX,g+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/public/uploads"
chown root:"$APP_USER" "$APP_DIR/.env"; chmod 640 "$APP_DIR/.env"
[ -f "$APP_DIR/deploy/deploy.sh" ] && chmod 750 "$APP_DIR/deploy/deploy.sh"
if [ -z "$(get_env APP_KEY)" ]; then
    (cd "$APP_DIR" && php artisan key:generate --force --no-interaction >/dev/null)
    ok "Đã tạo APP_KEY"
fi
ok "Mã nguồn chỉ đọc với web server; chỉ storage/, bootstrap/cache/, public/uploads/ ghi được"

# ---------------------------------------------------------------------------- 6. PHP-FPM
step "Cấu hình PHP-FPM"
cat > "/etc/php/${PHP_VERSION}/fpm/conf.d/99-bepminhbach.ini" <<'INI'
; BếpMinhBạch: ảnh bài viết (trình duyệt đã thu nhỏ trước khi gửi, máy chủ giới hạn 10 MB/ảnh)
upload_max_filesize = 12M
post_max_size = 64M
max_file_uploads = 20
memory_limit = 256M
max_execution_time = 60
expose_php = Off
opcache.enable = 1
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
INI
svc restart "php${PHP_VERSION}-fpm"
ok "upload 12M/ảnh, post 64M, memory 256M"

# ---------------------------------------------------------------------------- 7. Laravel
step "Migrate CSDL và tối ưu cấu hình"
artisan migrate --force --no-interaction
artisan optimize:clear >/dev/null
artisan config:cache >/dev/null
artisan route:cache >/dev/null
artisan view:cache >/dev/null
ok "Đã migrate và cache config/route/view"

# ---------------------------------------------------------------------------- 8. Nginx
step "Cấu hình Nginx"
SOCK="/run/php/php${PHP_VERSION}-fpm.sock"
SERVER_NAME="${DOMAIN:-_}"
cat > /etc/nginx/sites-available/bepminhbach <<NGINX
# BếpMinhBạch — tạo bởi deploy/deploy.sh (chạy lại script sẽ ghi đè; Certbot tự thêm phần HTTPS)
server {
    listen 80;
    listen [::]:80;
    server_name ${SERVER_NAME};
    root ${APP_DIR}/public;
    index index.php;
    charset utf-8;
    client_max_body_size 64m;
    server_tokens off;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${SOCK};
        fastcgi_read_timeout 60s;
    }

    # Thư mục ảnh tải lên: không bao giờ chạy mã
    location ^~ /uploads/ {
        location ~* \.(php|phtml|phar|pl|py|cgi|sh|html?|svg|js)\$ { deny all; }
        add_header X-Content-Type-Options nosniff always;
        expires 30d;
        try_files \$uri =404;
    }

    location ~* \.(woff2?|ttf)\$ { expires 1y; add_header Cache-Control "public, immutable"; access_log off; try_files \$uri =404; }
    location ~* \.(css|js|svg|ico|webp|png|jpe?g)\$ { expires 7d; access_log off; try_files \$uri =404; }

    # Không lộ file ẩn (.env, .git…)
    location ~ /\.(?!well-known) { deny all; }
}
NGINX
ln -sf /etc/nginx/sites-available/bepminhbach /etc/nginx/sites-enabled/bepminhbach
rm -f /etc/nginx/sites-enabled/default
nginx -t -q
svc reload nginx
ok "server_name ${SERVER_NAME} → ${APP_DIR}/public"

# ---------------------------------------------------------------------------- 9. HTTPS
if [ -n "$DOMAIN" ] && [ "$SKIP_CERTBOT" != 1 ] && [ -n "$LE_EMAIL" ]; then
    step "HTTPS (Let's Encrypt)"
    SERVER_IP=$(curl -fsS --max-time 5 https://api.ipify.org 2>/dev/null || true)
    DNS_IP=$(getent ahostsv4 "$DOMAIN" | awk 'NR==1{print $1}' || true)
    if [ -n "$SERVER_IP" ] && [ "$DNS_IP" != "$SERVER_IP" ]; then
        warn "DNS $DOMAIN đang trỏ về '${DNS_IP:-không có}', IP máy này là $SERVER_IP — Let's Encrypt có thể thất bại."
    fi
    if certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$LE_EMAIL" --redirect --keep-until-expiring -q; then
        ok "Đã bật HTTPS + chuyển hướng http → https (tự gia hạn qua certbot.timer)"
    else
        warn "Chưa xin được chứng chỉ. Trang tạm chạy HTTP; sửa DNS rồi chạy lại script."
        set_env APP_URL "http://${DOMAIN}"; set_env SESSION_SECURE_COOKIE false
        artisan config:cache >/dev/null
    fi
elif [ -z "$DOMAIN" ]; then
    warn "Chưa có DOMAIN: chạy bằng HTTP qua IP — KHÔNG dùng cho dữ liệu thật (mật khẩu đi qua mạng không mã hóa)."
fi

# ---------------------------------------------------------------------------- 10. tường lửa
if [ "$SKIP_FIREWALL" != 1 ] && command -v ufw >/dev/null; then
    step "Tường lửa (ufw)"
    SSH_PORTS=$(ss -Htlnp 2>/dev/null | awk '/sshd/ {n = split($4, a, ":"); print a[n]}' | sort -u || true)
    for port in ${SSH_PORTS:-22}; do ufw allow "${port}/tcp" >/dev/null; done
    ufw allow 'Nginx Full' >/dev/null
    ufw --force enable >/dev/null
    ok "Chỉ mở SSH (cổng ${SSH_PORTS//$'\n'/, }), HTTP, HTTPS"
fi

# ---------------------------------------------------------------------------- 11. sao lưu hằng ngày
step "Sao lưu tự động"
mkdir -p "$BACKUP_DIR"; chmod 700 "$BACKUP_DIR"
cat > /usr/local/bin/bepminhbach-backup <<BACKUP
#!/usr/bin/env bash
# Sao lưu CSDL + ảnh tải lên của BếpMinhBạch, giữ 14 ngày. Tạo bởi deploy/deploy.sh.
set -euo pipefail
umask 077
STAMP=\$(date +%F-%H%M)
mysqldump --defaults-extra-file=${SECRETS_DIR}/my.cnf --single-transaction --quick --default-character-set=utf8mb4 ${DB_NAME} | gzip > ${BACKUP_DIR}/db-\$STAMP.sql.gz
tar -czf ${BACKUP_DIR}/uploads-\$STAMP.tar.gz -C ${APP_DIR}/public uploads
find ${BACKUP_DIR} -name '*.gz' -mtime +14 -delete
BACKUP
chmod 700 /usr/local/bin/bepminhbach-backup
printf '# BếpMinhBạch: sao lưu 02:30 hằng ngày\n30 2 * * * root /usr/local/bin/bepminhbach-backup >/dev/null 2>&1\n' > /etc/cron.d/bepminhbach
chmod 644 /etc/cron.d/bepminhbach
/usr/local/bin/bepminhbach-backup
ok "02:30 hằng ngày → $BACKUP_DIR (giữ 14 ngày). Đã chạy thử 1 bản."

# ---------------------------------------------------------------------------- 12. quản trị viên đầu tiên
step "Tài khoản quản trị"
ADMINS=$(cd "$APP_DIR" && runuser -u "$APP_USER" -- php -r '
    require "vendor/autoload.php"; $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo App\Models\User::where("role", App\Enums\Role::Admin->value)->where("is_active", true)->count();')
if [ "${ADMINS:-0}" -gt 0 ]; then
    ok "Đã có ${ADMINS} quản trị viên"
elif [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
    (cd "$APP_DIR" && BMB_ADMIN_PASSWORD="$ADMIN_PASSWORD" runuser -u "$APP_USER" -- \
        php artisan bmb:create-admin --name="${ADMIN_NAME:-Quản trị}" --email="$ADMIN_EMAIL" --no-interaction)
elif [ -t 0 ]; then
    (cd "$APP_DIR" && runuser -u "$APP_USER" -- php artisan bmb:create-admin)
else
    warn "Chưa có quản trị viên. Chạy: cd $APP_DIR && sudo -u $APP_USER php artisan bmb:create-admin"
fi

# ---------------------------------------------------------------------------- 13. hoàn tất
{
    printf '# Cấu hình lần triển khai gần nhất (deploy.sh tự đọc lại). Sửa tay hoặc truyền biến môi trường để đổi.\n'
    for var in DOMAIN LE_EMAIL REPO_URL BRANCH APP_DIR PHP_VERSION SKIP_FIREWALL SKIP_CERTBOT TRUSTED_PROXIES; do
        printf 'SAVED_%s=%q\n' "$var" "${!var}"
    done
} > "$SAVED_CONF"
chmod 600 "$SAVED_CONF"

if [ "$MAINTENANCE" = 1 ]; then artisan up >/dev/null; MAINTENANCE=0; ok "Tắt chế độ bảo trì"; fi
step "Kiểm tra cấu hình production"
artisan bmb:check-production || warn "Còn mục cần xử lý (xem bảng trên)."

APP_URL_FINAL=$(get_env APP_URL)
printf '\n%s============================================================%s\n' "$c_ok" "$c_off"
printf '%s  BếpMinhBạch đã chạy: %s%s\n' "$c_ok" "$APP_URL_FINAL" "$c_off"
printf '  Trang quản trị:      %s/admin\n' "$APP_URL_FINAL"
printf '  Mã nguồn:            %s\n' "$APP_DIR"
printf '  Mật khẩu CSDL:       %s/db.env (chỉ root đọc được)\n' "$SECRETS_DIR"
printf '  Sao lưu:             %s (02:30 hằng ngày)\n' "$BACKUP_DIR"
printf '  Cập nhật bản mới:    sudo bash %s/deploy/deploy.sh\n' "$APP_DIR"
printf '%s============================================================%s\n' "$c_ok" "$c_off"
