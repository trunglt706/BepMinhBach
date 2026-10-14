# Test Plan

## Chiến lược
Ưu tiên Feature tests cho luồng nghiệp vụ và quyền truy cập; Unit tests cho quy tắc tính toán/định dạng nếu có logic riêng. Dùng factories và dữ liệu giả. Chạy bộ test hiện có theo framework trong repository.

## Nhóm test
### Authentication & permissions
- T01: Khách chưa đăng nhập bị chuyển đến trang đăng nhập khi mở trang nội bộ.
- T02: Tài khoản bị khóa không đăng nhập/không tiếp tục thực hiện hành động.
- T03: Tình nguyện viên không tạo/xóa đợt nếu không có quyền.
- T04: Người dùng không xem hoặc sửa task/đợt ngoài phạm vi được phép.
- T05: Admin có thể quản lý tài khoản theo quyền.

### Cooking batches
- T06: Tạo đợt với dữ liệu hợp lệ.
- T07: Từ chối số suất âm, thiếu tên hoặc ngày giờ không hợp lệ.
- T08: Cập nhật trạng thái theo workflow.
- T09: Đợt đã hủy không nhận cập nhật thông thường.
- T10: Không xóa cứng đợt có lịch sử.

### Ingredients
- T11: Thêm/sửa nguyên liệu.
- T12: Từ chối lượng âm và đơn vị rỗng.
- T13: Báo thiếu nguyên liệu và hiển thị cảnh báo.
- T14: Không tự coi lượng dự kiến là tồn kho hoặc lượng thực tế.

### Tasks
- T15: Điều phối viên tạo và phân công công việc.
- T16: Người được giao cập nhật trạng thái theo quyền.
- T17: Đánh dấu hoàn thành ghi nhận người và thời điểm.
- T18: Báo bị vướng cần ghi lý do theo quy tắc đã thống nhất.

### Results & expenses
- T19: Nhập số suất đã chuẩn bị/phân phát hợp lệ.
- T20: Chênh lệch giữa các số liệu được hiển thị, không tự điều chỉnh.
- T21: Không chấp nhận số suất âm hoặc số thập phân.
- T22: Thêm chi phí VND; từ chối số tiền âm.
- T23: Tổng chi phí đúng và không trộn lẫn “chưa nhập” với 0.

### Reports & usability
- T24: Báo cáo tổng hợp đúng theo phạm vi lọc.
- T25: CSV có header, encoding phù hợp và escape dữ liệu nguy hiểm.
- T26: Báo cáo không có dữ liệu cá nhân người nhận.
- T27: Checklist in có tên đợt và danh sách cần làm, không in navigation.
- T28: Trang tổng quan hiển thị empty state khi chưa có dữ liệu.
- T29: Lỗi validation hiển thị cạnh trường liên quan.
- T30: Giao diện hoạt động tại viewport 360px, 768px và desktop.

## Test data
- Dùng tài khoản/đợt/nguyên liệu giả.
- Không dùng tên/số điện thoại/dữ liệu sức khỏe của người thật.
- Seed demo không chạy tự động ở production.

## Hoàn tất test
- Chạy test suite liên quan sau mỗi thay đổi.
- Chạy toàn bộ test trước khi kết luận MVP.
- Ghi test đã chạy, kết quả và phần chưa thể kiểm tra vào báo cáo cuối.
- Nếu test thất bại, không bỏ qua hoặc làm test yếu đi chỉ để đạt trạng thái xanh; xác định nguyên nhân và sửa phù hợp.
