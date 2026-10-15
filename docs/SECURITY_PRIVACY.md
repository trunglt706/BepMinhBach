# Security & Privacy

## Dữ liệu tối thiểu
MVP không cần tên, số điện thoại, địa chỉ nhà, chẩn đoán, dị ứng hay hồ sơ sức khỏe của người nhận suất ăn. Không tạo chức năng thu thập các thông tin đó.

## Kiểm soát truy cập
- Bắt buộc đăng nhập với các trang nội bộ.
- Áp dụng authorization phía server cho từng hành động và đối tượng.
- Tình nguyện viên chỉ cập nhật các tác vụ được phép; không dựa vào việc ẩn nút trên UI.
- Admin quản lý tài khoản; không xem/khôi phục mật khẩu dạng rõ.
- Tài khoản bị khóa không thể tiếp tục sử dụng phiên theo cách vượt qua kiểm tra quyền.
- **Phạm vi dữ liệu đợt nấu (15/10/2026)** — `CookingBatch::scopeVisibleTo` / `isVisibleTo`, áp cho danh sách, chi tiết,
  bản in, trang tổng quan, badge menu, báo cáo (trang + CSV), ô chọn đợt khi viết bài:
  - Quản trị viên: mọi đợt.
  - Điều phối viên: chương trình mình là người điều phối (`coordinator_id`) hoặc có công việc giao cho mình.
  - Người ghi nhận, tình nguyện viên: chỉ đợt có công việc giao cho mình.
  - Ngoài phạm vi: trả **404** (không tiết lộ đợt tồn tại) — kiểm tra trong `before()` của policy đợt, công việc,
    nguyên liệu, khoản chi. Điều phối viên giao đợt cho người khác thì mất quyền xem đợt đó.
- **Mật khẩu & phiên:** đổi/đặt lại mật khẩu → các phiên khác bị đăng xuất (`auth.session`) và cookie
  "ghi nhớ đăng nhập" cũ hết hiệu lực; khóa tài khoản / "Đăng xuất mọi thiết bị" xóa phiên + đổi remember token.
  Quên mật khẩu: không tiết lộ email có tài khoản; tài khoản khóa không nhận link; link 1 lần, hết hạn 60 phút;
  giới hạn theo IP và theo email. Bảng `notifications` không lưu token/link đặt lại mật khẩu.
- Danh sách thiết bị đăng nhập (quản trị viên) không hiển thị mã phiên; thao tác đăng xuất dùng mã băm SHA-256.

## Bảo vệ ứng dụng
- Validation server-side cho mọi input.
- CSRF protection theo cơ chế framework.
- Escape dữ liệu hiển thị; tránh XSS.
- Dùng Eloquent/parameter binding; không nối chuỗi SQL từ input.
- Rate limit đăng nhập nếu phù hợp.
- Session/cookie: `HttpOnly`, `SameSite=Lax`, `Secure` mặc định khi `APP_URL` là https://.
- Secret chỉ trong environment variables.
- Không bật debug trên production. `php artisan bmb:check-production` kiểm tra cấu hình (xem `docs/DEPLOYMENT.md`).

## Header bảo mật (middleware `SecurityHeaders`, mọi phản hồi)
- `Content-Security-Policy`: JS chỉ từ chính site (`script-src 'self'`, không `unsafe-inline`/`unsafe-eval`; mọi thư viện nằm trong `public/vendor`, không CDN); ảnh `self`/`data:`/`blob:` (xem trước ảnh trước khi tải lên); `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`, `frame-ancestors 'self'`. `style-src` cho phép inline vì biểu đồ/thanh tiến độ dùng thuộc tính `style` (không chạy được mã).
- View không được có `<script>` nội tuyến (trừ JSON-LD), thuộc tính `on*=` hay `javascript:` — có test kiểm tra.
- `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (tắt camera/micro/vị trí/thanh toán), `Cross-Origin-Opener-Policy: same-origin`.
- `Strict-Transport-Security` chỉ gửi khi yêu cầu đi qua HTTPS.
- Chạy sau proxy/CDN: khai báo `TRUSTED_PROXIES` để giới hạn theo IP dùng đúng IP khách; để trống thì không tin header `X-Forwarded-*`.

## Trang công khai (bài viết)
- Khách (không đăng nhập) chỉ xem bài viết **đã đăng** và đã tới thời điểm đăng; bản nháp trả 404 (người quản lý được xem trước).
- Bài viết gắn với một đợt nấu chỉ hiện: tên đợt, ngày, trạng thái, số suất dự kiến/chuẩn bị/phân phát. **Không** hiện người điều phối, địa điểm, ghi chú, công việc, nguyên liệu hay chi phí.
- **Tên tác giả** (tên tài khoản nội bộ của người viết) hiện trên thẻ bài viết theo yêu cầu 14/10/2026 (OQ-16). Không hiện email/vai trò.
- Trang đợt nấu công khai `/dot-nau/{public_id}`: chỉ đợt **đang diễn ra** hoặc **đã có bài đăng**; URL dùng mã ngẫu nhiên 16 ký tự hex (không dùng ID tăng dần → không dò được đợt khác). Đợt nháp/kế hoạch chưa có bài trả 404.
- Ảnh nền đợt nấu xử lý như ảnh bài viết (kiểm tra nội dung, WebP, bỏ EXIF/GPS); form nhắc tránh ảnh nhìn rõ mặt người nhận.
- Bộ lọc theo thẻ/đợt chỉ chấp nhận thẻ/đợt có bài đã đăng, nên không dò được tên đợt nội bộ qua `?dot=<id>`.
- Nội dung là HTML **đã lọc theo danh sách cho phép** (xem mục Ảnh tải lên); tiêu đề, tóm tắt, thẻ được escape. Ô nhập nội dung nhắc không ghi thông tin người nhận.
- Chỉ điều phối viên trở lên viết/đăng/gỡ/xóa bài.

## Ảnh tải lên (bài viết)
- Chỉ nhận JPG/PNG/WebP, kiểm tra theo **nội dung file** (finfo/getimagesize), không theo đuôi tên; không nhận SVG/GIF/HEIC.
- Giới hạn: 10 MB/ảnh trước nén, 40 triệu điểm ảnh (chống "bom giải nén"), 12 ảnh album/bài (đếm cả ảnh đang có).
- Máy chủ giải mã rồi mã hóa lại thành WebP: file lưu không chứa EXIF/GPS hay dữ liệu lạ nhúng trong ảnh gốc; tên file ngẫu nhiên; file gốc không được lưu.
- Thư mục `public/uploads` có `.htaccess` chặn chạy mã (PHP/CGI/HTML/SVG/JS) và gửi `X-Content-Type-Options: nosniff`.
- Trình soạn thảo không cho chèn ảnh/file vào nội dung; HTML được lọc theo danh sách cho phép, liên kết chỉ http/https/mailto và luôn có `rel="nofollow noopener noreferrer ugc"`.
- Form nhắc tránh ảnh nhìn rõ mặt người nhận suất ăn khi chưa được đồng ý.

## Lịch sử thay đổi (activity log)
- Chỉ ghi số hiệu và giá trị nghiệp vụ (trạng thái, số suất, tên/số tiền khoản chi, tên việc). **Không** ghi mật khẩu, email, tên người dùng, ghi chú tự do (lý do vướng, lý do hủy/mở lại, ghi chú thiếu nguyên liệu, ghi chú chênh lệch).
- `ActivityLog::record()` còn tự che email và số điện thoại Việt Nam trong tóm tắt (phòng khi chuỗi ghép vô tình chứa).
- Lý do mở lại đợt lưu ở `cooking_batches.reopen_reason` (chỉ người quản lý đợt thấy), không còn trong log.

## Lượt xem và bộ nhớ đệm trang công khai
- Lượt xem: khóa người xem là HMAC(IP + trình duyệt) với APP_KEY, chỉ nằm trong cache và tự hết hạn; không lưu IP hay danh sách người xem.
- Trang công khai gửi `Cache-Control: private` + ETag (trang có token CSRF riêng nên không cho cache dùng chung lưu lại).

## Giới hạn tần suất (chống spam)
- Theo **từng tài khoản** (không theo IP vì tình nguyện viên có thể dùng chung wifi): thao tác ghi 40/phút, mở trang 300/phút. Trang công khai: 120/phút mỗi IP. Đăng nhập: 5 lần sai/email+IP. Chỉnh trong `.env` (`BMB_RATE_*`, xem `config/bmb.php`).
- Vượt giới hạn: AJAX nhận JSON 429 kèm thời gian chờ; form thường quay lại kèm thông báo và dữ liệu đã nhập (trừ mật khẩu).

## Báo cáo và minh bạch
- Báo cáo nội bộ có quyền truy cập; không công khai mặc định.
- Không xuất thông tin cá nhân người nhận.
- Nếu sau này có trang báo cáo công khai, chỉ công bố dữ liệu tổng hợp, kiểm tra khả năng suy ra danh tính ở nhóm nhỏ.
- Hiển thị rõ dữ liệu do người dùng nhập, không phải kiểm toán độc lập.

## An toàn thực phẩm
- Ứng dụng chỉ ghi nhận checklist và thông tin do người dùng cung cấp.
- Không thay thế quy trình chuyên môn, hướng dẫn của cơ quan có thẩm quyền hoặc người phụ trách an toàn thực phẩm.
- Nếu bổ sung nhiệt độ, thời gian, dị ứng hay truy xuất nguồn gốc, phải xác định quy trình, đơn vị đo, người chịu trách nhiệm, quy tắc cảnh báo và cách xử lý dữ liệu sai trước khi triển khai.
- Không gắn nhãn “đạt chuẩn” hoặc “an toàn” chỉ vì checklist đã được đánh dấu hoàn tất.

## Kiểm thử bảo mật
- Kiểm tra người chưa đăng nhập.
- Kiểm tra người dùng sai vai trò.
- Kiểm tra IDOR: đổi ID trong URL/form không truy cập được dữ liệu ngoài quyền.
- Kiểm tra validation, CSRF/XSS cơ bản và xuất CSV chống spreadsheet formula injection.
- Không dùng dữ liệu thật trong môi trường demo.

## Liên hệ từ khách (trang `/lien-he`)
- Thu tối thiểu: chủ đề, họ tên, **một** email hoặc số điện thoại, nội dung. Không có chủ đề "đăng ký nhận suất ăn" (không thu thập thông tin người nhận qua trang công khai); form nhắc không ghi thông tin sức khỏe/người nhận.
- Không lưu IP thô: `client_hash = HMAC(IP, APP_KEY)`, chỉ để giới hạn **1 liên hệ / client / ngày** (theo IP đã băm **hoặc** theo email/SĐT trùng trong ngày).
- Chống spam: giới hạn 5 lần bấm gửi / 10 phút / IP (tính cả lần lỗi, `BMB_RATE_CONTACT`), ô bẫy bot ẩn, CSRF, kiểm tra phía trình duyệt trước khi gửi + kiểm tra lại đầy đủ ở máy chủ.
- Chỉ điều phối viên trở lên xem/đánh dấu xử lý/xóa (`ContactMessagePolicy`). Nội dung liên hệ không ghi vào activity log hay file log. Nên xóa liên hệ sau khi đã xử lý.

## Kiểm tra phía trình duyệt
- `app.js` kiểm tra `required`, độ dài, email, số, email/SĐT trước khi gửi để giảm yêu cầu thừa. **Không** thay thế kiểm tra máy chủ — mọi FormRequest vẫn kiểm tra lại toàn bộ.

## Triển khai (`deploy/deploy.sh`)
- Mật khẩu CSDL ngẫu nhiên, lưu `/root/.gopgio/db.env` (600); `.env` quyền 640 `root:www-data`; mã nguồn không ghi được bởi web server.
- Mật khẩu quản trị không truyền qua tham số dòng lệnh (biến môi trường `BMB_ADMIN_PASSWORD` hoặc nhập ẩn).
- Nginx chặn file ẩn và chạy mã trong `/uploads/`; ufw chỉ mở SSH (cổng thật), 80, 443; sao lưu hằng ngày quyền 700.

## Kênh thông báo của đợt (Slack / Telegram / Chatwork)
- Chỉ điều phối viên trở lên cấu hình (`CookingBatchPolicy::manageNotifications`); mọi tài khoản xem được đợt thấy nút "Tham gia kênh".
- Webhook/token **mã hóa** trong CSDL (APP_KEY), không bao giờ hiện lại đầy đủ (form chỉ hiện 4 ký tự cuối; để trống = giữ giá trị cũ); không ghi vào log; lỗi gửi được che token trước khi lưu/hiển thị (URL Telegram có token).
- Chống SSRF: Slack chỉ nhận webhook `https://hooks.slack.com/services/…`; Telegram/Chatwork gọi địa chỉ API cố định; link tham gia chỉ https đúng tên miền dịch vụ.
- Nội dung gửi: tên + ngày đợt, sự kiện (tóm tắt đã che email/SĐT), người thực hiện, link mở đợt trong hệ thống. **Không gửi** chi phí (tình nguyện viên không được xem chi phí, mà ai có link đều vào kênh được), lý do bị vướng, ghi chú thiếu nguyên liệu, ghi chú chênh lệch. Tên người phụ trách chỉ có trong tin nhắn, không lưu vào activity log.
- Chatwork: ngoặc vuông trong chữ người dùng nhập được đổi sang ngoặc toàn chiều để không tạo thẻ `[To:]`/`[info]`.
- Gửi sau khi giao dịch DB thành công và sau khi trả trang (defer), timeout 5 giây/kênh; lỗi không làm hỏng thao tác của người dùng.
