# Workflows

## WF-01: Tạo và lập kế hoạch đợt nấu
1. Điều phối viên mở “Đợt nấu ăn” → “Tạo đợt nấu”.
2. Nhập tên, thời gian, địa điểm, số suất dự kiến, người điều phối và ghi chú.
3. Lưu nháp hoặc chuyển sang đã lên kế hoạch.
4. Hệ thống hiển thị trang chi tiết, checklist nguyên liệu và công việc.
5. Nếu thiếu trường bắt buộc, giữ dữ liệu đã nhập và hiển thị lỗi dễ hiểu.

## WF-02: Chuẩn bị nguyên liệu
1. Điều phối viên thêm các dòng nguyên liệu và lượng dự kiến.
2. Tình nguyện viên kiểm tra thực tế.
3. Nếu thiếu, đánh dấu “Cần bổ sung” và nhập ghi chú ngắn.
4. Dashboard/chi tiết đợt hiển thị cảnh báo.
5. Không tự trừ kho hoặc giả định nguyên liệu đã mua.

## WF-03: Phân công và hoàn thành công việc
1. Điều phối viên tạo công việc, chọn người phụ trách và hạn.
2. Tình nguyện viên vào “Công việc của tôi”.
3. Cập nhật “Đang làm”, “Hoàn thành” hoặc “Đang bị vướng”.
4. Nếu bị vướng, ghi lý do ngắn.
5. Hệ thống ghi thời gian/người cập nhật; điều phối viên có thể xử lý phân công lại.

## WF-04: Ghi nhận kết quả
1. Người có quyền nhập số suất đã chuẩn bị và đã phân phát.
2. Hệ thống kiểm tra số nguyên không âm.
3. Nếu số liệu lệch với dự kiến hoặc số đã chuẩn bị, hiển thị nhắc kiểm tra.
4. Người nhập có thể thêm giải thích.
5. Lưu người cập nhật và thời gian; không tự sửa số liệu để khớp nhau.

## WF-05: Ghi nhận chi phí
1. Người có quyền thêm khoản chi nguyên liệu với tên và số tiền.
2. Có thể ghi chú, nhưng không bắt buộc upload chứng từ trong MVP.
3. Hệ thống tổng hợp theo đợt.
4. Không tuyên bố là báo cáo kế toán hoặc bằng chứng độc lập.

## WF-06: Hoàn tất đợt
1. Điều phối viên xem công việc, nguyên liệu, số suất và chi phí.
2. Hệ thống nhắc các mục còn thiếu nhưng không bịa số liệu.
3. Điều phối viên xác nhận hoàn tất và nhập ghi chú rút kinh nghiệm.
4. Đợt chuyển sang `completed`; thao tác chỉnh sửa kết quả sau đó cần quyền và xác nhận rõ ràng.

## WF-07: Báo cáo và xuất dữ liệu
1. Người dùng có quyền mở báo cáo, lọc theo ngày/trạng thái.
2. Xem số liệu tổng hợp và chi tiết.
3. Xuất CSV hoặc in/PDF.
4. Mỗi báo cáo thể hiện thời điểm tạo, phạm vi và lưu ý rằng dữ liệu do người dùng ghi nhận.

## WF-08: Hủy đợt
1. Điều phối viên chọn hủy và nhập lý do nếu cần.
2. Hệ thống yêu cầu xác nhận.
3. Đợt chuyển `cancelled`, không xóa lịch sử.
4. Các task còn mở được đánh dấu/hạn chế thao tác theo quy tắc đã thống nhất.
