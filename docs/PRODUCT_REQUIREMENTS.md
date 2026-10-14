# Product Requirements — BếpMinhBạch

## 1. Tầm nhìn
Giúp bếp ăn thiện nguyện tổ chức từng đợt nấu ăn rõ ràng, dễ phối hợp, dễ tổng hợp và có thể đối chiếu với sổ ghi chép thực tế. Công cụ phục vụ điều phối nội bộ; không thay thế chuyên môn an toàn thực phẩm.

## 2. Người dùng và vai trò
- **Quản trị hệ thống (Admin):** quản lý tài khoản, vai trò và cấu hình cơ bản.
- **Điều phối viên:** tạo/quản lý đợt nấu, nguyên liệu, công việc, số suất và báo cáo.
- **Tình nguyện viên:** xem công việc được giao, cập nhật tiến độ, báo thiếu nguyên liệu.
- **Người ghi nhận kết quả (Recorder):** nhập số suất hoàn thành/phân phát và chi phí thực tế.
Có thể cho phép một tài khoản có nhiều quyền; tránh tạo nhiều vai trò nếu Policy/permission đơn giản đáp ứng được.

## 3. Mục tiêu và chỉ số thử nghiệm
- Tạo một đợt nấu ăn trong vài phút.
- Người mới có thể hiểu trang “Công việc của tôi hôm nay” mà không cần đào tạo dài.
- Đối chiếu được số suất dự kiến, đã chuẩn bị và đã phân phát.
- Có thể xem lại chi phí và công việc còn thiếu sau mỗi đợt.
- Có bản checklist in được khi cần làm việc ngoại tuyến.
Các mục tiêu này là giả thuyết để thử nghiệm với một bếp ăn thật, không phải cam kết hiệu quả đã được chứng minh.

## 4. Chức năng và tiêu chí chấp nhận

### FR-01 — Tài khoản và quyền truy cập
- Người chưa đăng nhập không được truy cập trang nội bộ.
- Admin có thể tạo/sửa/khóa tài khoản và gán vai trò.
- Người dùng chỉ thao tác trong phạm vi quyền được cấp.
- Không cho tài khoản đã khóa đăng nhập.

### FR-02 — Đợt nấu ăn
Thông tin tối thiểu: tên đợt, ngày/giờ, địa điểm dạng mô tả, số suất dự kiến, người điều phối, ghi chú, trạng thái.
Trạng thái đề xuất: `draft`, `planned`, `in_progress`, `completed`, `cancelled`.
- Tạo/sửa đợt theo quyền.
- Không xóa cứng đợt đã phát sinh kết quả; dùng hủy hoặc lưu trữ.
- Đợt đã hoàn tất cần có xác nhận trước khi mở lại/chỉnh số liệu.

### FR-03 — Nguyên liệu
Thông tin: tên nguyên liệu, đơn vị, lượng dự kiến, lượng thực tế đã dùng (nếu ghi nhận), ghi chú.
- Cho phép thêm/sửa/xóa nguyên liệu khi phù hợp trạng thái và quyền.
- Hỗ trợ đơn vị rõ ràng: kg, g, lít, ml, bó, gói, thùng, cái hoặc đơn vị tự nhập có kiểm soát.
- Không tự quy đổi đơn vị nếu chưa có quy tắc rõ ràng.
- Không cần tồn kho tổng hợp trong MVP.

### FR-04 — Công việc và checklist
Thông tin: tên việc, mô tả ngắn, người phụ trách, hạn hoàn thành, trạng thái, ghi chú.
Trạng thái: `todo`, `in_progress`, `blocked`, `done`.
- Điều phối viên phân công được người phụ trách.
- Tình nguyện viên xem được việc của mình và cập nhật trạng thái theo quyền.
- Có thao tác “Báo thiếu nguyên liệu/Đang bị vướng”.
- Khi đánh dấu hoàn thành, ghi lại thời điểm và người cập nhật.

### FR-05 — Số suất ăn
- Lưu số suất dự kiến, số suất đã chuẩn bị và số suất đã phân phát.
- Các giá trị là số nguyên không âm.
- Không mặc định rằng số suất phân phát luôn bằng số suất chuẩn bị.
- Hiển thị chênh lệch để điều phối viên rà soát; cho phép ghi chú giải thích.
- Lịch sử chỉnh sửa số liệu quan trọng nên có người cập nhật và thời gian.

### FR-06 — Chi phí
- Ghi nhận tổng chi phí nguyên liệu theo đợt; có thể nhập các dòng chi phí: tên khoản, số tiền, ghi chú.
- Tiền VND là mặc định; lưu số tiền bằng số nguyên.
- Không phải hệ thống kế toán; không quản lý hóa đơn điện tử hoặc thanh toán.
- Hiển thị tổng dự kiến (nếu có) và tổng thực tế đã nhập, không tự suy đoán số liệu còn thiếu.

### FR-07 — Báo cáo và xuất dữ liệu
- Báo cáo theo đợt: thời gian, suất dự kiến/chuẩn bị/phân phát, tổng chi phí, nguyên liệu và công việc.
- Có thể lọc theo ngày/trạng thái.
- CSV phải dùng encoding phù hợp, có tiêu đề cột và dữ liệu được escape đúng.
- PDF nếu triển khai phải có tiếng Việt, ngày giờ tạo và phạm vi báo cáo.
- Báo cáo không chứa danh tính người nhận hỗ trợ.
- Hiển thị cảnh báo “Số liệu do người dùng ghi nhận; cần đối chiếu thực tế”.

### FR-08 — Trang tổng quan
- Hiển thị các đợt sắp diễn ra/đang diễn ra.
- Hiển thị công việc chưa hoàn thành, số suất gần nhất và cảnh báo thiếu nguyên liệu.
- Không tạo số liệu giả khi chưa có dữ liệu; dùng trạng thái rỗng hữu ích.

### FR-09 — Checklist có thể in
- Checklist được in rõ ràng trên giấy A4, ít màu, không in menu điều hướng.
- Có tên đợt, ngày, mục công việc/nguyên liệu, ô đánh dấu và người phụ trách nếu có.
- In được từ trình duyệt; không bắt buộc tích hợp máy in.

## 5. Quy tắc nghiệp vụ
- Mọi số lượng suất và tiền phải không âm.
- Ngày kết thúc (nếu có) không được trước ngày bắt đầu.
- Công việc/nguyên liệu phải thuộc một đợt hợp lệ.
- Không xóa tài khoản đã có lịch sử; khóa tài khoản.
- Đợt đã hủy không nhận cập nhật thông thường, trừ Admin/Điều phối viên có quyền mở lại.
- Báo cáo phải phân biệt số dự kiến và số thực tế.
- Mọi trường không chắc chắn cần được xác nhận với người vận hành; không tự thêm dữ liệu nhạy cảm.

## 6. MVP theo giai đoạn
1. Nền tảng: đăng nhập, vai trò, layout, migrations, seed dữ liệu demo.
2. Đợt nấu ăn: CRUD, trạng thái, trang tổng quan.
3. Checklist: nguyên liệu, công việc, phân công, cập nhật tiến độ.
4. Kết quả: số suất, chi phí, ghi chú chênh lệch.
5. Báo cáo: xem, lọc, CSV, in checklist, PDF nếu phù hợp.
6. Kiểm thử, bảo mật, usability review, tài liệu chạy/deploy.
