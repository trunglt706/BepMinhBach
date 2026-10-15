# Prompt khởi động cho Claude Code

Dán prompt này khi mở repository trong Claude Code:

> Hãy đọc `CLAUDE.md` và toàn bộ tài liệu trong `docs/`, sau đó khảo sát repository hiện tại và `git status`. Đừng ghi đè thay đổi của tôi.
>
> Nhiệm vụ:
> 1. Đối chiếu code hiện có với yêu cầu Góp Gió.
> 2. Cập nhật `tasks/BACKLOG.md` thành kế hoạch triển khai thực tế, đánh dấu rõ task đã có, thiếu hoặc cần sửa.
> 3. Tạo danh sách giả định/mâu thuẫn nghiệp vụ cần xác nhận trong `docs/OPEN_QUESTIONS.md`; không dừng toàn bộ tiến trình vì câu hỏi không chặn MVP.
> 4. Bắt đầu task P0 ưu tiên cao nhất có thể hoàn thành an toàn.
> 5. Triển khai tính năng theo chiều dọc: database, validation, authorization, nghiệp vụ, UI và tests.
> 6. Chạy test phù hợp; không tuyên bố test đã pass nếu chưa chạy.
> 7. Cập nhật `docs/IMPLEMENTATION_LOG.md` và backlog.
>
> Quy tắc: Laravel + Blade + Bootstrap + MariaDB/MySQL theo môi trường thực tế; mobile-first; tiếng Việt dễ hiểu; không thu thập dữ liệu người nhận; không thêm payment, inventory ERP, app mobile riêng hoặc delivery. Hãy làm việc chủ động, kiểm chứng từng bước và báo cáo rõ các hạn chế.

## Prompt tiếp tục
> Tiếp tục task chưa hoàn thành có độ ưu tiên cao nhất trong `tasks/BACKLOG.md`. Đọc log gần nhất, kiểm tra trạng thái code, thực hiện thay đổi nhỏ có thể kiểm thử, chạy test và cập nhật tài liệu. Không lặp lại task đã hoàn tất nếu không phát hiện lỗi.
