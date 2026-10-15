# Implementation Log

Claude cập nhật file này sau mỗi lát cắt có ý nghĩa. Không ghi secret, token, mật khẩu hoặc dữ liệu cá nhân thật.

## Nhật ký

### 2026-10-14 — BM-001 — Khảo sát repository
- **Khảo sát:** thư mục chỉ có `CLAUDE.md`, `README.md`, `docs/`, `tasks/`, `prompts/`, `.claude/`. Không phải git repository (`git status` báo lỗi), không có code để bảo toàn.
- **Môi trường:** Windows 11 + Laragon. PHP trên PATH là 8.2.12 (XAMPP, thiếu ext `zip`); PHP của Laragon 8.3.30 (đủ `pdo_mysql`, `pdo_sqlite`, `intl`, `mbstring`, `zip`). Composer 2.9.8, Node 22.18, npm 10.9. MySQL 8.4.3 (Laragon) đang chạy nhưng `root` không đăng nhập được từ môi trường agent (cần mật khẩu) → không đoán mật khẩu, ghi OQ-09.
- **Quyết định:** dùng PHP 8.3 của Laragon cho các lệnh; chọn Laravel 12 (yêu cầu PHP ≥ 8.2, chạy được trên cả hai bản PHP có trên máy).

### 2026-10-14 — BM-002/003/004/005/006 — Nền tảng
- **Lệnh cài đặt:**
  - `composer create-project laravel/laravel:^12.0 _app` rồi chuyển file vào thư mục gốc, không ghi đè `README.md`/`docs` có sẵn (README mặc định của Laravel bị bỏ).
  - `npm install` (bootstrap 5.3.8, bootstrap-icons 1.13.1) và `npm run assets` để chép CSS/JS/font vào `public/vendor/`. Đã bỏ Vite/Tailwind mặc định: server **không cần Node hoặc bước build**, không phụ thuộc CDN.
- **Cấu hình:** `APP_LOCALE=vi`, `APP_TIMEZONE=Asia/Ho_Chi_Minh` (Việt Nam không có giờ mùa hè nên lưu giờ địa phương an toàn, input `datetime-local` khớp trực tiếp), `.env.example` mặc định MySQL; `.env` local dùng SQLite.
- **Thay đổi:** enums `Role`, `BatchStatus`, `TaskStatus`; middleware `EnsureUserIsActive` (đăng xuất ngay tài khoản bị khóa); `LoginController` (rate limit 5 lần/email+IP, thông báo không tiết lộ tài khoản); đổi mật khẩu; quản lý tài khoản cho Admin (tạo/sửa/khóa, không xóa, không tự khóa/tự hạ quyền); lệnh `php artisan bmb:create-admin`; layout Blade mobile-first với thanh điều hướng dưới; components form có label, `aria-describedby`, lỗi cạnh trường; trang lỗi 403/404/419/429/500/503 tiếng Việt; `lang/vi`.
- **Quyết định:** một cột `role` với 4 vai trò phân cấp thay vì bảng permission (đủ cho MVP, ít cấu hình). Seed demo chỉ chạy ở `local`/`testing`.

### 2026-10-14 — BM-010…013 — Đợt nấu ăn
- **Thay đổi:** migration `cooking_batches`; `CookingBatchController` (CRUD, in checklist), `BatchStatusController` (chuyển trạng thái, hoàn tất/hủy/mở lại có trang xác nhận + checkbox bắt buộc), `CookingBatchPolicy`, `CookingBatchRequest`, `App\Support\BatchFilter` (dùng chung cho danh sách và báo cáo), `DashboardController`.
- **Quy tắc:** đợt `completed`/`cancelled` bị khóa với mọi cập nhật thông thường; mở lại bắt buộc ghi lý do. Chỉ xóa cứng được đợt nháp chưa có dữ liệu. Số tiền nhận “1.500.000”. Bộ lọc bỏ qua giá trị không hợp lệ thay vì báo lỗi.

### 2026-10-14 — BM-020…024 — Checklist
- **Thay đổi:** migrations `ingredients`, `tasks`; controllers/policies/requests tương ứng; `MyTaskController`; partial `tasks/_item` với thao tác nhanh (Đánh dấu hoàn thành / Bắt đầu làm / Báo đang bị vướng trong `<details>`, không cần JS).
- **Quy tắc:** điều phối viên quản lý nguyên liệu/công việc; người được giao (hoặc điều phối viên) cập nhật trạng thái; báo vướng bắt buộc lý do; hoàn thành ghi `completed_by/at`. Mọi tài khoản đang hoạt động báo thiếu nguyên liệu được; gỡ cảnh báo: điều phối viên hoặc người đã báo. Đơn vị chọn từ danh sách hoặc tự nhập (≤ 30 ký tự), không quy đổi.

### 2026-10-14 — BM-030…034 — Kết quả và chi phí
- **Thay đổi:** `BatchResultController` (khóa dòng + `results_version` để phát hiện hai người sửa cùng lúc), `ExpenseItemController`, migration `expense_items`, `activity_logs` (chỉ lưu tóm tắt an toàn), `CookingBatch::servingVariances()`.
- **Quy tắc:** số suất là số nguyên không âm hoặc để trống (“chưa nhập” ≠ 0); chênh lệch chỉ hiển thị nhắc, không tự sửa. Tổng chi phí hiện “Chưa nhập” khi chưa có khoản nào.

### 2026-10-14 — BM-040…045 — Báo cáo
- **Thay đổi:** `ReportController` (tổng hợp trên toàn phạm vi lọc, danh sách phân trang, báo cáo từng đợt), `App\Support\Csv` (UTF-8 BOM, thêm `'` trước giá trị bắt đầu bằng `= + - @ tab CR LF`), view in A4 `batches/print`.
- **Quyết định:** CSV có vài dòng thông tin (thời điểm tạo, phạm vi, lưu ý) ở đầu rồi mới tới dòng tiêu đề cột — ưu tiên người đọc bằng Excel. PDF hoãn (OQ-05).

### 2026-10-14 — BM-052 — Kiểm thử
- **Lệnh:** `php artisan test` (PHP 8.3.30, SQLite in-memory theo `phpunit.xml`).
- **Kết quả:** lần chạy đầu 77 pass / 1 fail (sai ở assertion của test về cách quote CSV, output đúng) → sửa test → **78 passed, 443 assertions**. `vendor/bin/pint` đã định dạng 1 file (`bootstrap/app.php`); `pint --test` sạch.
- **Bao phủ TEST_PLAN:** T01–T29 có test tự động (Feature + Unit). T30 (viewport) chưa xác minh — xem bên dưới.
- **Kiểm tra trình duyệt:** `php artisan migrate:fresh --seed` + `php artisan serve`, đăng nhập bằng tài khoản demo điều phối viên; trang tổng quan (desktop) và chi tiết đợt nấu hiển thị đúng dữ liệu demo, cảnh báo thiếu nguyên liệu/việc bị vướng. **Không kiểm tra được 360px/768px**: cửa sổ trình duyệt tự động không thu nhỏ theo yêu cầu và ảnh chụp màn hình bị trả về ảnh cũ, nên dừng thay vì thử lại nhiều lần.

### Còn lại / rủi ro
- Chưa chạy migration trên MySQL/MariaDB (OQ-09). Các truy vấn đặc thù (`orderByRaw('due_at is null')`, `case when`) là SQL chuẩn, nhưng cần xác minh: tạo DB rồi chạy `php artisan migrate:fresh --seed` và `php artisan test` với `DB_CONNECTION=mysql`.
- Cần kiểm tra UX bằng mắt trên điện thoại thật (BM-050).
- Chưa có git repository; người dùng tự quyết định `git init`/commit.

### 2026-10-14 — BM-050 — Thiết kế lại layout và giao diện
- **Bối cảnh:** người dùng đã cấu hình MySQL, kiểm tra trên điện thoại và xác nhận các mặc định trong OPEN_QUESTIONS; yêu cầu cải thiện layout và giao diện toàn bộ.
- **Vấn đề phát hiện:** giao diện Bootstrap mặc định, chưa có nhận diện riêng; thanh điều hướng ngang lãng phí chiều rộng desktop, mobile bị trùng menu thu gọn + thanh dưới; trang đợt nấu có 7–8 nút cùng mức ưu tiên và chỉ một cột; mỗi công việc có tới 5 thao tác dạng chữ; nút Lưu nằm cuối form dài; bảng báo cáo phải cuộn ngang trên điện thoại.
- **Thay đổi:**
  - Design tokens trong `public/css/app.css` (màu, bo góc, bóng, trạng thái); font Be Vietnam Pro tự host (`npm run assets` tạo `public/vendor/be-vietnam-pro/font.css`, chỉ woff2 400–700, ~232 KB); favicon SVG.
  - Khung mới `components/layouts/app.blade.php`: sidebar desktop, topbar + bottom nav mobile, số việc đang mở trên menu (view composer trong `AppServiceProvider`).
  - Component mới: `page-header`, `stat`, `date-tile`, `avatar`, `task-status`, `batch-card`, `batch-stepper`, `row-menu`; làm lại `empty-state`, `status-badge`, `task-progress`. Enum `badgeClass()` trả về class `status-pill`.
  - Làm lại mọi trang: đăng nhập (chia đôi, nút hiện mật khẩu), tổng quan (số liệu nhanh + chọn khoảng thời gian dạng phân đoạn, “Cần xử lý” dạng liên kết), danh sách đợt (chip lọc trạng thái, thẻ có ô ngày), chi tiết đợt (một nút “bước tiếp theo”, thanh tiến trình trạng thái, 4 ô số liệu, 2 cột trên desktop, mục lục dính trên điện thoại), dòng công việc/nguyên liệu (biểu tượng trạng thái, thao tác chính rõ ràng, menu ⋮), các form (chia phần đánh số, thanh Lưu dính đáy), báo cáo (thẻ trên điện thoại), quản lý tài khoản (avatar, chọn vai trò dạng thẻ), trang lỗi.
  - Biểu mẫu báo vướng/báo thiếu vẫn nằm trong `<details>` nên dùng được khi tắt JavaScript.
- **Kiểm thử:**
  - `php artisan test` (SQLite): **83 passed, 478 assertions**; sửa 2 assertion gắn với markup cũ (số lượng nguyên liệu, chip trạng thái), thêm `tests/Feature/UiLayoutTest.php` (5 test: nút bước tiếp theo theo trạng thái, ẩn thao tác quản lý với tình nguyện viên, số đếm trên menu, form tài khoản, chọn khoảng thời gian).
  - Chạy cùng bộ test trên **MySQL 8.4** với DB riêng `gopgio_test` (`DB_CONNECTION=mysql DB_DATABASE=gopgio_test php artisan test`): **83 passed** → BM-003 đã xác minh trên MySQL.
  - Smoke test HTTP với `php artisan serve` trên DB MySQL thật (chỉ đọc): đăng nhập điều phối viên/người ghi nhận/tình nguyện viên, 28 URL trả 200; 403 đúng ở đợt đã hoàn tất và trang không đủ quyền; 404 ở URL không tồn tại. Tài khoản admin demo không đăng nhập được bằng mật khẩu demo (có thể đã được đổi) — không kiểm tra lại admin trên DB thật, trang admin được kiểm tra bằng feature test.
  - `vendor/bin/pint --test` sạch.
- **Chưa kiểm tra được:** hiển thị trực quan (extension Chrome không kết nối trong phiên này). Cần xem lại trên điện thoại thật ở 360px/768px và desktop.

### 2026-10-14 — BM-050 — Liên kết trong bảng, skeleton, loader chuyển trang, trạng thái đang gửi
- **Thay đổi:**
  - `public/css/app.css`: liên kết trong `.table` không gạch chân (chỉ đổi màu khi rê/focus); style cho thanh tiến trình đầu trang, lớp phủ, spinner trong nút, skeleton (tắt hiệu ứng khi người dùng chọn giảm chuyển động); ô số liệu `compact` cho số tiền.
  - `public/js/app.js` (viết lại):
    - **Loader chuyển trang:** thanh tiến trình hiện sau 120ms; lớp phủ “Đang tải…/Đang gửi…” chỉ hiện nếu chờ quá 1 giây. Bỏ qua link `download`/`data-no-loading`, tab mới, Ctrl/Cmd+click, link `#` trong trang, link ngoài site, và khi form có thay đổi chưa lưu (để trình duyệt tự hỏi). Tự tắt sau 20 giây nếu trang không chuyển.
    - **Skeleton:** khi gửi form lọc (`role="search"`), bấm chip trạng thái hoặc phân trang, các vùng `data-skeleton="table|cards|list"` được thay bằng khung xám cùng bố cục (bảng giữ tiêu đề cột, số dòng theo dữ liệu hiện tại, 3–8 dòng). Khi đã có skeleton thì không hiện lớp phủ.
    - **Gửi form:** nút được bấm hiện spinner + chữ theo ngữ cảnh (`data-loading-text`, mặc định Đang lưu…/Đang xóa…/Đang tải…), giữ nguyên chiều rộng; mọi nút gửi của form bị khóa; chặn gửi lần hai; `aria-busy` + thông báo cho trình đọc màn hình.
    - Khôi phục toàn bộ trạng thái khi quay lại bằng nút Back (sự kiện `pageshow` từ bộ nhớ đệm).
  - View: gắn `data-skeleton` cho bảng + thẻ báo cáo, lưới đợt nấu, danh sách tài khoản; link CSV có `download data-no-loading`; trang đăng nhập nạp `app.js`; cache-busting `?v=filemtime` cho `app.css`/`app.js`.
  - Sửa layout phát hiện khi kiểm tra: nút “Xem báo cáo” bị gãy 3 dòng (cột `col-md-1` quá hẹp → chia lại lưới bộ lọc), số tiền “2.270.000 đ” bị xuống dòng trong ô số liệu.
- **Quyết định:** không hiện skeleton khi mở trang lần đầu. Trang render phía server nên dữ liệu đến cùng HTML; skeleton lúc đó chỉ là độ trễ giả. Skeleton dùng khi người dùng đang chờ dữ liệu mới (lọc/phân trang).
- **Kiểm thử:**
  - `node --check public/js/app.js` OK.
  - Trên Chrome (phiên đăng nhập sẵn có, chỉ mô phỏng, không gửi dữ liệu): skeleton bảng 4 dòng giữ tiêu đề, skeleton thẻ ở danh sách đợt; nút “Đang tìm…”/“Đang lưu…” + khóa nút phụ; chặn gửi lần hai; khôi phục sau Back; link CSV không bật loader, link thường có; form chưa lưu không bật loader; ảnh chụp xác nhận layout bộ lọc và ô số tiền đã sửa.
  - `php artisan test`: **85 passed** trên SQLite và trên MySQL `gopgio_test` (thêm 2 test markup trong `UiLayoutTest`). Pint sạch.
- **Chưa kiểm tra:** hiệu ứng trên điện thoại thật và mạng chậm thật (đã mô phỏng bằng cách chặn điều hướng).

### 2026-10-14 — BM-050 — Ô tiền tệ, bỏ gạch chân liên kết, giảm chuyển trang (modal + AJAX)
- **Bối cảnh:** yêu cầu (1) ô số tiền hiển thị định dạng tiền tệ, backend tự bỏ ký tự phân cách; (2) bỏ gạch chân mọi `a[href]`; (3) ít redirect nhất, dùng modal/AJAX cập nhật dữ liệu.
- **Đã có từ phiên trước (chưa ghi log, đã rà soát và giữ nguyên):** `app.js` có modal tải form bằng `?modal=1`, gửi form `data-ajax` bằng fetch, lỗi 422 hiện ngay trong modal, 409 tải lại form, 401/419 tải lại trang, cập nhật vùng `data-region` thay vì tải lại trang, hộp xác nhận modal thay `window.confirm`; ô tiền `data-money` (chỉ nhận chữ số, tự thêm dấu chấm, bỏ dấu trước khi gửi); `Controller::done()` trả JSON khi `expectsJson()`, redirect khi JS tắt; `Input::money()` chuẩn hóa "1.500.000", "1,500,000 đ", "1 500 000" ở FormRequest; `tests/Feature/AjaxFlowTest.php`. Áp dụng cho nguyên liệu, công việc, khoản chi, số suất, hoàn tất/hủy/mở lại đợt, đổi trạng thái, báo thiếu, khóa tài khoản.
- **Thay đổi trong phiên này:**
  - CSS: `a[href]` không gạch chân ở mọi nơi (thay quy tắc chỉ áp cho `.table a`); rê chuột đổi màu, viền focus rõ khi dùng bàn phím; liên kết trong đoạn văn/thông báo đậm hơn để không lẫn với chữ thường.
  - Chuyển sang modal + AJAX các form còn lại: tạo/sửa đợt nấu, tạo/sửa tài khoản, đổi mật khẩu (dùng `x-form-shell`, thêm tùy chọn `bare` cho form tự chia thẻ và slot `after` cho nội dung ngoài form ở trang đầy đủ). CSS bỏ khung thẻ lồng nhau khi form nhiều phần nằm trong modal.
  - Xóa đợt nháp: thêm vào menu ⋯ trang đợt nấu (AJAX + hộp xác nhận); trang sửa đầy đủ (không JS) vẫn giữ vùng xóa. Không lồng form xóa trong modal.
  - `done()`: khi có `redirect` (vừa tạo đợt mới / vừa xóa đợt) thì flash thông báo để trang đích hiển thị; `refresh: false` để JS không tải lại vùng dữ liệu (đổi mật khẩu).
- **Còn chuyển trang (có chủ đích):** tạo đợt mới → mở trang đợt vừa tạo; xóa đợt → về danh sách (trang cũ không còn); lọc/phân trang/báo cáo (GET, giữ URL để chia sẻ/quay lại); đăng nhập/đăng xuất; khi JS tắt mọi form vẫn gửi và redirect như trước.
- **Kiểm thử:**
  - `php artisan test` (SQLite): **99 passed (622 assertions)**; thêm 3 test trong `AjaxFlowTest` (modal cho form dài + link `data-modal`, tạo/sửa/xóa đợt trả JSON kèm chuẩn hóa tiền "3.200.000"/"4.000.000 đ", tạo/sửa tài khoản + đổi mật khẩu trả JSON, 422 và 403).
  - `vendor/bin/pint --test` (PHP 8.3 của Laragon) sạch; `node --check public/js/app.js` OK.
  - Chrome trên `php artisan serve` (DB thật, **không gửi form**): modal “Tạo đợt nấu” mở không chuyển trang; gõ `25abc00000` vào ô chi phí → hiển thị `2.500.000` (chữ bị chặn); modal “Đổi mật khẩu” mở đúng; không còn liên kết gạch chân trên trang danh sách đợt.
- **Chưa kiểm tra:** gửi thật các form mới qua giao diện trên DB thật (chỉ kiểm tra bằng feature test để không ghi dữ liệu vào DB của người dùng); hiển thị modal trên điện thoại thật.

### 2026-10-14 — BM-050 — Padding footer modal, nút xem mật khẩu
- **Lỗi footer modal dính nút:** `--bs-modal-padding` được đặt 2 giá trị (`1.1rem 1.25rem`) nhưng Bootstrap tính padding footer bằng `calc(var(--bs-modal-padding) - …)` → calc không hợp lệ, padding = 0. Sửa: đặt `padding: .85rem 1.25rem` trực tiếp cho `.modal-footer` (đo trên Chrome: 13.6px/20px quanh nút).
- **Nút xem/ẩn mật khẩu:** `x-input type="password"` tự thêm nút con mắt trong `input-group` (`data-toggle-password`, `aria-pressed`, `aria-controls`, nhãn “Hiện/Ẩn mật khẩu”). Áp dụng cho đổi mật khẩu (3 ô), tạo/sửa tài khoản (2 ô); trang đăng nhập đã có sẵn. Ô mật khẩu không bao giờ điền sẵn giá trị. Khi gửi form, mật khẩu đang hiện được ẩn lại.
- **Kiểm thử:** `php artisan test` 99 passed (629 assertions; thêm kiểm tra nút xem mật khẩu và không có `value` ở ô mật khẩu); Pint sạch; `node --check` OK; Chrome: modal đổi mật khẩu có 3 nút, bấm → ô chuyển sang `text`, nhãn “Ẩn mật khẩu”.

### 2026-10-14 — BM-050 — Icon tải chậm, biểu đồ, màn chờ có logo
- **Icon bị giật — nguyên nhân:** `bootstrap-icons.woff2` chứa 2.000+ icon (131 KB) với `font-display: block` → icon vô hình tới khi tải xong cả file; trình duyệt chỉ bắt đầu tải sau khi đọc CSS; `php artisan serve` không gửi header cache.
- **Sửa:**
  - `scripts/build-icons.mjs` (dùng `subset-font` 2.9.0, devDependency): quét `resources/`, `app/`, `public/js/app.js`, chỉ giữ 75 icon đang dùng → **6,5 KB**; sinh `public/vendor/bootstrap-icons/bootstrap-icons.subset.css` + `fonts/bootstrap-icons-subset.woff2?v=<md5>`. Chạy `npm run icons` khi thêm icon mới (cũng chạy trong `npm run assets`). Test `ChartAndAssetTest` báo lỗi nếu có icon chưa nằm trong bộ rút gọn.
  - `partials/head-assets.blade.php` dùng chung cho layout app/guest/lỗi: preload font icon (URL trùng với CSS) + 2 font chữ chính; `.bi::before { width: 1em }` giữ chỗ để chữ bên cạnh không xô lệch.
  - `public/.htaccess`: cache font 1 năm (immutable), CSS/JS/SVG 7 ngày — có hiệu lực khi chạy qua Apache/Laragon, không áp dụng với `php artisan serve`.
- **Biểu đồ** (render phía server bằng HTML/CSS, không thêm thư viện JS; component `x-chart.columns|bars|stack`, `App\Support\Charts`):
  - Tổng quan (theo bộ chọn khoảng thời gian, bộ chọn nằm trên toàn bộ số liệu + biểu đồ; mục “Cần xử lý” chuyển lên trên cùng): số suất dự kiến vs đã phân phát theo ngày/tuần/tháng (chỉ đợt đã nhập số phân phát); đợt nấu theo trạng thái (gồm đợt sắp tới); chi phí thực tế theo thời gian — **chỉ người có quyền xem báo cáo**.
  - Chi tiết đợt: thanh xếp chồng công việc theo trạng thái (màu trạng thái + biểu tượng + tên + số); số suất dự kiến/chuẩn bị/phân phát dạng thanh (thay lưới 3 số; “Chưa nhập” khác 0); chi phí dự kiến vs thực tế + 5 khoản chi lớn nhất, còn lại gộp “Khác” (chỉ người xem được tài chính).
  - Màu đã kiểm tra bằng validator bảng màu của skill dataviz: CVD (deutan/protan) ΔE ≥ 13, normal-vision ≥ 17, tương phản ≥ 3:1 trên nền trắng; xám “dự kiến” cố ý trung tính (không phải màu chuỗi).
  - Tooltip khi rê chuột/focus bàn phím/chạm (dựng bằng `textContent`, tên khoản chi được escape); mỗi biểu đồ cột có “Xem dạng bảng”; nhãn trục rút gọn (K/Tr).
- **Màn chờ:** logo (SVG nội tuyến, không phụ thuộc font) ở giữa, vòng cung xoay quanh, chữ trạng thái bên dưới; tắt xoay khi người dùng chọn giảm chuyển động.
- **Kiểm thử:** `php artisan test` **106 passed (679 assertions)** — thêm `tests/Unit/ChartsTest.php` (3) và `tests/Feature/ChartAndAssetTest.php` (4: quyền xem chi phí, trạng thái rỗng, escape tên khoản chi, preload + đủ icon); sửa nhãn trong test T23 (“Thực tế đã nhập”). Pint sạch; `node --check` OK.
  - Chrome (440px, dữ liệu demo, chỉ xem): biểu đồ tổng quan 12 tháng, chi tiết đợt, màn chờ hiển thị đúng; font icon 7 KB tải song song với font chữ. **Phát hiện và sửa** khi xem: giá trị tiền dài (“2.000.000 đ”) tràn khỏi thẻ ở màn hẹp → thang thanh tính trên bề ngang trừ chỗ dành cho giá trị, nhãn nằm trên thanh với biểu đồ tiền dưới 576px.
- **Chưa kiểm tra:** desktop rộng (cửa sổ trình duyệt đang ở chế độ giả lập 440px); hiệu quả cache trên Apache thật.

### 2026-10-14 — BM-060/061 — Giới hạn tần suất, bài viết công khai
- **Giới hạn tần suất:** limiter `user-actions` (theo ID tài khoản; ghi 40/phút, đọc 300/phút) cho toàn bộ trang nội bộ, `public` (120/phút/IP) cho trang bài viết; cấu hình `config/bmb.php` + `BMB_RATE_*`. Phản hồi 429 tiếng Việt: JSON cho AJAX (app.js hiện trong form/toast), form thường quay lại với thông báo + dữ liệu đã nhập (trừ mật khẩu), trang GET hiện `errors/429` có thời gian chờ.
- **Bài viết:** bảng `posts`, `tags`, `post_tag`; `PostPolicy` (điều phối viên+); `PostController` (nội bộ `/posts`: lọc, viết/sửa trong modal, đăng/gỡ, xóa có xác nhận, ghi activity log); `PublicPostController` (`/bai-viet`, `/bai-viet/{slug}`; layout `components/layouts/public`). Tìm kiếm: mọi từ khóa phải có trong tiêu đề/tóm tắt/nội dung/tên thẻ (ký tự `%`/`_` được escape), lọc theo thẻ và đợt, bộ lọc giữ giá trị, phân trang 9. Trang chi tiết: khung "Thuộc đợt nấu" (chỉ số liệu tổng hợp), bài viết khác (ưu tiên cùng đợt). Menu "Công khai" trong sidebar, "Viết bài về đợt này" trên trang đợt, link từ trang đăng nhập. Seeder demo thêm 4 bài + 1 nháp (chỉ khi chưa có bài).
- **Quyết định (ít rủi ro nhất):** không hiện tên tác giả/người điều phối, địa điểm, chi phí trên trang công khai; nội dung là văn bản thuần; chưa có ảnh (BM-062).
- **Lỗi phát hiện nhờ test:** tìm kiếm nhiều từ khóa lỗi vì callback của `when()` trả về Collection (`each()`) và Laravel dùng giá trị đó làm query tiếp theo → sửa thành closure không trả về.
- **Sự cố khi thử:** lệnh thử migration với `--env=testing` (không có `.env.testing` nên đọc `.env`) đã tạo file SQLite thừa `./gopgio`; đã xóa. Không ảnh hưởng MySQL (`database/database.sqlite` có từ trước, không đụng tới).
- **Kiểm thử:** `php artisan test` **120 passed (798 assertions)** trên SQLite và trên MySQL 8.4 (`gopgio_test`); thêm `PostTest` (10) và `RateLimitTest` (4, chạy lặp 3 lần ổn định). Pint sạch; `node --check` OK; bộ icon rút gọn tạo lại (81 icon, 7 KB).
  - Chrome (DB dev, chỉ xem, không gửi form): danh sách 1280px không cuộn ngang; tìm "bánh" + thẻ #thông báo ra đúng 1 bài, bộ lọc giữ giá trị; trang chi tiết có khung đợt nấu đúng phạm vi công khai; trang `/posts` và modal "Viết bài mới" hiển thị đúng.
- **Chưa kiểm tra:** trang công khai trên điện thoại thật; gửi form viết bài qua giao diện trên DB thật.

### 2026-10-14 — BM-062/063 — Trình soạn thảo, ảnh bài viết, SEO, lượt xem, bộ nhớ đệm; rà soát mã
- **Trình soạn thảo:** Trix 2.1.15 (MIT), tải lười khi mở form; thanh công cụ tiếng Việt; chặn chèn ảnh/file vào nội dung. Không có JS: ô văn bản thường. Máy chủ lọc HTML bằng `symfony/html-sanitizer` 7.4 (allowlist, `App\Support\PostHtml`): bỏ script/style/class/on*/iframe/img, `<h1>`→`<h2>`, liên kết chỉ http/https/mailto + `rel="nofollow noopener noreferrer ugc"`. Lọc ngay khi gán `Post::body`; thêm `body_text` (chữ thuần) cho tìm kiếm/tóm tắt. Migration chuyển bài cũ (chữ thuần) sang `<p>`.
- **Ảnh:** ảnh đại diện + album (≤ 12, đếm cả ảnh đang có). FormRequest kiểm tra kiểu theo nội dung + dung lượng; `PostImageProcessor` giải mã (GD), xoay theo EXIF, giới hạn 40 MP, tạo WebP 480/960/1600 (không phóng to), tên ngẫu nhiên, không lưu file gốc; xử lý ảnh trước giao dịch, lỗi thì xóa file đã tạo; xóa ảnh/bài thì xóa file sau commit. Trình duyệt thu nhỏ ảnh > 2560px hoặc > 1,5 MB trước khi gửi (canvas → WebP). Form: xem trước, nút × bỏ ảnh mới chọn, nút × đánh dấu xóa ảnh đã lưu (có hoàn tác), chú thích ảnh. Disk `uploads` = `public/uploads` (+ `.htaccess` chặn chạy mã), git bỏ qua ảnh.
- **Ô nhập thẻ:** chip; Enter/Tab/dấu phẩy để thêm (Tab khi ô trống vẫn chuyển ô), Backspace xóa thẻ cuối, bỏ trùng không phân biệt hoa thường, gợi ý từ thẻ có sẵn (datalist), tối đa 10. Không JS: ô chữ ngăn cách dấu phẩy.
- **Trang công khai:** ảnh đầu bài `srcset/sizes` + `fetchpriority=high`; album dạng lưới (desktop) / **trượt ngang có snap** (điện thoại < 576px) + gợi ý vuốt; bấm ảnh mở PhotoSwipe 5.4.4 (MIT, tải lười, nhãn tiếng Việt, chú thích). **Không dùng Fancybox** vì Fancybox v5 cần giấy phép GPLv3/thương mại.
- **SEO:** canonical, robots (noindex cho trang tìm kiếm/lọc và bản xem trước), Open Graph (og:image WebP 1600 + kích thước), Twitter card, `article:*`, JSON-LD `BlogPosting` (tác giả là tổ chức), breadcrumb, `rel="tag"`, `/sitemap.xml`.
- **Lượt xem:** `PostViewCounter` — 1 lần / người xem / bài / 6 giờ (HMAC IP+UA trong cache, `Cache::add` nguyên tử), bỏ qua bot, bản nháp, người quản lý; tăng bằng query builder (không đổi `updated_at`, không làm mất cache).
- **Bộ nhớ đệm:** `PublicPostCache` (phiên bản + TTL 10 phút) cho danh sách/bộ lọc và quan hệ của trang chi tiết; tăng phiên bản sau commit khi Post/PostImage/Tag/CookingBatch thay đổi. Trang công khai có ETag (`Cache-Control: private`) → 304 khi không đổi.
- **Rà soát theo yêu cầu:**
  - PHPDoc đầy đủ cho mọi function trong `app/` (mô tả + `@param` + `@return` kiểu mảng/quan hệ); kiểm bằng script: 0 thiếu.
  - Mọi validation chuyển sang FormRequest: `LoginRequest`, `PasswordUpdateRequest`, `BatchResultRequest`, `BatchStatusRequest`, `BatchCompleteRequest`, `BatchCancelRequest`, `BatchReopenRequest`, `ShortageRequest`, `TaskStatusRequest`, `BatchFilterRequest` (thay `BatchFilter::fromRequest`), `DashboardRequest`, `UserIndexRequest`, `PostIndexRequest`, `PublicPostIndexRequest`.
  - Activity log: enum `ActivityAction` (giữ nguyên giá trị cũ), bỏ chữ tự do khỏi tóm tắt (lý do mở lại → cột `cooking_batches.reopen_reason`), tự che email/số điện thoại VN (không đụng số tiền).
  - Bỏ so sánh chuỗi cứng: `Rule::enum`/giá trị enum cho `save_as`, trạng thái công việc/đợt trong view, `Role::valuesAtLeast()`, `AccountState` enum (lọc tài khoản), `Ingredient::UNIT_OTHER`, `CookingBatch::VARIANCE_*`, `PostImage::COVER|GALLERY|EXTENSION`, `Charts::MODE_*`, mặc định `$attributes` dùng enum.
- **Lỗi phát hiện và sửa trong lúc làm:** `Rule::enum()->only([])` của Laravel nghĩa là "không giới hạn" → dùng `Rule::in` cho chuyển trạng thái; nút "Đính kèm" của Trix vẫn hiện (trix.css nạp sau) → tăng độ cụ thể selector; đầu trang công khai tràn ngang ở màn 320px → nút chỉ biểu tượng dưới 400px.
- **Sự cố:** một lần nữa lỡ chạy `migrate:fresh --database=sqlite` (định chỉ kiểm tra) tạo file `./gopgio`; đã xóa, không ảnh hưởng MySQL.
- **Kiểm thử:** `php artisan test` **139 passed (994 assertions)** trên SQLite và MySQL 8.4 (`gopgio_test`); thêm `PostMediaTest` (12), `PublicPostCacheTest` (4), `RequestNormalizationTest` (3). Pint sạch; `node --check` OK; bộ icon 85 icon.
  - E2E trên bản **cách ly** (SQLite trong scratchpad, `artisan serve` cổng 8001, cookie phiên riêng, tài khoản thử tạo riêng): tạo bài có ảnh bìa 3000×2000 (gửi lên 187 KB WebP sau khi thu nhỏ trên máy) + 2 ảnh album → 200, ảnh lưu đúng 3 kích thước; trang công khai dùng bản 480 ở màn hẹp, PhotoSwipe mở "1 / 2"; sửa bài: bỏ 1 ảnh mới bằng ×, chọn rồi bỏ ảnh bìa mới (trả lại ảnh cũ), đánh dấu xóa 1 ảnh cũ → DB và file khớp; album trượt ngang trên màn hẹp. Sau đó xóa đúng 9 file ảnh thử + DB tạm; 12 file ảnh của người dùng giữ nguyên.
- **Chưa kiểm tra:** trên điện thoại thật; hiệu quả `.htaccess` của `public/uploads` trên Apache thật; giao diện desktop rộng bằng mắt (cửa sổ trình duyệt hẹp).

### 2026-10-14 — BM-050/051/053/054 — Rà soát UX 3 cỡ màn hình, bảo mật production, hướng dẫn triển khai, nghiệm thu
- **Sự cố môi trường khi bắt đầu:** ổ C: đầy (0,17 GB trống) → `vendor/` thiếu `autoload.php`, `node_modules/` mất, `.env` được tạo lại không có `APP_KEY`. Sau khi ổ có chỗ trống: `composer install --prefer-dist` (PHP 8.3 Laragon), `npm ci`, `php artisan key:generate` (phiên đăng nhập cũ mất hiệu lực). `public/uploads/` của máy dev chỉ còn `.htaccess`.
- **Sửa test:** `ChartAndAssetTest` văng `ErrorException` khi thiếu `node_modules` thay vì bỏ qua như ý định → kiểm tra `is_file()` trước.
- **BM-051 — bảo mật cấu hình production:**
  - Middleware `SecurityHeaders` (gắn toàn cục): CSP `script-src 'self'` (không inline/eval), `img-src 'self' data: blob:`, `object-src 'none'`, `base-uri`/`form-action`/`frame-ancestors 'self'`; `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, `COOP`; HSTS chỉ khi HTTPS. Không ghi đè header do controller tự đặt.
  - `config/session.php`: `secure` mặc định bật khi `APP_URL` là https://.
  - `TRUSTED_PROXIES` (`config/bmb.php` → `TrustProxies::at()` trong `AppServiceProvider`; không dùng `env()` trong `bootstrap/app.php` vì không chạy sau `config:cache`).
  - Trang lỗi: bỏ `href="javascript:history.back()"` (bị CSP chặn) → URL trước đó nếu cùng site, ngược lại về trang chủ.
  - Lệnh `php artisan bmb:check-production` (chỉ đọc, không in bí mật): APP_ENV/DEBUG/KEY, HTTPS, cookie Secure/HttpOnly, LOG_LEVEL, quyền ghi `storage`/`bootstrap/cache`, `.htaccess` thư mục ảnh, cache cấu hình, kết nối DB, tài khoản demo còn hoạt động, có quản trị viên. Mã thoát 1 nếu có LỖI.
  - `tests/Feature/ProductionHardeningTest.php` (8 test), trong đó có test quét view tìm JS nội tuyến — test này đã phát hiện `javascript:` ở trang lỗi.
- **BM-050 — rà soát UX:** script Chrome headless qua DevTools Protocol (Node 22, WebSocket có sẵn; nằm trong scratchpad, không thêm vào repo) chạy trên **bản cách ly** (SQLite tạm + DemoSeeder, `artisan serve` cổng 8001, biến môi trường riêng). 360/768/1280px × 26 trang/trạng thái (khách: danh sách/chi tiết/tìm bài, đăng nhập; quản trị: tổng quan, đợt, chi tiết đợt các trạng thái, việc của tôi, báo cáo, tài khoản, bài viết, số suất, đổi mật khẩu, bản in, 404, modal tạo đợt, modal viết bài; tình nguyện viên: tổng quan, việc, đợt, 403). Đo: tràn ngang, phần tử vượt khung, nút bị cắt chữ, vùng chạm < 24px, lỗi JS/console/CSP; chụp ảnh từng trang.
  - Phát hiện và sửa: bảng checklist in tràn 65px ở 360px → bọc vùng cuộn ngang chỉ trên màn hình < 576px (bản in A4 không đổi); thanh công cụ Trix bị cắt ở 360px vì `trix.css` nạp sau thắng `flex-wrap` → tăng độ cụ thể selector; thẻ `#tag` cao 23px → `min-height: 28px`.
  - Báo động giả đã loại: nút "Bỏ qua điều hướng" (ẩn có chủ đích), link nhỏ trong sidebar trượt đang ẩn, tiêu đề thẻ bài viết (link phủ cả thẻ), link đứng riêng có đủ khoảng cách (ngoại lệ spacing của WCAG 2.5.8).
  - E2E luồng ảnh dưới CSP (390px, điều phối viên demo): chọn ảnh bìa 3000×2000 + 2 ảnh album → 3 ảnh xem trước `blob:` hiển thị → "Đăng bài" (AJAX) thành công → trang công khai dùng bản 480px → PhotoSwipe mở "1 / 2"; không lỗi. Xóa đúng 18 file ảnh thử (so danh sách file trước/sau), dừng server, không tạo file SQLite thừa trong repo.
- **BM-053:** `docs/DEPLOYMENT.md` (yêu cầu máy chủ, `.env` production, khởi tạo, cPanel, Nginx mẫu, quyền thư mục, sao lưu DB + `public/uploads`, khôi phục, cập nhật phiên bản, kiểm tra sau triển khai). README trỏ tới tài liệu này; `.env.example` thêm ghi chú production + `TRUSTED_PROXIES`. Chưa chạy thử trên hosting thật (OQ-12) → BM-053 vẫn `[-]`.
- **Tài liệu:** `SECURITY_PRIVACY.md` thêm mục header bảo mật, sửa câu đã lỗi thời "nội dung bài viết là văn bản thuần" (từ BM-062 là HTML đã lọc); `TEST_PLAN.md` thêm T31–T33; `OPEN_QUESTIONS.md` cập nhật OQ-12.
- **BM-054:** `docs/ACCEPTANCE_REPORT.md`.
- **Kiểm thử:** `php artisan test` **147 passed (1047 assertions)** trên SQLite và trên MySQL 8.4.3 (`gopgio_test`); `vendor/bin/pint --test` sạch; `bmb:check-production` trên máy dev báo đúng 3 lỗi của môi trường dev.
- **Lưu ý khi sửa file trên Windows:** script Python ghi CRLF → Pint báo `line_ending`; đã chuẩn hóa về LF.
- **Chưa kiểm tra:** điện thoại thật, hosting thật, header khi chạy qua Apache/Nginx thật (middleware chỉ áp dụng phản hồi PHP; file tĩnh dùng cấu hình máy chủ).

### 2026-10-14 — BM-064…070 — /admin, trang chủ bài viết, ảnh tải lười, liên hệ, slider đợt nấu, validate trình duyệt, deploy VPS
- **Route:** mọi trang cần đăng nhập nằm dưới `/admin` (test quét toàn bộ route có middleware `auth`). `/` = danh sách bài viết công khai; `/bai-viet` → 301 về `/` giữ query. Đã đăng nhập mà mở `/login` → `/admin`. Trang lỗi: nút về tổng quan (đã đăng nhập) hoặc trang chủ.
- **Ảnh:** component `x-post-img` — ảnh ưu tiên (ảnh đầu bài, slide đầu, thẻ đầu khi không có slider) có `src` + `fetchpriority=high`; ảnh khác giữ URL ở `data-src/srcset`, `app.js` (IntersectionObserver, vùng đệm 50px) chỉ gán khi vào khung nhìn, kể cả album/slider cuộn ngang; `<noscript>` bản thật cho SEO/không JS. Nền nhấp nháy khi chờ, hiện dần khi xong; ảnh lỗi ẩn để lộ nền. Bài/đợt chưa có ảnh: nền màu thương hiệu + biểu tượng. Rê chuột: phóng ảnh, phủ tối, biểu tượng phóng to ở album (tắt khi giảm chuyển động).
  - Đo trên Chrome (DOM, không dựa log mạng vì lẫn request trễ): 390px chỉ ảnh trong khung + 50px có `src`; ảnh ở 1515px chờ tới khi cuộn.
- **Liên hệ:** bảng `contact_messages`, `ContactRequest` (chuẩn hóa SĐT/email, bẫy bot, 1 liên hệ/ngày theo IP băm HOẶC email/SĐT), limiter `contact` (5/10 phút/IP, tính cả lần lỗi). Nội bộ `/admin/lien-he`: lọc trạng thái/chủ đề, tìm theo tên/email/SĐT (SĐT gõ có dấu cách vẫn khớp; LIKE dùng `ESCAPE '!'` để `%`/`_` là ký tự thường trên cả MySQL và SQLite — **phát hiện nhờ test**: `addcslashes` + `\` không có tác dụng trên SQLite), thống kê (tổng, chưa/đã xử lý, hôm nay, 7 ngày, theo chủ đề), đánh dấu xử lý, xóa có xác nhận, badge số chưa xử lý ở menu.
  - Header công khai: thêm "Liên hệ", bỏ nút "Quản lý"; footer chỉ còn bản quyền (ghi chú "số liệu do đội Góp Gió tự ghi nhận" chuyển vào khung số suất của trang đợt).
- **Đợt nấu công khai:** ảnh nền (form đợt, xử lý như ảnh bài viết, thư mục `batches/`, xóa file cũ sau commit); slider đầu trang chủ chỉ các đợt **đang diễn ra**, hiển thị **chỉ ngày** (OQ-17), nút trước/sau, chấm, tự chuyển 6 giây (dừng khi rê/focus/chạm/giảm chuyển động), tải trước ảnh slide kế. Trang `/dot-nau/{public_id}` (mã hex 16 ký tự ngẫu nhiên thay ID): thông tin + số suất ở trên, bài viết phân trang ở dưới; chỉ đợt đang diễn ra hoặc có bài đăng. Sitemap thêm trang đợt + liên hệ.
- **Trang chủ:** ô tìm trên header (máy tính hiện sẵn; điện thoại mở bằng `<details>`, không cần JS); bỏ form lọc + danh sách thẻ ở thân trang (lọc theo thẻ/đợt vẫn chạy qua link trên thẻ bài); 6 bài/trang; thẻ bài: ngày trái – tác giả phải (OQ-16), badge đợt nấu màu cam nổi trên ảnh.
- **Validate trình duyệt:** `app.js` chặn gửi ở pha capture (trước AJAX/xác nhận/màn chờ) khi thiếu `required`, sai độ dài, email, số, `data-validate="email-or-phone"`, trình soạn thảo trống; hiện lỗi tiếng Việt bằng cùng cơ chế với lỗi máy chủ, focus ô lỗi đầu tiên, sửa xong tự bỏ đánh dấu. Bỏ qua form GET/`data-no-validate`. Chrome: form liên hệ, đăng nhập, modal tạo đợt, viết bài để trống → 0 request POST.
- **Deploy:** `deploy/deploy.sh` (xem `docs/DEPLOYMENT.md` mục 0). `bmb:create-admin` thêm `--name`, `--email`, mật khẩu qua `BMB_ADMIN_PASSWORD`. Tự rà và sửa trước khi giao: SIGPIPE `head -c` + pipefail, bẫy ERR giả trong `$(...)`, ufw có thể khóa SSH cổng khác 22, chạy lại mất HTTPS nếu quên tham số (lưu `/root/.gopgio/deploy.conf`), quyền file khóa kho Sury, `SCRIPT_FILENAME` trùng.
- **Sự cố khi thử:** `PHP_CLI_SERVER_WORKERS` khiến `artisan serve` trên Windows trả 500 rỗng (không fork được) — bỏ biến này khi chạy thử. Heredoc bash chứa nhiều dấu nháy bị lỗi phân tích → chuyển sang script Python/Write.
- **Kiểm thử:** `php artisan test` **173 passed (1270 assertions)** trên SQLite và MySQL 8.4.3; Pint sạch; `node --check` OK; `bash -n deploy/deploy.sh` OK. Test mới: `PublicHomeTest`, `ContactTest`, `PublicBatchTest`, bổ sung `ProductionHardeningTest`, `PostTest`. Chrome (bản cách ly SQLite + ảnh thử, đã xóa sau): 390/1280px không tràn ngang, không lỗi JS/CSP; sửa khi xem: nút slider đè chữ, menu header xuống dòng ở 390px, biểu tượng ảnh vỡ.
- **Chưa kiểm tra:** chạy `deploy.sh` trên VPS/container thật (không có quyền VPS; Docker Desktop đang tắt); điện thoại thật.

### 2026-10-14 — BM-071 — Tinh chỉnh trang công khai, lượt xem
- Trang chủ: "Bài viết" và tổng số bài cùng một hàng, canh đều hai bên (số theo bộ lọc hiện tại); dòng "Tìm thấy…" chỉ hiện khi đang lọc.
- Ô tìm trên header: bấm × (xóa) gửi form ngay để về danh sách đầy đủ (sự kiện `search` + `input` không có `inputType`; chỉ khi trang đang có từ khóa; cờ riêng chống gửi đôi — không dùng `data-submitting` vì sẽ bị bộ xử lý submit chung chặn).
- Chi tiết bài: hiện người viết; **bỏ ảnh đại diện ở đầu bài** (vẫn dùng cho thẻ bài, `og:image`, JSON-LD); breadcrumb đợt nấu → `/dot-nau/{public_id}`. Trang đợt: breadcrumb hiện tên đợt. Khóa cache chi tiết đổi `post:v2:` để bản cũ (chưa có tác giả) không được dùng.
- Thẻ bài: ảnh phủ kín khung 16:9 như ảnh nền (định vị tuyệt đối + `object-fit: cover`) — ảnh dọc/vuông không để viền trống.
- Slider: nút trước/sau ẩn, hiện khi rê chuột hoặc focus bàn phím; thiết bị cảm ứng/điện thoại không hiện nút (vuốt), bỏ lề chừa cho nút.
- **Lượt xem "không chạy":** tái hiện qua toàn bộ HTTP kernel — đếm đúng (lần đầu +1, cùng máy trong 6 giờ không tăng, máy khác +1). DB dev: mọi bài 0 lượt, không có khóa đếm nào → các lần xem trước đều khi **đăng nhập quyền quản lý bài** (cố ý không tính để chống tăng ảo). Giữ quy tắc; thêm dòng nhắc cho người quản lý ngay dưới số lượt xem. Chrome headless cũng không được tính (UA "HeadlessChrome" = bot).
- **Kiểm thử:** `php artisan test` **175 passed** trên SQLite và MySQL; Pint sạch; Chrome (bản cách ly, ảnh thử đã xóa): nút slider `hidden/0` → rê vào `visible/1`, điện thoại `display:none`; ảnh thẻ 406×228 = khung; chi tiết không có `.post-cover`.

### 2026-10-14 — BM-072 — Layout chi tiết đợt (admin), ngày bắt đầu kiểu DATE, ảnh nền
- **Lỗi layout:** CSS `.batch-hero` thêm cho trang đợt công khai trùng tên class đầu trang chi tiết đợt (admin) → thẻ đầu trang cao 320px có khoảng trống, chữ trắng làm mất số ngày trong ô lịch. Đổi class trang công khai thành `.public-batch-hero`. Bài học: class riêng của trang công khai dùng tiền tố `public-`.
- **Ảnh nền:** banner 150px (điện thoại) / 220px ở đầu thẻ chi tiết đợt (admin), tải ngay; form có gợi ý "1600 × 900 px (16:9), tối thiểu 1200 px ngang, ≤ 10 MB, đặt chủ thể ở giữa".
- **Ngày bắt đầu chỉ là ngày:** đổi tên `starts_at` → `starts_on` (29 file), cột DATE (xem DATA_MODEL). Form `type="date"` + `date_format:Y-m-d` (giá trị kèm giờ bị từ chối); hiển thị/CSV/bản in/báo cáo chỉ ngày; dashboard/bộ lọc so sánh bằng chuỗi `Y-m-d` (gồm ngày biên). Hạn công việc (`due_at`) vẫn có giờ.
- **Kiểm thử:** 176 passed trên SQLite và MySQL 8.4.3 (thêm `test_batch_start_is_a_date_only`, cập nhật tiêu đề cột CSV). Migration thử riêng với dữ liệu có sẵn (đợt 23:30 vẫn đúng ngày) + rollback + migrate lại trên MySQL và SQLite. Chrome: chi tiết đợt 390/768/1280 không tràn, ô lịch hiện ngày, banner hiển thị.
- **Lưu ý:** DB dev đã chạy đủ migration (gồm `001300`); ảnh nền đợt #2 người dùng tải lên được giữ nguyên.

### 2026-10-14 — Bỏ hộp hỏi "Bỏ các thay đổi chưa lưu?" khi đóng modal
- `app.js`: bỏ xử lý `hide.bs.modal` gọi `window.confirm` — đóng modal (×, Hủy, Esc, bấm ra ngoài) là đóng ngay, nội dung đã nhập mất. Giữ cảnh báo `beforeunload` của trình duyệt khi rời/tải lại trang có form chưa lưu (UX_UI_GUIDELINES).
- Test: `AjaxFlowTest::test_closing_modal_does_not_ask_to_discard_unsaved_changes`.
- **Cập nhật:** bỏ luôn cảnh báo `beforeunload` khi rời/tải lại trang có form chưa lưu (theo yêu cầu). Gỡ toàn bộ cơ chế đánh dấu form đã sửa: `hasDirtyForm`, `markDirty`, `data-dirty`, thuộc tính `data-warn-unsaved` và prop `warn` của `x-form-shell` (cùng `:warn="false"` ở 3 form hoàn tất/hủy/mở lại). Màn chờ chuyển trang nay luôn hiện khi bấm link (trước đây bỏ qua khi form chưa lưu để trình duyệt tự hỏi). UX_UI_GUIDELINES ghi nhận thay đổi; thao tác phá hủy vẫn có hộp xác nhận.

### 2026-10-14 — BM-073/074 — Kênh thông báo theo đợt; slug tiếng Anh, badge menu, chọn ngày, ô lịch, xác nhận đăng xuất
- **Kênh thông báo** (yêu cầu rõ của người vận hành — ngoài phạm vi MVP gốc, ghi OQ-19):
  - Bảng `batch_notification_channels`, enum `NotificationDriver` (thông số + quy tắc + mẫu link tham gia theo từng kênh), `NotificationChannelRequest` (chỉ kiểm tra thông số của loại đang chọn; ô bí mật để trống = giữ), `NotificationChannelController` (thêm/sửa/bật-tắt/gửi thử/xóa), policy `manageNotifications` (điều phối viên, kể cả đợt đã khóa).
  - `BatchNotifier`: nguồn sự kiện là `ActivityLog::record()` (thêm tham số nội dung riêng cho kênh) → mọi sự kiện quan trọng tự đi ra kênh. Thêm sự kiện `task.created`, `task.assigned` (trước đây không ghi log). Không gửi chi phí/chữ tự do. Gửi sau commit + sau khi trả trang (`defer`), timeout 5s, lỗi ghi vào kênh (đã che token) và hiện cho người quản lý.
  - Trang chi tiết đợt: khung "Kênh thông báo" đầu cột phụ (link tham gia, trạng thái gửi, gửi thử, menu sửa/bật-tắt/xóa). Form modal đổi ô theo loại kênh (JS), không JS vẫn gửi đúng (ô link riêng từng loại `join_urls[driver]`). `app.js` hiện lỗi mảng `settings.x` đúng ô và lỗi nghiệp vụ 422 (gửi thử thất bại) có lý do cụ thể.
- **Slug tiếng Anh** cho trang quản lý: `/admin/my-tasks`, `/admin/account/password`, `/admin/contacts` (+ `/toggle`), tham số lọc liên hệ `status`/`topic`; đường dẫn cũ chuyển 301. Trang công khai giữ tiếng Việt.
- **Badge menu:** "việc của tôi hôm nay" (thay "việc đang mở") và "đợt đang diễn ra" (xanh).
- **Chọn ngày:** flatpickr 4.6.13 (MIT) vào `public/vendor/flatpickr` qua `npm run assets`, tải lười khi trang có ô ngày; áp dụng `type=date` và `datetime-local` (hạn công việc). Ô gốc thành ẩn → đánh dấu `data-required` để bộ kiểm tra trình duyệt vẫn bắt lỗi, lỗi gắn sang ô hiển thị.
- **Ô lịch** (`x-date-tile`): `align-self: flex-start` — không bị kéo cao theo khối tiêu đề (danh sách, chi tiết, dashboard, báo cáo).
- **Đăng xuất** có modal xác nhận (cả sidebar và menu điện thoại); bỏ hoàn toàn cảnh báo "thay đổi chưa lưu" (modal + `beforeunload`).
- **Kiểm thử:** `php artisan test` **188 passed** trên SQLite và MySQL 8.4.3 (thêm `NotificationChannelTest` 9 test với `Http::fake` + `preventStrayRequests`, test slug/redirect, badge hôm nay, xác nhận đăng xuất). Chrome (bản cách ly, ảnh thử đã xóa, ảnh nền người dùng giữ nguyên): chọn ngày 20 → hiển thị 20/10/2026, gửi 2026-10-20; xóa ngày → "Vui lòng nhập ngày bắt đầu." ở đúng ô; form kênh đổi loại chỉ gửi ô của loại đó; đăng xuất hiện modal, bấm "Không" vẫn đăng nhập; lịch 308px ở 390px không tràn; ô lịch 64px (khung 165px).
- **Chưa kiểm tra:** gửi thật tới Slack/Telegram/Chatwork (không có token thật — chỉ kiểm bằng giả lập HTTP); hãy bấm "Gửi thử" sau khi cấu hình.

### 2026-10-15 — Chọn ngày, thanh cuộn ngang, bộ lọc báo cáo (điện thoại), dấu "—", đơn giá nguyên liệu, "Cần xử lý" thu gọn
- **Chọn ngày (flatpickr):** ô hiển thị chỉ đọc (`allowInput: false`, `inputmode="none"`) — bấm vào chỉ mở lịch, không bật bàn phím/con trỏ gõ. Lịch có nút **"Hôm nay"** (ô có giờ: giờ hiện tại làm tròn lên 5 phút).
- **Menu/tab cuộn ngang** (`.segmented`, `.chip-row`; `.section-nav`, slider, album đã ẩn sẵn): ẩn thanh cuộn, vẫn vuốt được. Bảng dữ liệu (`.table-scroll`) giữ thanh cuộn vì không phải menu.
- **Báo cáo trên điện thoại:** nút phễu cạnh "Tải CSV"/"In"; bộ lọc thu gọn, bấm nút → nút ẩn, form hiện và focus ô đầu. Đang lọc thì nút có chấm cam. Chỉ thu gọn khi có JS (nút mặc định `hidden`), không JS vẫn thấy form. Phạm vi lọc vẫn in ở dòng "Phạm vi" nên không bị mất thông tin.
- **Số liệu chưa có hiển thị "—"** thay cho "Chưa nhập"/"Chưa ghi nhận": `Format::NOT_ENTERED = '—'` (dùng chung với `Format::EMPTY` và chú thích sẵn có "“—” nghĩa là chưa nhập (khác với 0)"), biểu đồ cột ngang, báo cáo đợt, dòng nguyên liệu. Câu nhắc/tiêu đề trạng thái rỗng (ví dụ "Chưa nhập khoản chi nào", nhắc khi hoàn tất) giữ nguyên vì là câu chữ, không phải ô số liệu.
- **Đơn giá nguyên liệu** (tùy chọn): cột `ingredients.unit_price_vnd` (xem DATA_MODEL). Form có ô tiền "đ / 1 đơn vị" + xem trước thành tiền (JS, máy chủ tính lại). Dòng nguyên liệu hiện đơn giá + thành tiền (theo lượng thực tế/dự kiến); đầu danh sách hiện tổng tiền nguyên liệu và số dòng có đơn giá. Báo cáo đợt: cột Đơn giá/Thành tiền + tổng; CSV thêm 2 cột và dòng "Tiền nguyên liệu theo đơn giá (VND)". Chỉ người ghi nhận trở lên thấy giá. Không gộp vào chi phí thực tế (tránh tính trùng với khoản chi).
- **Định dạng số lượng** "Lượng dự kiến"/"Lượng thực tế": ô `x-input quantity` tự thêm dấu chấm hàng nghìn khi gõ, dấu phẩy thập phân (tối đa 3 số); phím "." được hiểu là dấu thập phân. Dán "2.5" vẫn hiểu là 2,5. Máy chủ: `Input::quantity` ("1.500,25" → 1500.25, "1.500" → 1500, "2.5" → 2.5); form hiển thị lại bằng `Format::quantityField`. Lưu ý: gửi thô "1.125" (không qua JS) được hiểu là 1125.
- **Trang chủ "Cần xử lý":** `<details>` mặc định đóng, bấm tiêu đề (có số lượng + mũi tên) để mở; chạy cả khi không có JS, dùng được bằng bàn phím.
- **Kiểm thử:** `php artisan test` **194 passed** (SQLite); Pint sạch; `node --check public/js/app.js`; logic định dạng số lượng chạy thử bằng Node (gõ, xóa "1.500" → "150", dán "2.5"). Migration đã chạy trên DB dev (MySQL).
- **Chưa kiểm tra trên trình duyệt thật:** lịch chỉ đọc + nút "Hôm nay", nút phễu báo cáo, ô số lượng khi gõ trên điện thoại — cần thử tay ở 390px.

### 2026-10-15 — Bộ lọc thu gọn (liên hệ, tài khoản, bài viết), số suất có dấu chấm, bố cục danh sách điện thoại, lên đầu trang, SEO
- **Bộ lọc thu gọn trên điện thoại:** component `x-filter-toggle` (nút phễu, chấm cam khi đang lọc) dùng cho Báo cáo, Liên hệ, Tài khoản, Bài viết. Form lọc có `id` và chỉ thu gọn khi JS chạy (`is-collapsible`); không JS thì form luôn hiện.
- **Số suất dự kiến:** `x-input integer` (dùng chung cơ chế `data-money`: chỉ cho gõ số, tự thêm dấu chấm, không có hậu tố "đồng"); `CookingBatchRequest` chuẩn hóa "1.200" → 1200 bằng `Input::money`.
- **Tài khoản (điện thoại):** vai trò + trạng thái cùng một hàng (`user-pills` không xuống dòng); nút Sửa và Khóa/Mở khóa ngang hàng ở dòng dưới cùng (`row-actions`), máy tính giữ nguyên một dòng.
- **Bài viết:** dòng trên gồm trạng thái + thao tác, tiêu đề riêng một dòng chiếm trọn chiều ngang; người viết + lượt xem luôn cùng dòng (`meta-pair`). Điện thoại: Đăng/Gỡ bài nằm trong menu "⋮" (vẫn có hộp xác nhận khi gỡ), máy tính vẫn để nút ngoài.
- **Liên hệ công khai (điện thoại):** chủ đề cuộn ngang một hàng, ẩn thanh cuộn, chừa chỗ cho viền focus.
- **Lên đầu trang** (trang công khai): nút tròn góc phải dưới, hiện khi cuộn quá ~1,5 màn hình; ẩn thì không nhận Tab (`visibility`); tôn trọng `prefers-reduced-motion`; bấm xong đưa focus về `#main`. Không JS thì không hiện.
- **SEO — rà soát:** trang công khai đã có title, description, canonical, robots (noindex trang tìm kiếm/lọc, bản xem trước), sitemap.xml, OG + JSON-LD cho bài viết. Bổ sung:
  - Khu quản lý, đăng nhập, trang lỗi: `X-Robots-Tag: noindex, nofollow` (trong `SecurityHeaders`, gồm cả phản hồi chuyển hướng về /login và mọi mã ≥ 400) + `<meta name="robots" content="noindex, nofollow">` trong layout app/guest/lỗi; `robots.txt` thêm `Disallow: /admin`, `/login`; link Đăng nhập ngoài trang công khai `rel="nofollow"`.
  - Layout công khai tự sinh OG (`og:type/title/description/url/image`) và Twitter card cho mọi trang (trước đây trang chủ, liên hệ chưa có); bài viết/đợt nấu bỏ thẻ trùng.
  - Trang chủ (không lọc): JSON-LD `WebSite` (kèm `SearchAction` tìm bài) + `Organization`.
  - `robots.txt` chưa có dòng `Sitemap:` vì cần URL tuyệt đối và file được nginx phục vụ tĩnh — hãy khai báo `https://<tên miền>/sitemap.xml` trong Google Search Console.
- **Bộ icon rút gọn** tạo lại (`npm run icons`, 105 icon): các icon mới `bi-funnel`, `bi-calendar-check`, `bi-arrow-up`, `bi-chevron-down` trước đó chưa có trong font nên sẽ hiện trống.
- **Kiểm thử:** `php artisan test` **201 passed** (thêm `SeoTest`, test bộ lọc thu gọn 3 trang, bố cục bài viết/tài khoản, số suất "1.200"); Pint sạch; `node --check`. Chưa thử trên trình duyệt thật.

### 2026-10-15 — Thông báo kênh qua queue, số suất, biểu đồ báo cáo, quên mật khẩu, phạm vi xem theo vai trò, chi tiết tài khoản
- **Thông báo kênh (Slack/Telegram/Chatwork):**
  - Rà soát: trước đây KHÔNG gửi khi thêm/sửa/xóa nguyên liệu, sửa/xóa việc, việc chuyển "Đang làm"/"Chưa làm", gỡ bài. Đã bổ sung (enum mới `ingredient.created/updated/deleted`, `task.updated/deleted`; `task.status` ghi mọi lần đổi thật sự; thêm `PostUnpublished` vào danh sách gửi). Vẫn KHÔNG gửi chi phí, đơn giá, ghi chú tự do (quy tắc bảo mật); đổi chỉ đơn giá/ghi chú nguyên liệu không gửi.
  - Không gửi khi tạo đợt (chưa có kênh) và xóa đợt nháp (kênh xóa cùng đợt).
  - Chuyển từ `defer` sang **queue**: `SendBatchChannelNotification` (mỗi kênh một job, sau commit; payload chỉ có ID kênh + nội dung — không có token; thử lại 30 giây/2 phút). Nút "Gửi thử" vẫn gửi ngay để báo kết quả.
  - `deploy.sh`: đặt `QUEUE_CONNECTION=database`, tạo dịch vụ systemd `gopgio-queue` (`queue:work --tries=3 --max-time=3600`, `queue:restart` mỗi lần cập nhật); không có systemd thì cron mỗi phút `--stop-when-empty`. `bmb:check-production` cảnh báo khi queue là `sync` hoặc có việc chờ quá 10 phút.
  - **Máy dev (Laragon):** `.env` dùng `QUEUE_CONNECTION=database` → cần chạy `php artisan queue:work` (hoặc `composer dev`) thì thông báo mới gửi.
- **Số suất:** "Đã chuẩn bị", "Đã phân phát" dùng ô số có dấu chấm (`x-input integer`); server `Input::money`. **Đã phân phát ≤ đã chuẩn bị** (`lte`, chỉ so khi đã nhập số chuẩn bị; thông báo nêu rõ số chuẩn bị). Sửa lỗi 500 có sẵn khi thiếu `variance_note` trong request.
- **Kênh thông báo:** nút "Gửi thử" chuyển vào menu "⋮"; chú thích liệt kê đầy đủ sự kiện được gửi.
- **Báo cáo chi tiết đợt:** thêm 4 khối biểu đồ — số suất; công việc theo trạng thái; chi phí (dự kiến / thực tế / tiền nguyên liệu) + chi phí mỗi suất đã phân phát; cơ cấu chi (5 khoản chi lớn nhất, nguyên liệu tốn tiền nhất theo đơn giá).
- **Quên / đặt lại mật khẩu:** `/forgot-password`, `/reset-password/{token}` (`PasswordResetController`), email tiếng Việt gửi qua queue (`ResetPasswordNotification`), thông báo chung không tiết lộ email; tài khoản khóa không nhận/không dùng được link; link 1 lần, 60 phút; throttle IP (`password-reset`, 5/10 phút) + broker 60 giây/email. Đặt lại xong: đổi remember token, các phiên khác bị đăng xuất, ghi lịch sử. Trang được noindex + robots.txt. `bmb:check-production` cảnh báo khi `MAIL_MAILER=log/array` hoặc địa chỉ gửi còn `@example.com` — **production cần cấu hình SMTP** thì email mới tới.
- **"Ghi nhớ đăng nhập" — kiểm tra:** hoạt động đúng (test `RememberMeTest`: cookie giữ đăng nhập sau khi phiên hết hạn; không chọn thì không có cookie; khóa tài khoản / đổi mật khẩu làm cookie cũ hết hiệu lực — Laravel 12 kiểm mã băm mật khẩu trong cookie). Lỗ hổng tìm thấy: đổi mật khẩu **không** đăng xuất các phiên đang mở trên máy khác → thêm middleware `auth.session` cho `/admin`; máy đang đổi mật khẩu được cấp lại cookie mới. Hai test cũ đổi người dùng trong cùng phiên test được sửa để bắt đầu phiên mới.
- **Phạm vi xem theo vai trò** (xem SECURITY_PRIVACY): điều phối viên chỉ đợt mình điều phối; ghi nhận/tình nguyện viên chỉ đợt có việc của mình; quản trị xem hết. Áp cho danh sách, chi tiết, in, dashboard (mọi số liệu + biểu đồ), badge menu, báo cáo + CSV, ô chọn đợt khi viết bài (validation server). Ngoài phạm vi → 404 qua `before()` của policy. Điều phối viên giao đợt cho người khác → về danh sách kèm thông báo. Hơn 30 test cũ được cập nhật bằng helper `assignToBatch()` (người dùng phải thuộc đợt), thêm `BatchVisibilityTest`.
- **Chi tiết tài khoản (quản trị viên)** `/admin/users/{id}`: tab Thông tin (vai trò, số đợt điều phối/tham gia, việc được giao/đã xong, hoạt động gần nhất), Nhật ký hoạt động (phân trang), Thông báo đã nhận (bảng `notifications` mới: "được giao việc", "email đặt lại mật khẩu" — không lưu token), Thiết bị đăng nhập (bảng `sessions`: trình duyệt/hệ điều hành/IP/lúc hoạt động, đăng xuất từng thiết bị hoặc mọi thiết bị + đổi remember token; mã phiên không lộ ra HTML). Tên trong danh sách tài khoản là link tới trang chi tiết.
- **Kiểm thử:** `php artisan test` **229 passed** (SQLite); Pint sạch; `bash -n deploy/deploy.sh`. Migration `notifications` đã chạy trên DB dev. Chưa thử trên trình duyệt thật và chưa gửi email/Slack thật.

### 2026-10-15 — Đổi thuật ngữ "Đợt nấu ăn" → "Chương trình"
- Lý do: hệ thống dùng cho cả nấu ăn, phát quà và các hoạt động thiện nguyện khác (người vận hành chọn "Chương trình").
- Chỉ đổi **chữ** (giao diện, thông báo, CSV, email, tin nhắn kênh, chú thích code): "đợt nấu ăn" / "đợt nấu" / "đợt" → "chương trình", giữ hoa/thường; script áp cho `resources/views`, `app`, `lang`, `config`, `routes`, `database`, `public/js`, `tests` (89 + 16 + 9 file). Chữ đặc thù nấu ăn: nút "Bắt đầu nấu" → "Bắt đầu thực hiện", bước "Đang nấu" → "Đang diễn ra". Giữ "suất ăn" trong các lưu ý quyền riêng tư người nhận.
- **Không đổi** tên trong code/CSDL (`CookingBatch`, `cooking_batches`, `batches.*`, `/admin/batches`) — tránh migration đổi bảng rủi ro, không ảnh hưởng người dùng.
- Đường dẫn công khai `/dot-nau/{public_id}` → `/chuong-trinh/{public_id}`; link cũ chuyển **301** (giữ `?page=`). Sitemap/canonical tự dùng đường dẫn mới. Tên file CSV: `bao-cao-chuong-trinh-…`.
- Dữ liệu đã nhập (tên chương trình, lịch sử thay đổi cũ có chữ "đợt nấu") giữ nguyên.
- Kiểm thử: **231 passed** (thêm test chuyển hướng 301 và test các trang chính không còn chữ "đợt nấu"). README có mục thuật ngữ; tài liệu cũ trong `docs/` vẫn dùng "đợt nấu" = "chương trình".

### 2026-10-15 — Đổi thương hiệu BếpMinhBạch → Góp Gió; rà soát QA/bảo mật/hiệu năng trước production
- **Thương hiệu:** mọi chữ hiển thị "BếpMinhBạch" → "Góp Gió" (giao diện, tiêu đề, logo chữ `Góp <span class="brand-accent">Gió</span>`, email, CSV, tin nhắn kênh, SEO/OG, chân trang, tài liệu); "đội bếp" → "đội Góp Gió"; biểu tượng trứng chiên → `bi-wind`, favicon mới (nét gió). `APP_NAME="Góp Gió"`; email demo `@gopgio.test` (`bmb:check-production` vẫn bắt cả `@bepminhbach.test` cũ); `.env.example` CSDL `gopgio`; `deploy.sh` cài mới dùng CSDL/tài khoản `gopgio`, máy đã cài giữ tên cũ (đọc từ `db.env`). **Không đổi** tên file, thư mục, repo, đường dẫn `/var/www/bepminhbach`, dịch vụ `bepminhbach-*`, tiền tố code `bmb`.
- **CSDL máy dev chưa đổi:** MySQL máy dev đang từ chối kết nối root (`Access denied for 'root'@'172.25.0.1'`, kể cả từ PHP) nên chưa chép được `gopgio` → `gopgio`; `.env` máy dev tạm giữ `DB_DATABASE=gopgio`. Cách làm: xem báo cáo/README.
- **Rà soát QA — đã sửa:**
  - Hiệu năng: "Công việc của tôi" chạy **185 truy vấn** (N+1: kiểm tra phạm vi xem + tên người phụ trách cho từng việc) → **7** (nạp sẵn `coordinator_id`, việc của chính người dùng, `assignee`, `completer`). Thêm `QueryBudgetTest` giới hạn số truy vấn 10 trang chính.
  - Lỗi phân quyền: điều phối viên được giao việc trong chương trình của người khác thấy việc nhưng mở chương trình bị 404, không cập nhật được (và `coordinator_id` không được nạp ở trang "Công việc của tôi" nên mất nút cập nhật cả ở chương trình của mình) → quy tắc điều phối viên: điều phối HOẶC có việc được giao.
  - Bảo mật: "Quên mật khẩu" lộ email có tài khoản qua thông báo "gửi quá nhanh" (gửi 2 lần) → mọi trường hợp cùng một thông báo.
  - Production: log `single` phình vô hạn → `LOG_STACK=daily`, 14 ngày; thêm lịch `auth:clear-resets` (hằng ngày), `queue:prune-failed --hours=720` (hằng tuần) + cron `schedule:run` trong `deploy.sh`.
- **Rà soát QA — đã kiểm, không có vấn đề:** truy vấn thô đều có tham số ràng buộc; HTML bài viết được lọc khi lưu (`PostHtml::sanitize`); `{!! !!}` chỉ ở nội dung đã lọc và khai báo XML sitemap; webhook Slack chỉ nhận `hooks.slack.com`, Telegram/Chatwork địa chỉ cố định (không SSRF); `composer audit`, `npm audit --omit=dev`: 0 lỗ hổng; `route:cache`, `view:cache` (biên dịch mọi view), `config:cache` chạy được; các khóa ngoại/cột lọc chính có index.
- **Kiểm thử:** `php artisan test` **234 passed** (SQLite); Pint sạch; `node --check`; `bash -n deploy/deploy.sh`.

### 2026-10-15 — Duyệt bài viết, che thông tin liên hệ, mật khẩu tối thiểu 8, tên gopgio trong tài liệu/deploy
- **Quy trình duyệt bài:** trạng thái mới `pending` ("Chờ duyệt"); cột `posts.submitted_at`, `reviewed_by`, `reviewed_at`, `review_note`.
  - Điều phối viên: chỉ thấy/sửa/xóa bài **của mình**, "Lưu nháp" hoặc "Gửi duyệt", rút yêu cầu, tự gỡ bài của mình; không tự đăng được (`save_as` giới hạn theo vai trò ở `PostRequest`). Sửa bài **đang đăng** → về "Chờ duyệt", ẩn khỏi trang công khai tới khi duyệt lại. Bài của người khác: 404.
  - Quản trị viên: thấy mọi bài (bài chờ duyệt lên đầu, badge số bài chờ duyệt trên menu), "Duyệt & đăng", "Từ chối" (bắt buộc lý do, form modal), gỡ bài; bài tự viết đăng ngay.
  - Thông báo (`PostReviewNotification`, tab "Thông báo đã nhận"): quản trị viên nhận "bài chờ duyệt"; tác giả nhận "đã duyệt" / "chưa được duyệt + lý do". Lý do từ chối hiện ở danh sách và form sửa.
  - Xem trước bài chưa đăng: chỉ tác giả và quản trị viên. Trang công khai chỉ hiện bài đã đăng (không đổi).
  - Route: `posts.submit`, `posts.withdraw`, `posts.publish` (chỉ quản trị viên), `posts.reject` (GET form + POST), `posts.unpublish`; bỏ `togglePublish`.
- **Liên hệ — che thông tin nhạy cảm:** `App\Support\Mask::contact` (`l*****@gmail.com`, `090****567`). Trang công khai: lời xác nhận sau khi gửi chỉ có bản đã che. Trang quản lý: danh sách chỉ in bản đã che (bản đầy đủ không có trong HTML); nút "Hiện" gọi `POST /admin/contacts/{id}/reveal` (quyền xem liên hệ, `Cache-Control: no-store`) → thay bằng link mailto/tel; **mỗi lần xem ghi nhật ký** `contact.revealed` (không ghi chính email/số điện thoại). Quyền truy cập trang liên hệ giữ nguyên.
- **Mật khẩu tối thiểu 8 ký tự** ở mọi nơi (lệnh `bmb:create-admin` trước đây yêu cầu 10).
- **Tên `gopgio` trong tài liệu và deploy:** `deploy.sh` dùng biến `SLUG` cho thư mục, bí mật, sao lưu, nginx, php.ini, khóa, cron, dịch vụ queue: máy đã cài trước (có `/root/.bepminhbach`) giữ `bepminhbach`, máy cài mới dùng `gopgio`. Sửa 2 chỗ `printf` nháy đơn để `SLUG` được bung đúng trong cron. Tài liệu (`README`, `docs/`, `tasks/`) đổi `bepminhbach` → `gopgio` (giữ URL repo GitHub và các câu nói về tên cũ).
- **Kiểm thử:** `php artisan test` **239 passed**; thêm `PostReviewTest` (4), test che/hiện liên hệ; cập nhật test bài viết/ảnh/cache/kênh theo quy trình duyệt. Pint sạch; `node --check`; `bash -n deploy/deploy.sh`.
