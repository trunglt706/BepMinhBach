# UX/UI Guidelines

## Nguyên tắc
- Mobile-first, giao diện tiếng Việt, câu chữ đời thường.
- Mỗi màn hình có một hành động chính dễ nhận biết.
- Hạn chế thuật ngữ kỹ thuật, tránh form dài không cần thiết.
- Các nút thao tác có nhãn rõ: “Đánh dấu hoàn thành”, “Báo thiếu nguyên liệu”, “Lưu đợt nấu”.
- Không chỉ dùng màu để truyền đạt trạng thái; luôn có chữ hoặc biểu tượng kèm theo.
- Mục tiêu chạm khoảng 44×44 px khi có thể.
- Có label nhìn thấy được cho mọi input; không chỉ dùng placeholder.
- Có trạng thái loading, empty, success, validation error và access denied.
- Hạn chế modal; dùng trang/form rõ ràng, modal chỉ cho tác vụ ngắn hoặc xác nhận.

## Điều hướng đề xuất
1. Tổng quan
2. Đợt nấu ăn
3. Công việc của tôi
4. Báo cáo
5. Quản lý tài khoản (chỉ Admin)

Trên điện thoại ưu tiên menu thu gọn, nhưng “Công việc của tôi” và các việc đang diễn ra phải dễ truy cập.

## Màn hình cần thiết
### A. Đăng nhập
- Email/tên đăng nhập và mật khẩu theo cấu hình thực tế.
- Thông báo lỗi thân thiện, không tiết lộ tài khoản có tồn tại hay không.
- Có trạng thái tài khoản bị khóa.

### B. Tổng quan
- Thẻ số liệu: đợt sắp tới, đợt đang diễn ra, việc chưa xong, tổng suất đã phân phát trong khoảng thời gian đang chọn.
- Danh sách đợt gần đây.
- Cảnh báo thiếu nguyên liệu/việc bị chặn.
- Khi chưa có dữ liệu: giải thích ngắn + CTA “Tạo đợt nấu đầu tiên”.

### C. Danh sách đợt nấu
- Tìm kiếm theo tên; lọc ngày/trạng thái.
- Mỗi dòng/card có tên, ngày giờ, trạng thái, suất dự kiến, tiến độ công việc.
- CTA “Tạo đợt nấu”.
- Phân trang khi dữ liệu lớn.

### D. Tạo/sửa đợt nấu
Chia nhóm:
1. Thông tin đợt: tên, ngày/giờ, địa điểm.
2. Kế hoạch: số suất dự kiến, người điều phối.
3. Ghi chú.
- Hiển thị lỗi cạnh trường tương ứng.
- Có nút lưu nháp và lưu kế hoạch nếu phù hợp workflow.
- Cảnh báo trước khi rời trang nếu có thay đổi chưa lưu, nếu kỹ thuật cho phép.

### E. Chi tiết đợt nấu
- Header: tên, thời gian, trạng thái, số suất dự kiến.
- Tab/section: Tổng quan, Nguyên liệu, Công việc, Kết quả, Báo cáo.
- Tiến độ checklist thể hiện cả số lượng và tỷ lệ; không chỉ dùng vòng tròn màu.
- Nút hoàn tất/hủy có xác nhận và mô tả hậu quả.

### F. Công việc của tôi
- Ưu tiên danh sách hôm nay và việc quá hạn.
- Mỗi việc: tên, tên đợt, hạn, trạng thái, hành động nhanh.
- “Đánh dấu hoàn thành” và “Báo đang bị vướng” không yêu cầu mở form dài.
- Cho phép ghi chú ngắn khi bị vướng.

### G. Nguyên liệu
- Tên, lượng dự kiến, đơn vị, lượng đã dùng (nếu ghi nhận), trạng thái cần bổ sung, ghi chú.
- Nút “Báo thiếu” rõ ràng.
- Không tự coi số lượng dự kiến là lượng có sẵn.

### H. Kết quả và chi phí
- Tách biệt số dự kiến, số đã chuẩn bị, số đã phân phát.
- Nếu có chênh lệch, hiển thị nhắc kiểm tra và ô giải thích.
- Chi phí thể hiện theo VND, có phân tách hàng nghìn.
- Tránh hiển thị tổng 0 như thể đã xác nhận nếu chưa có dữ liệu; phân biệt “chưa nhập” và “0 đồng”.

### I. Báo cáo
- Bộ lọc dễ dùng; nút “Xem báo cáo”, “Tải CSV”, “In”.
- Báo cáo có thời điểm tạo, khoảng thời gian và cảnh báo về số liệu tự ghi nhận.
- Dữ liệu trên điện thoại cần cuộn/đổi sang card hợp lý.

### J. Quản lý tài khoản
- Danh sách tài khoản, vai trò, trạng thái.
- Không cho hiển thị mật khẩu.
- Khóa/mở tài khoản có xác nhận.

## Responsive và accessibility
- Kiểm tra ở viewport 360 px, 768 px và desktop ≥1280 px.
- Không để bảng gây tràn ngang toàn trang; dùng card hoặc vùng cuộn có chủ đích.
- Đảm bảo focus keyboard, thứ tự tab hợp lý, tương phản đủ, thông báo lỗi liên kết với input.
- Trạng thái và thao tác có text, không dựa hoàn toàn vào màu.

## UI acceptance checklist
- [ ] Người mới hiểu việc cần làm mà không đọc hướng dẫn dài.
- [ ] Các hành động chính dùng ngôn ngữ đơn giản.
- [ ] Có empty/error/success state.
- [ ] Form hiển thị validation gần trường lỗi.
- [ ] Không có nút giả hoặc liên kết chết.
- [ ] Trên màn hình nhỏ không cần zoom để thao tác.
- [ ] Bản in không chứa menu/nút giao diện.

## Design system đã triển khai (2026-10-14)
Nguồn: `public/css/app.css` (ghi đè biến CSS của Bootstrap 5.3, không cần build) và `resources/views/components/`.

- **Màu:** xanh lá đậm `#1b6343` (hành động chính, thương hiệu), cam ấm `#c96f1a` (điểm nhấn, số việc đang mở), đỏ `#b42318` (vướng/thiếu/xóa), nền giấy ấm `#f6f4ef`, viền `#e7e2d8`. Nhãn trạng thái luôn có chữ + biểu tượng.
- **Chữ:** Be Vietnam Pro 400–700, tự host trong `public/vendor/be-vietnam-pro` (hiển thị dấu tiếng Việt chuẩn, không phụ thuộc Google Fonts).
- **Khung:** desktop ≥ 992px dùng sidebar trái; điện thoại dùng thanh trên (thương hiệu + menu tài khoản) và thanh điều hướng dưới có số việc đang mở ở mục “Việc của tôi”.
- **Mỗi trang một hành động chính:** nút xanh đặc. Trang đợt nấu chỉ hiện một nút “bước tiếp theo” (Chốt kế hoạch → Bắt đầu nấu → Hoàn tất đợt → Mở lại đợt); thao tác phụ gom vào nút “⋯”, sửa/xóa từng dòng gom vào nút “⋮”.
- **Form:** chia phần có đánh số; thanh nút Lưu dính đáy màn hình trên điện thoại; độ rộng tối đa 760px.
- **Component:** `page-header`, `stat`, `date-tile`, `avatar`, `status-badge`, `task-status`, `batch-card`, `batch-stepper`, `row-menu`, `empty-state`, `input`/`select`/`textarea`.
- **Danh sách trên điện thoại:** thẻ thay cho bảng (báo cáo); bảng chỉ dùng từ 768px trở lên và khi in.
- **Trạng thái tải:** thanh tiến trình đầu trang khi chuyển trang; skeleton cho bảng/danh sách khi lọc hoặc phân trang (khai báo bằng `data-skeleton="table|cards|list"`); nút gửi hiện spinner + chữ theo ngữ cảnh (`data-loading-text`) và bị khóa để tránh gửi hai lần. Link tải file phải có `download data-no-loading`.
