# Definition of Done

Một tính năng chỉ được xem là hoàn tất khi đáp ứng các điều kiện phù hợp:

## Nghiệp vụ
- [ ] Đúng yêu cầu và workflow trong tài liệu.
- [ ] Có xử lý dữ liệu hợp lệ, dữ liệu thiếu và dữ liệu sai.
- [ ] Có authorization phía server.
- [ ] Có xác nhận trước hành động khó đảo ngược.
- [ ] Không tự suy diễn hoặc sửa số liệu thực tế.

## Giao diện
- [ ] Tiếng Việt dễ hiểu, nhất quán.
- [ ] Mobile-first; kiểm tra 360px, 768px, desktop.
- [ ] Có empty, loading (nếu cần), success và error state.
- [ ] Label, validation và focus keyboard hợp lý.
- [ ] Không có nút/link giả.
- [ ] Bản in được tối ưu nếu tính năng có print.

## Kỹ thuật
- [ ] Migration/index/foreign key phù hợp.
- [ ] Validation phía server.
- [ ] Tránh N+1 và query không giới hạn.
- [ ] Không để secret hoặc dữ liệu thật trong source/demo.
- [ ] Xử lý transaction khi cập nhật nhiều bản ghi liên quan.
- [ ] Có test phù hợp.

## Tài liệu và xác minh
- [ ] README/setup được cập nhật khi cần.
- [ ] `docs/IMPLEMENTATION_LOG.md` cập nhật.
- [ ] Test đã chạy và kết quả được ghi chính xác.
- [ ] Nêu rõ rủi ro, quyết định chưa xác nhận và việc còn lại.
