#!/usr/bin/env bash
# =============================================================================
#  Góp Gió — CẬP NHẬT phiên bản mới trên máy chủ ĐÃ CẤU HÌNH SẴN.
#
#  Script KHÔNG cài/cấu hình hệ thống (PHP, Nginx, CSDL, HTTPS, tường lửa, .env, dịch vụ systemd, cron).
#  Phần đó làm một lần theo docs/DEPLOYMENT.md. Mỗi lần cập nhật script chạy:
#    bật bảo trì → fetch + pull code → composer → npm → thư mục storage + cấp quyền → migrate
#    → cache → tắt bảo trì → systemctl (reload PHP-FPM, khởi động lại worker queue) → kiểm tra production.
#  Lỗi ở bất kỳ bước nào: tự tắt bảo trì để bản đang chạy vẫn phục vụ.
#
#  Cách dùng (quyền root, từ thư mục mã nguồn trên máy chủ):
#    sudo bash deploy/deploy.sh
#
#  Biến tùy chọn (truyền qua môi trường hoặc file deploy/deploy.env):
#    APP_DIR        thư mục mã nguồn (mặc định: thư mục cha của deploy/)
#    BRANCH         nhánh git (mặc định main)
#    APP_USER       người dùng chạy PHP-FPM / worker (mặc định www-data)
#    PHP_FPM        tên dịch vụ PHP-FPM (mặc định tự dò: phpX.Y-fpm theo phiên bản php đang dùng)
#    QUEUE_SERVICE  tên dịch vụ worker queue (mặc định tự dò: gopgio-queue, bepminhbach-queue)
#    SKIP_NPM=1     bỏ qua bước npm (máy chủ không có Node; tài nguyên trong public/vendor đã có sẵn trong git)
#    SKIP_PULL=1    không kéo code (đã đồng bộ code bằng cách khác, ví dụ rsync)
# =============================================================================
set -Eeuo pipefail
umask 022

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
[ -f "$SCRIPT_DIR/deploy.env" ] && . "$SCRIPT_DIR/deploy.env"

APP_DIR="${APP_DIR:-$(cd "$SCRIPT_DIR/.." && pwd)}"
BRANCH="${BRANCH:-main}"
APP_USER="${APP_USER:-www-data}"
SKIP_NPM="${SKIP_NPM:-0}"
SKIP_PULL="${SKIP_PULL:-0}"
export COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1

# ---------------------------------------------------------------------------- tiện ích
c_info=$'\033[1;34m'; c_ok=$'\033[1;32m'; c_warn=$'\033[1;33m'; c_err=$'\033[1;31m'; c_off=$'\033[0m'
step() { printf '\n%s==> %s%s\n' "$c_info" "$*" "$c_off"; }
ok()   { printf '%s    ✓ %s%s\n' "$c_ok" "$*" "$c_off"; }
warn() { printf '%s    ! %s%s\n' "$c_warn" "$*" "$c_off"; }
die()  { printf '%s✗ %s%s\n' "$c_err" "$*" "$c_off" >&2; exit 1; }

MAINTENANCE=0
on_error() {
    printf '%s✗ Lỗi ở dòng %s. Xem thông báo phía trên.%s\n' "$c_err" "$1" "$c_off" >&2
    if [ "$MAINTENANCE" = 1 ]; then
        warn "Tắt chế độ bảo trì để bản đang chạy vẫn phục vụ."
        (cd "$APP_DIR" && php artisan up >/dev/null 2>&1) || true
    fi
}
trap 'on_error $LINENO' ERR

# Chạy artisan bằng người dùng của web server (file cache/log tạo ra thuộc đúng chủ).
artisan() { (cd "$APP_DIR" && runuser -u "$APP_USER" -- php artisan "$@"); }

# Dịch vụ systemd có tồn tại không (không có systemd — ví dụ container — thì coi như không).
has_unit() { command -v systemctl >/dev/null && systemctl list-unit-files "$1.service" --no-legend 2>/dev/null | grep -q .; }

# ---------------------------------------------------------------------------- 0. kiểm tra
step "Kiểm tra"
[ "$(id -u)" -eq 0 ] || die "Cần chạy bằng root (để cấp quyền thư mục và khởi động lại dịch vụ): sudo bash deploy/deploy.sh"
[ -f "$APP_DIR/artisan" ] || die "Không thấy mã nguồn Laravel ở $APP_DIR (đặt APP_DIR=…)."
[ -f "$APP_DIR/.env" ] || die "Chưa có $APP_DIR/.env — cấu hình máy chủ lần đầu theo docs/DEPLOYMENT.md trước."
id "$APP_USER" >/dev/null 2>&1 || die "Không có người dùng $APP_USER (đặt APP_USER=…)."
command -v composer >/dev/null || die "Chưa cài Composer."
exec 9>"/tmp/$(basename "$APP_DIR")-deploy.lock"
flock -n 9 || die "Một lần cập nhật khác đang chạy."
# Git chạy bằng root trên thư mục thuộc người khác cần khai báo an toàn.
git config --global --get-all safe.directory 2>/dev/null | grep -qxF "$APP_DIR" || git config --global --add safe.directory "$APP_DIR"
ok "Mã nguồn: $APP_DIR (nhánh $BRANCH), chạy PHP bằng $APP_USER"

# ---------------------------------------------------------------------------- 1. bật bảo trì
step "Bật chế độ bảo trì"
if [ -f "$APP_DIR/vendor/autoload.php" ]; then
    artisan down --retry=30 >/dev/null && MAINTENANCE=1 && ok "Khách thấy trang bảo trì (tự tải lại sau 30 giây)"
else
    warn "Chưa có vendor/ (lần đầu cài thư viện) — bỏ qua bước bảo trì."
fi

# ---------------------------------------------------------------------------- 2. lấy code mới
if [ "$SKIP_PULL" != 1 ]; then
    step "Lấy code mới (git fetch + pull)"
    # Tài nguyên trong public/vendor do "npm run assets" sinh lại → trả về bản trong git để pull không bị vướng.
    git -C "$APP_DIR" checkout -- public/vendor 2>/dev/null || true
    BEFORE=$(git -C "$APP_DIR" rev-parse --short HEAD)
    git -C "$APP_DIR" fetch --prune origin "$BRANCH"
    git -C "$APP_DIR" checkout "$BRANCH" >/dev/null 2>&1 || git -C "$APP_DIR" checkout -b "$BRANCH" "origin/$BRANCH"
    git -C "$APP_DIR" pull --ff-only origin "$BRANCH" \
        || die "Không pull được (có sửa đổi trên máy chủ?). Kiểm tra: git -C $APP_DIR status"
    ok "$BEFORE → $(git -C "$APP_DIR" log -1 --format='%h %s')"
fi

# ---------------------------------------------------------------------------- 3. thư viện PHP
step "Composer (không gói dev)"
(cd "$APP_DIR" && composer install --no-dev --optimize-autoloader --no-progress --quiet)
ok "vendor/ đã cập nhật"

# ---------------------------------------------------------------------------- 4. tài nguyên giao diện
if [ "$SKIP_NPM" != 1 ] && command -v npm >/dev/null; then
    step "npm (sao chép thư viện giao diện + bộ icon rút gọn vào public/vendor)"
    (cd "$APP_DIR" && npm ci --no-audit --no-fund --loglevel=error && npm run --silent assets)
    ok "public/vendor đã cập nhật"
else
    warn "Bỏ qua npm (SKIP_NPM=1 hoặc máy chủ không có Node) — dùng public/vendor có sẵn trong git."
fi

# ---------------------------------------------------------------------------- 5. storage + quyền thư mục
step "Thư mục storage và quyền"
mkdir -p "$APP_DIR"/storage/{app/public,framework/{cache/data,sessions,views,testing},logs} \
         "$APP_DIR/bootstrap/cache" "$APP_DIR/public/uploads"
[ -f "$APP_DIR/public/uploads/.htaccess" ] || git -C "$APP_DIR" checkout -- public/uploads/.htaccess 2>/dev/null || true
# Mã nguồn: root sở hữu, web server chỉ đọc (bỏ qua node_modules/.git cho nhanh).
chown -R root:root "$APP_DIR"
find "$APP_DIR" \( -path "$APP_DIR/node_modules" -o -path "$APP_DIR/.git" \) -prune -o -type d -exec chmod 755 {} +
find "$APP_DIR" \( -path "$APP_DIR/node_modules" -o -path "$APP_DIR/.git" \) -prune -o -type f -exec chmod 644 {} +
# Chỉ các thư mục cần ghi mới thuộc web server.
chown -R "$APP_USER":"$APP_USER" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/public/uploads"
chmod -R u+rwX,g+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/public/uploads"
chown root:"$APP_USER" "$APP_DIR/.env"; chmod 640 "$APP_DIR/.env"
chmod 750 "$APP_DIR/deploy/deploy.sh"
ok "Chỉ storage/, bootstrap/cache/, public/uploads/ ghi được; .env chỉ root + $APP_USER đọc"

# ---------------------------------------------------------------------------- 6. CSDL
step "Migrate CSDL"
artisan migrate --force --no-interaction

# ---------------------------------------------------------------------------- 7. cache
step "Làm mới cache (config, route, view, event)"
artisan optimize:clear >/dev/null
artisan config:cache >/dev/null
artisan route:cache >/dev/null
artisan view:cache >/dev/null
artisan event:cache >/dev/null
ok "Đã cache config/route/view/event"

# ---------------------------------------------------------------------------- 8. tắt bảo trì
if [ "$MAINTENANCE" = 1 ]; then
    step "Tắt chế độ bảo trì"
    artisan up >/dev/null; MAINTENANCE=0
    ok "Trang đã mở lại"
fi

# ---------------------------------------------------------------------------- 9. dịch vụ
step "Dịch vụ (systemctl)"
PHP_FPM="${PHP_FPM:-php$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')-fpm}"
if has_unit "$PHP_FPM"; then
    systemctl reload "$PHP_FPM" && ok "Reload $PHP_FPM (xóa opcache, nạp code mới)"
else
    warn "Không thấy dịch vụ $PHP_FPM — tự reload PHP-FPM nếu cần (đặt PHP_FPM=…)."
fi
# Worker đang chạy nạp lại code mới sau job hiện tại; dịch vụ systemd tự chạy lại worker.
artisan queue:restart >/dev/null
if [ -z "${QUEUE_SERVICE:-}" ]; then
    for name in gopgio-queue bepminhbach-queue; do has_unit "$name" && QUEUE_SERVICE=$name && break; done
fi
if [ -n "${QUEUE_SERVICE:-}" ] && has_unit "$QUEUE_SERVICE"; then
    systemctl restart "$QUEUE_SERVICE" && ok "Khởi động lại $QUEUE_SERVICE"
else
    warn "Không thấy dịch vụ worker queue — thông báo kênh/email sẽ không gửi nếu chưa có worker (xem docs/DEPLOYMENT.md)."
fi

# ---------------------------------------------------------------------------- 10. kiểm tra
step "Kiểm tra cấu hình production"
artisan bmb:check-production || warn "Còn mục cần xử lý (xem bảng trên)."

printf '\n%s✓ Cập nhật xong: %s%s\n' "$c_ok" "$(git -C "$APP_DIR" log -1 --format='%h %s' 2>/dev/null || echo "$APP_DIR")" "$c_off"
