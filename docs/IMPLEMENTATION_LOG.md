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
  - Chạy cùng bộ test trên **MySQL 8.4** với DB riêng `bepminhbach_test` (`DB_CONNECTION=mysql DB_DATABASE=bepminhbach_test php artisan test`): **83 passed** → BM-003 đã xác minh trên MySQL.
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
  - `php artisan test`: **85 passed** trên SQLite và trên MySQL `bepminhbach_test` (thêm 2 test markup trong `UiLayoutTest`). Pint sạch.
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
- **Sự cố khi thử:** lệnh thử migration với `--env=testing` (không có `.env.testing` nên đọc `.env`) đã tạo file SQLite thừa `./bepminhbach`; đã xóa. Không ảnh hưởng MySQL (`database/database.sqlite` có từ trước, không đụng tới).
- **Kiểm thử:** `php artisan test` **120 passed (798 assertions)** trên SQLite và trên MySQL 8.4 (`bepminhbach_test`); thêm `PostTest` (10) và `RateLimitTest` (4, chạy lặp 3 lần ổn định). Pint sạch; `node --check` OK; bộ icon rút gọn tạo lại (81 icon, 7 KB).
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
- **Sự cố:** một lần nữa lỡ chạy `migrate:fresh --database=sqlite` (định chỉ kiểm tra) tạo file `./bepminhbach`; đã xóa, không ảnh hưởng MySQL.
- **Kiểm thử:** `php artisan test` **139 passed (994 assertions)** trên SQLite và MySQL 8.4 (`bepminhbach_test`); thêm `PostMediaTest` (12), `PublicPostCacheTest` (4), `RequestNormalizationTest` (3). Pint sạch; `node --check` OK; bộ icon 85 icon.
  - E2E trên bản **cách ly** (SQLite trong scratchpad, `artisan serve` cổng 8001, cookie phiên riêng, tài khoản thử tạo riêng): tạo bài có ảnh bìa 3000×2000 (gửi lên 187 KB WebP sau khi thu nhỏ trên máy) + 2 ảnh album → 200, ảnh lưu đúng 3 kích thước; trang công khai dùng bản 480 ở màn hẹp, PhotoSwipe mở "1 / 2"; sửa bài: bỏ 1 ảnh mới bằng ×, chọn rồi bỏ ảnh bìa mới (trả lại ảnh cũ), đánh dấu xóa 1 ảnh cũ → DB và file khớp; album trượt ngang trên màn hẹp. Sau đó xóa đúng 9 file ảnh thử + DB tạm; 12 file ảnh của người dùng giữ nguyên.
- **Chưa kiểm tra:** trên điện thoại thật; hiệu quả `.htaccess` của `public/uploads` trên Apache thật; giao diện desktop rộng bằng mắt (cửa sổ trình duyệt hẹp).
