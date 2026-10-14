# Security & Privacy

## Dữ liệu tối thiểu
MVP không cần tên, số điện thoại, địa chỉ nhà, chẩn đoán, dị ứng hay hồ sơ sức khỏe của người nhận suất ăn. Không tạo chức năng thu thập các thông tin đó.

## Kiểm soát truy cập
- Bắt buộc đăng nhập với các trang nội bộ.
- Áp dụng authorization phía server cho từng hành động và đối tượng.
- Tình nguyện viên chỉ cập nhật các tác vụ được phép; không dựa vào việc ẩn nút trên UI.
- Admin quản lý tài khoản; không xem/khôi phục mật khẩu dạng rõ.
- Tài khoản bị khóa không thể tiếp tục sử dụng phiên theo cách vượt qua kiểm tra quyền.

## Bảo vệ ứng dụng
- Validation server-side cho mọi input.
- CSRF protection theo cơ chế framework.
- Escape dữ liệu hiển thị; tránh XSS.
- Dùng Eloquent/parameter binding; không nối chuỗi SQL từ input.
- Rate limit đăng nhập nếu phù hợp.
- Session/cookie theo cấu hình production an toàn.
- Secret chỉ trong environment variables.
- Không bật debug trên production.

## Trang công khai (bài viết)
- Khách (không đăng nhập) chỉ xem bài viết **đã đăng** và đã tới thời điểm đăng; bản nháp trả 404 (người quản lý được xem trước).
- Bài viết gắn với một đợt nấu chỉ hiện: tên đợt, thời gian, trạng thái, số suất dự kiến/chuẩn bị/phân phát. **Không** hiện người điều phối, tác giả, địa điểm, ghi chú, công việc, nguyên liệu hay chi phí.
- Bộ lọc theo thẻ/đợt chỉ chấp nhận thẻ/đợt có bài đã đăng, nên không dò được tên đợt nội bộ qua `?dot=<id>`.
- Nội dung hiển thị dạng văn bản thuần (escape), không cho HTML. Ô nhập nội dung nhắc không ghi thông tin người nhận.
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
