# Open Questions — xác nhận trong quá trình thử nghiệm

Không chặn các chức năng MVP không phụ thuộc câu trả lời. Cột “Đang áp dụng” là cách hệ thống đang chạy cho tới khi người vận hành xác nhận.

| ID | Câu hỏi | Đang áp dụng | Khi nào cần xác nhận |
|---|---|---|---|
| OQ-01 | Đăng nhập bằng email hay tên đăng nhập? | Email (không phân biệt hoa thường) | Trước khi thử với bếp thật |
| OQ-02 | Một người có thể vừa điều phối vừa ghi nhận kết quả không? | Có. Vai trò phân cấp: Admin ⊇ Điều phối viên ⊇ Người ghi nhận ⊇ Tình nguyện viên. Mỗi tài khoản một vai trò. | Nếu cần tổ hợp quyền khác (vd. ghi nhận nhưng không xem báo cáo) |
| OQ-03 | Có cần chi phí dự kiến hay chỉ chi phí thực tế? | Có trường “chi phí dự kiến” tùy chọn ở cấp đợt; chi phí thực tế là danh sách khoản chi | Trước khi dùng báo cáo chi phí |
| OQ-04 | Ai được sửa số liệu sau khi hoàn tất? | Đợt hoàn tất bị khóa. Điều phối viên/Admin phải “Mở lại” (bắt buộc ghi lý do + xác nhận, lưu vào lịch sử) rồi mới sửa | Trước khi khóa workflow |
| OQ-05 | PDF có bắt buộc ở MVP không? | Chưa làm PDF. Có CSV + bản in A4; người dùng có thể “In → Lưu thành PDF” trong trình duyệt | Sau khi thử CSV/in |
| OQ-06 | Đợt nấu có cần ngày kết thúc không? | Không; chỉ `starts_at`, `completed_at`, `cancelled_at` | Khi có nhu cầu đợt kéo dài nhiều ngày |
| OQ-07 | Có cần báo cáo công khai cho cộng đồng không? | Không; báo cáo chỉ dành cho người ghi nhận trở lên sau khi đăng nhập | Chỉ khi được yêu cầu rõ |
| OQ-08 | Có cần lượng nguyên liệu thực tế đã dùng? | Có, tùy chọn, cùng đơn vị với lượng dự kiến | Khi làm kết quả |
| OQ-09 | Thông tin kết nối MySQL/MariaDB | **Đã giải quyết:** người dùng cấu hình MySQL `bepminhbach` trong `.env`; migration và bộ test đã chạy trên MySQL 8.4 | — |
| OQ-10 | Tình nguyện viên có được xem chi phí và báo cáo không? | Không. Tình nguyện viên xem đợt, nguyên liệu, công việc và số suất; chi phí, lịch sử và báo cáo từ vai trò Người ghi nhận trở lên | Khi thử với bếp |
| OQ-11 | Tình nguyện viên có được tự nhận việc chưa giao không? | Không; chỉ điều phối viên phân công | Nếu bếp muốn tự đăng ký việc |
| OQ-12 | Triển khai ở đâu (hosting chia sẻ, VPS, máy trong bếp)? | Chưa xác định; app chạy được không cần Node/build trên server | Trước khi viết hướng dẫn deploy chi tiết |
| OQ-13 | Hủy đợt thì công việc đang mở xử lý thế nào? | Giữ nguyên trạng thái, khóa thao tác cùng đợt; ghi số việc còn mở vào lịch sử | Khi thử luồng hủy |
| OQ-14 | Báo vướng có bắt buộc ghi lý do không? | Có, bắt buộc (tối đa 500 ký tự). Báo thiếu nguyên liệu: ghi chú tùy chọn | Khi thử với tình nguyện viên |
| OQ-15 | Mở lại đợt đã hủy thì về trạng thái nào? | Về “Nháp” để lên kế hoạch lại; đợt hoàn tất mở lại về “Đang diễn ra” | Khi thử luồng mở lại |

## Mâu thuẫn/điểm không rõ trong tài liệu và cách xử lý
- `DATA_MODEL.md` có một trường `result_notes`, còn WF-04/WF-06 cần cả “giải thích chênh lệch” lẫn “rút kinh nghiệm”. Đã tách thành `variance_note` và `completion_notes`.
- `UX_UI_GUIDELINES` yêu cầu thông báo đăng nhập không tiết lộ tài khoản có tồn tại nhưng phải có trạng thái “bị khóa”. Cách xử lý: chỉ báo “đã bị khóa” khi người dùng nhập **đúng mật khẩu**; sai mật khẩu luôn báo chung chung.
