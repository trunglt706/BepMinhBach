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
