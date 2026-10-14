# Claude workspace notes

`CLAUDE.md` ở root là nguồn chỉ dẫn chính. Khi Claude Code hỗ trợ project-level rules, giữ nguyên hướng dẫn ở root và không tạo rules mâu thuẫn.

Agent không được tự động deploy, chạy destructive database commands trên môi trường không xác định, hoặc commit/push thay đổi nếu chưa được yêu cầu rõ.
