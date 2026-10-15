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
| OQ-09 | Thông tin kết nối MySQL/MariaDB | **Đã giải quyết:** người dùng cấu hình MySQL `gopgio` trong `.env`; migration và bộ test đã chạy trên MySQL 8.4 | — |
| OQ-10 | Tình nguyện viên có được xem chi phí và báo cáo không? | Không. Tình nguyện viên xem đợt, nguyên liệu, công việc và số suất; chi phí, lịch sử và báo cáo từ vai trò Người ghi nhận trở lên | Khi thử với bếp |
| OQ-11 | Tình nguyện viên có được tự nhận việc chưa giao không? | Không; chỉ điều phối viên phân công | Nếu bếp muốn tự đăng ký việc |
| OQ-12 | Triển khai ở đâu (hosting chia sẻ, VPS, máy trong bếp)? | **VPS** (theo yêu cầu 14/10/2026): `deploy/deploy.sh` một lệnh cho Ubuntu 22.04/24.04, Debian 12. Chưa chạy trên VPS thật | Khi có VPS: chạy script, kiểm tra theo mục 9 của `docs/DEPLOYMENT.md` |
| OQ-13 | Hủy đợt thì công việc đang mở xử lý thế nào? | Giữ nguyên trạng thái, khóa thao tác cùng đợt; ghi số việc còn mở vào lịch sử | Khi thử luồng hủy |
| OQ-14 | Báo vướng có bắt buộc ghi lý do không? | Có, bắt buộc (tối đa 500 ký tự). Báo thiếu nguyên liệu: ghi chú tùy chọn | Khi thử với tình nguyện viên |
| OQ-15 | Mở lại đợt đã hủy thì về trạng thái nào? | Về “Nháp” để lên kế hoạch lại; đợt hoàn tất mở lại về “Đang diễn ra” | Khi thử luồng mở lại |

## Mâu thuẫn/điểm không rõ trong tài liệu và cách xử lý
- `DATA_MODEL.md` có một trường `result_notes`, còn WF-04/WF-06 cần cả “giải thích chênh lệch” lẫn “rút kinh nghiệm”. Đã tách thành `variance_note` và `completion_notes`.
- `UX_UI_GUIDELINES` yêu cầu thông báo đăng nhập không tiết lộ tài khoản có tồn tại nhưng phải có trạng thái “bị khóa”. Cách xử lý: chỉ báo “đã bị khóa” khi người dùng nhập **đúng mật khẩu**; sai mật khẩu luôn báo chung chung.
| OQ-16 | Có hiện tên tác giả bài viết trên trang công khai không? | **Có** (yêu cầu 14/10/2026). Tên lấy từ tài khoản nội bộ của người viết; JSON-LD vẫn ghi tác giả là tổ chức | Nếu tình nguyện viên không muốn lộ tên: thêm "tên hiển thị" riêng |
| OQ-17 | Đợt nấu có cần giờ bắt đầu không? | **Đã chốt (14/10/2026): chỉ ngày.** Cột đổi thành `starts_on` kiểu DATE; form dùng ô chọn ngày; CSV/bản in/báo cáo chỉ ghi ngày. Giờ cụ thể đặt ở hạn của từng công việc | — |
| OQ-18 | Ai đọc và xử lý liên hệ từ khách? Giữ liên hệ bao lâu? | Điều phối viên trở lên; xóa thủ công sau khi xử lý (chưa tự xóa theo hạn) | Trước khi mở trang liên hệ cho công chúng |
| OQ-19 | Tích hợp bên thứ ba (CLAUDE.md: không tự thêm trong MVP) | **Theo yêu cầu người vận hành (14/10/2026):** kênh thông báo Slack/Telegram/Chatwork cho từng đợt. Chỉ gửi ra địa chỉ cố định của từng dịch vụ; không gửi chi phí/ghi chú tự do | Nếu cần thêm kênh khác (Zalo, email): thêm loại trong `NotificationDriver` |
| OQ-20 | Ai được thấy link tham gia kênh thông báo? | Mọi tài khoản xem được đợt (kể cả tình nguyện viên); kênh đang tắt chỉ người quản lý thấy. Link không hiện trên trang công khai | Nếu kênh chỉ dành cho điều phối viên: thêm tùy chọn ẩn link |
