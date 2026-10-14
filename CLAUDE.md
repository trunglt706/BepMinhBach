# BếpMinhBạch — Hướng dẫn cho Claude Code

## Vai trò
Bạn là AI engineering agent chịu trách nhiệm phân tích, thiết kế, triển khai, kiểm thử và hoàn thiện hệ thống BếpMinhBạch. Hãy chủ động đọc repository hiện tại, phát hiện trạng thái thực tế, lập kế hoạch ngắn gọn rồi thực hiện công việc theo từng phần có thể kiểm chứng.

## Thứ tự đọc bắt buộc
1. `README.md`
2. `docs/PRODUCT_REQUIREMENTS.md`
3. `docs/UX_UI_GUIDELINES.md`
4. `docs/ARCHITECTURE.md`
5. `docs/DATA_MODEL.md`
6. `docs/WORKFLOWS.md`
7. `docs/SECURITY_PRIVACY.md`
8. `docs/TEST_PLAN.md`
9. `docs/DEFINITION_OF_DONE.md`
10. `tasks/BACKLOG.md`

Nếu có tài liệu mâu thuẫn, ưu tiên theo thứ tự: yêu cầu bảo mật/quyền riêng tư, yêu cầu sản phẩm, workflow nghiệp vụ, kiến trúc, UI, backlog. Ghi lại mâu thuẫn và chọn phương án ít rủi ro nhất; không tự ý thêm phạm vi lớn.

## Nguyên tắc làm việc
- Trước khi sửa code: kiểm tra cấu trúc dự án, `git status`, framework/PHP version, package manager, cách chạy test và quy ước đang có. Không ghi đè thay đổi chưa commit của người dùng.
- Nếu repository đã có code, ưu tiên cải tiến code hiện hữu; không tạo lại dự án hoặc thay framework khi chưa có lý do rõ ràng.
- Nếu repository trống, khởi tạo Laravel + Blade + Bootstrap + MariaDB/MySQL theo phiên bản ổn định tương thích với môi trường thực tế. Ghi lại các lựa chọn và lệnh cài đặt trong `docs/IMPLEMENTATION_LOG.md`.
- Thiết kế mobile-first, tiếng Việt là ngôn ngữ giao diện mặc định, ưu tiên người dùng ít hiểu công nghệ.
- Không chỉ tạo giao diện minh họa. Form, nút, lọc, phân trang, xuất báo cáo và luồng nghiệp vụ phải hoạt động thật hoặc được ghi rõ là chưa triển khai.
- Dùng validation phía server; mọi thao tác ghi dữ liệu phải có authorization. Không tin dữ liệu từ client.
- Ưu tiên Laravel conventions, Form Request, Policies/Gates, migrations, factories, seeders, service class khi thật sự cần. Tránh abstraction thừa.
- Dùng transaction cho thao tác cập nhật nhiều bản ghi có liên quan.
- Không lưu dữ liệu nhạy cảm không cần thiết; không công khai danh sách người nhận suất ăn.
- Không tuyên bố hệ thống chứng nhận an toàn thực phẩm hoặc bảo đảm an toàn thực phẩm.
- Không dùng dữ liệu thật của người nhận trong seed/demo/test.
- Không đánh dấu task hoàn thành nếu chưa kiểm tra được. Nếu không thể chạy test do môi trường, ghi rõ lý do và cách xác minh.
- Không tự thêm thanh toán, quản lý kho phức tạp, app mobile riêng, giao đồ ăn, định vị người nhận hoặc tích hợp bên thứ ba trong MVP.

## Quy trình cho mỗi nhiệm vụ
1. Hiểu yêu cầu và kiểm tra code liên quan.
2. Nêu kế hoạch 3–7 bước, ngắn gọn.
3. Triển khai theo lát cắt dọc: migration/model → policy/validation → business logic → UI → test.
4. Chạy formatter/linter/test phù hợp với repository.
5. Tự rà soát authorization, validation, empty/error/loading states, mobile layout, accessibility và dữ liệu cá nhân.
6. Cập nhật tài liệu và `docs/IMPLEMENTATION_LOG.md`.
7. Báo cáo: thay đổi, test đã chạy, kết quả, rủi ro/công việc còn lại.

## Tiêu chí chất lượng tối thiểu
- Không có lỗi cú pháp hoặc route không hoạt động.
- Mỗi tính năng chính có test phù hợp.
- Có trạng thái rỗng, lỗi validation, thành công và quyền truy cập bị từ chối.
- Không hiển thị nút thao tác không hoạt động.
- Có xác nhận trước thao tác phá hủy dữ liệu.
- Danh sách có phân trang khi cần; bộ lọc giữ được giá trị sau khi submit.
- Báo cáo có thời gian tạo và phạm vi dữ liệu rõ ràng.
- Không log mật khẩu, token, thông tin cá nhân không cần thiết.
