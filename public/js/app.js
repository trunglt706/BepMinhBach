// Góp Gió — JavaScript tăng cường (progressive enhancement).
// Khi JS tắt, mọi form vẫn gửi bình thường và trang redirect như trước; JS chỉ thêm:
// modal + AJAX (ít chuyển trang), ô tiền tự định dạng, loader, skeleton, hộp xác nhận.
(function () {
    'use strict';

    var SPINNER = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>';
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var hasBootstrap = typeof window.bootstrap !== 'undefined';

    // =====================================================================
    // Thành phần dùng chung: vùng thông báo, thanh tải, lớp phủ, toast
    // =====================================================================
    function ensure(selector, html) {
        var el = document.querySelector(selector);
        if (!el) {
            document.body.insertAdjacentHTML('beforeend', html);
            el = document.querySelector(selector);
        }
        return el;
    }

    var liveRegion = ensure('#bmb-live', '<div id="bmb-live" class="visually-hidden" role="status" aria-live="polite"></div>');
    var loader = ensure('.page-loader', '<div class="page-loader" aria-hidden="true"><div class="page-loader-bar"></div></div>');
    // Màn chờ: logo (SVG nội tuyến, không phụ thuộc font) ở giữa, vòng xoay bao quanh.
    var overlay = ensure('.page-overlay', '<div class="page-overlay" aria-hidden="true"><div class="page-overlay-box">'
        + '<div class="loader-logo">'
        + '<svg class="loader-ring" viewBox="0 0 100 100"><circle class="loader-track" cx="50" cy="50" r="46"/>'
        + '<circle class="loader-arc" cx="50" cy="50" r="46"/></svg>'
        + '<svg class="loader-mark" viewBox="0 0 64 64"><rect width="64" height="64" rx="16" fill="#1b6343"/>'
        + '<ellipse cx="32" cy="33" rx="19" ry="17" fill="#fff"/><circle cx="32" cy="33" r="8" fill="#e5a11a"/></svg>'
        + '</div><span data-overlay-text>Đang tải…</span></div></div>');

    function announce(message) {
        liveRegion.textContent = '';
        setTimeout(function () { liveRegion.textContent = message; }, 30);
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /** Thông báo nhỏ góc màn hình, tự ẩn. type: 'success' | 'error' */
    function toast(message, type) {
        var stack = document.getElementById('bmb-toasts');
        if (!stack) {
            announce(message);
            return;
        }
        var isError = type === 'error';
        var item = document.createElement('div');
        item.className = 'toast-item ' + (isError ? 'is-error' : 'is-success');
        item.setAttribute('role', isError ? 'alert' : 'status');
        item.innerHTML = '<i class="bi ' + (isError ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill') + '" aria-hidden="true"></i>'
            + '<span>' + escapeHtml(message) + '</span>'
            + '<button type="button" class="btn-close btn-close-sm" aria-label="Đóng thông báo"></button>';
        stack.appendChild(item);
        var remove = function () {
            item.classList.add('is-leaving');
            setTimeout(function () { item.remove(); }, 200);
        };
        item.querySelector('.btn-close').addEventListener('click', remove);
        setTimeout(remove, isError ? 8000 : 4500);
    }

    // =====================================================================
    // Loader khi chuyển trang
    // =====================================================================
    var timers = [];

    function startPageLoading(message, withOverlay) {
        stopPageLoading();
        // Hiện sau 120ms để không nhấp nháy khi phản hồi nhanh; lớp phủ chỉ hiện nếu chờ > 1 giây.
        timers.push(setTimeout(function () { loader.classList.add('is-active'); }, 120));
        if (withOverlay !== false) {
            timers.push(setTimeout(function () { overlay.classList.add('is-active'); }, 1000));
        }
        timers.push(setTimeout(resetLoadingState, 20000)); // an toàn nếu trang không chuyển
        message = message || 'Đang tải trang…';
        overlay.querySelector('[data-overlay-text]').textContent = message;
        announce(message);
    }

    function stopPageLoading() {
        timers.forEach(clearTimeout);
        timers = [];
        loader.classList.remove('is-active');
        overlay.classList.remove('is-active');
    }

    // =====================================================================
    // Ô tiền tệ: chỉ cho nhập chữ số, tự thêm dấu chấm hàng nghìn,
    // bỏ dấu trước khi gửi (backend cũng tự chuẩn hóa thêm một lần).
    // =====================================================================
    function digitsOnly(value) {
        return String(value || '').replace(/\D/g, '');
    }

    function formatMoney(digits) {
        return digits.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function initMoney(root) {
        (root || document).querySelectorAll('[data-money]').forEach(function (input) {
            var digits = digitsOnly(input.value);
            if (digits !== '' || input.value === '') {
                input.value = formatMoney(digits);
            }
        });
    }

    document.addEventListener('beforeinput', function (event) {
        var input = event.target;
        if (input.matches && input.matches('[data-money]') && event.inputType === 'insertText' && /\D/.test(event.data || '')) {
            event.preventDefault(); // chặn chữ cái, dấu trừ, dấu phẩy...
        }
    });

    document.addEventListener('input', function (event) {
        var input = event.target;
        if (!input.matches || !input.matches('[data-money]')) {
            return;
        }
        // Giữ con trỏ đứng sau đúng chữ số người dùng vừa gõ (kể cả khi dán "1.500.000 đ").
        var caret = input.selectionStart || 0;
        var digitsBeforeCaret = digitsOnly(input.value.slice(0, caret)).length;
        var formatted = formatMoney(digitsOnly(input.value));
        input.value = formatted;
        var pos = 0;
        var seen = 0;
        while (pos < formatted.length && seen < digitsBeforeCaret) {
            if (/\d/.test(formatted[pos])) {
                seen++;
            }
            pos++;
        }
        input.setSelectionRange(pos, pos);
    });

    // =====================================================================
    // Ô số lượng (data-quantity): kiểu Việt Nam "1.500,25" — tự thêm dấu chấm hàng nghìn,
    // dấu phẩy là phần thập phân (tối đa 3 số). Phím "." được hiểu là dấu thập phân vì dấu
    // chấm hàng nghìn do hệ thống tự thêm. Máy chủ chuẩn hóa lại (Input::quantity).
    // =====================================================================
    // pasted = true: chữ dán từ nơi khác, "2.5" có thể là số thập phân. Còn lại (đang gõ/xóa, giá trị
    // máy chủ trả về) mọi dấu chấm đều là phân cách hàng nghìn, nên xóa 1 số của "1.500" → "150".
    function splitQuantity(value, pasted) {
        var s = String(value || '').replace(/[^\d.,]/g, '');
        var comma = s.indexOf(',');
        var intPart;
        var decPart = null;
        if (comma !== -1) {
            intPart = s.slice(0, comma).replace(/\D/g, '');
            decPart = s.slice(comma + 1).replace(/\D/g, '');
        } else if (!pasted || /^\d{1,3}(\.\d{3})+$/.test(s)) {
            intPart = s.replace(/\./g, '');
        } else if (s.indexOf('.') !== -1) {
            // Dán "2.5" từ nơi khác: dấu chấm cuối cùng là dấu thập phân.
            var dot = s.lastIndexOf('.');
            intPart = s.slice(0, dot).replace(/\D/g, '');
            decPart = s.slice(dot + 1).replace(/\D/g, '');
        } else {
            intPart = s;
        }
        return { int: intPart, dec: decPart === null ? null : decPart.slice(0, 3) };
    }

    function formatQuantity(value, pasted) {
        var parts = splitQuantity(value, pasted);
        if (parts.int === '' && parts.dec === null) {
            return '';
        }
        var intText = formatMoney(parts.int === '' ? '0' : parts.int);
        return parts.dec === null ? intText : intText + ',' + parts.dec;
    }

    /** Giá trị số (dấu chấm thập phân) của ô số lượng; null nếu trống. */
    function quantityValue(input) {
        var parts = splitQuantity(input.value);
        if (parts.int === '' && !parts.dec) {
            return null;
        }
        return parseFloat((parts.int || '0') + (parts.dec ? '.' + parts.dec : ''));
    }

    document.addEventListener('beforeinput', function (event) {
        var input = event.target;
        if (!input.matches || !input.matches('[data-quantity]') || event.inputType !== 'insertText') {
            return;
        }
        var data = event.data || '';
        if (data === '.' || data === ',') {
            event.preventDefault();
            if (input.value.indexOf(',') === -1) {
                input.setRangeText(',', input.selectionStart, input.selectionEnd, 'end');
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        } else if (/\D/.test(data)) {
            event.preventDefault();
        }
    });

    document.addEventListener('input', function (event) {
        var input = event.target;
        if (!input.matches || !input.matches('[data-quantity]')) {
            return;
        }
        var caret = input.selectionStart || 0;
        var before = input.value.slice(0, caret);
        var digitsBeforeCaret = digitsOnly(before).length;
        var commaBeforeCaret = before.indexOf(',') !== -1;
        var formatted = formatQuantity(input.value, /^insertFrom(Paste|Drop)/.test(event.inputType || ''));
        if (formatted === input.value) {
            return;
        }
        input.value = formatted;
        var pos = 0;
        var seen = 0;
        while (pos < formatted.length && (seen < digitsBeforeCaret || (commaBeforeCaret && formatted.slice(0, pos).indexOf(',') === -1))) {
            if (/\d/.test(formatted[pos])) {
                seen++;
            }
            pos++;
        }
        input.setSelectionRange(pos, pos);
    });

    // Xem trước thành tiền nguyên liệu = đơn giá × lượng (thực tế, chưa có thì dự kiến). Máy chủ tính lại khi lưu.
    function updateIngredientCost(form) {
        var output = form.querySelector('[data-ingredient-cost]');
        var price = form.querySelector('[name="unit_price_vnd"]');
        if (!output || !price) {
            return;
        }
        var priceValue = digitsOnly(price.value);
        var actual = quantityValue(form.querySelector('[name="actual_quantity"]'));
        var quantity = actual !== null ? actual : quantityValue(form.querySelector('[name="expected_quantity"]'));
        if (priceValue === '' || quantity === null) {
            output.hidden = true;
            output.textContent = '';
            return;
        }
        var total = Math.round(parseInt(priceValue, 10) * quantity);
        output.hidden = false;
        output.innerHTML = '';
        output.appendChild(document.createTextNode('Thành tiền: '));
        var strong = document.createElement('strong');
        strong.className = 'tabular';
        strong.textContent = formatMoney(String(total)) + ' đ';
        output.appendChild(strong);
        output.appendChild(document.createTextNode(actual !== null ? ' (theo lượng thực tế)' : ' (theo lượng dự kiến)'));
    }

    document.addEventListener('input', function (event) {
        var form = event.target.form;
        if (form && form.querySelector('[data-ingredient-cost]')) {
            updateIngredientCost(form);
        }
    });

    function initQuantities(root) {
        (root || document).querySelectorAll('[data-quantity]').forEach(function (input) {
            input.value = formatQuantity(input.value);
        });
        (root || document).querySelectorAll('[data-ingredient-cost]').forEach(function (output) {
            if (output.closest('form')) {
                updateIngredientCost(output.closest('form'));
            }
        });
    }

    function normalizeMoneyInputs(form) {
        form.querySelectorAll('[data-money]').forEach(function (input) {
            input.value = digitsOnly(input.value);
        });
    }

    // =====================================================================
    // Skeleton cho bảng/danh sách khi lọc hoặc chuyển trang
    //   data-skeleton="table" (trên <table>, giữ tiêu đề cột) | "cards" | "list"
    // =====================================================================
    var WIDTHS = [82, 58, 70, 46, 88, 64, 76, 52];

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function line(width, extra) {
        return '<span class="skeleton ' + (extra || '') + '" style="width:' + width + '%"></span>';
    }

    function buildTable(table) {
        var cols = table.querySelectorAll('thead th').length || 4;
        var current = table.tBodies[0] ? table.tBodies[0].rows.length : 0;
        var rows = clamp(current || 5, 3, 8);
        var html = '';
        for (var r = 0; r < rows; r++) {
            html += '<tr>';
            for (var c = 0; c < cols; c++) {
                var w = WIDTHS[(r + c) % WIDTHS.length];
                html += c === 0
                    ? '<td><div class="skeleton-stack">' + line(w, 'skeleton-title') + line(40, 'skeleton-sm') + '</div></td>'
                    : '<td>' + line(clamp(w, 40, 90)) + '</td>';
            }
            html += '</tr>';
        }
        var tbody = document.createElement('tbody');
        tbody.className = 'skeleton-host';
        tbody.setAttribute('aria-hidden', 'true');
        tbody.innerHTML = html;
        table.appendChild(tbody);
    }

    function buildCards(grid) {
        var first = grid.firstElementChild;
        var colClass = first ? first.className : 'col-md-6';
        var count = clamp(grid.children.length || 6, 3, 6);
        var html = '';
        for (var i = 0; i < count; i++) {
            html += '<div class="' + colClass + ' skeleton-host" aria-hidden="true"><div class="skeleton-card">'
                + '<div class="d-flex gap-3 mb-3"><span class="skeleton skeleton-tile"></span><div class="skeleton-stack">'
                + '<span class="skeleton skeleton-pill"></span>' + line(WIDTHS[i % 8], 'skeleton-title')
                + line(55, 'skeleton-sm') + line(40, 'skeleton-sm') + '</div></div>'
                + line(100, 'skeleton-sm') + '</div></div>';
        }
        grid.insertAdjacentHTML('beforeend', html);
    }

    function buildList(list) {
        var count = clamp(list.children.length || 5, 3, 8);
        var leading = list.getAttribute('data-skeleton-leading') === 'tile' ? 'skeleton-tile' : 'skeleton-circle';
        var html = '';
        for (var i = 0; i < count; i++) {
            html += '<div class="skeleton-row skeleton-host" aria-hidden="true"><span class="skeleton ' + leading + '"></span>'
                + '<div class="skeleton-stack">' + line(WIDTHS[i % 8], 'skeleton-title') + line(45, 'skeleton-sm') + '</div></div>';
        }
        list.insertAdjacentHTML('beforeend', html);
    }

    /** @returns {boolean} true nếu trang có vùng skeleton */
    function showSkeletons() {
        var targets = document.querySelectorAll('[data-skeleton]:not(.is-loading)');
        if (!targets.length) {
            return document.querySelector('[data-skeleton]') !== null;
        }
        targets.forEach(function (el) {
            var type = el.getAttribute('data-skeleton');
            if (type === 'table') {
                buildTable(el);
            } else if (type === 'cards') {
                buildCards(el);
            } else {
                buildList(el);
            }
            el.classList.add('is-loading');
            el.setAttribute('aria-busy', 'true');
        });
        return true;
    }

    function hideSkeletons() {
        document.querySelectorAll('.skeleton-host').forEach(function (el) { el.remove(); });
        document.querySelectorAll('[data-skeleton].is-loading').forEach(function (el) {
            el.classList.remove('is-loading');
            el.removeAttribute('aria-busy');
        });
    }

    // =====================================================================
    // Nút gửi: spinner + chữ theo ngữ cảnh, khóa nút, khôi phục
    // =====================================================================
    function defaultLoadingText(form) {
        var override = form.querySelector('input[name="_method"]');
        var method = (override ? override.value : form.getAttribute('method') || 'GET').toUpperCase();
        if (method === 'DELETE') {
            return 'Đang xóa…';
        }
        return method === 'GET' ? 'Đang tải…' : 'Đang lưu…';
    }

    function setButtonsLoading(form, submitter) {
        var buttons = Array.prototype.slice.call(form.querySelectorAll('button[type="submit"], button:not([type])'));
        var main = submitter || buttons[0];

        if (main && !main.hasAttribute('data-no-disable')) {
            var text = main.getAttribute('data-loading-text') || defaultLoadingText(form);
            main.setAttribute('data-original-html', main.innerHTML);
            main.style.minWidth = main.offsetWidth + 'px';
            main.innerHTML = main.classList.contains('btn-icon')
                ? SPINNER + '<span class="visually-hidden">' + text + '</span>'
                : SPINNER + '<span>' + text + '</span>';
            main.classList.add('is-loading');
            main.setAttribute('aria-busy', 'true');
        }

        // Khóa sau khi trình duyệt đã lấy dữ liệu form (giữ name/value của nút được bấm).
        setTimeout(function () {
            buttons.forEach(function (button) {
                if (!button.hasAttribute('data-no-disable') && !button.disabled) {
                    button.disabled = true;
                    button.setAttribute('data-loading-disabled', '1');
                }
            });
        }, 0);
    }

    function restoreButtons(root) {
        root.querySelectorAll('[data-original-html]').forEach(function (button) {
            button.innerHTML = button.getAttribute('data-original-html');
            button.removeAttribute('data-original-html');
            button.classList.remove('is-loading');
            button.removeAttribute('aria-busy');
            button.style.minWidth = '';
        });
        root.querySelectorAll('[data-loading-disabled]').forEach(function (button) {
            button.disabled = false;
            button.removeAttribute('data-loading-disabled');
        });
        // Form có ô xác nhận: chưa tích thì khóa lại nút gửi
        root.querySelectorAll('input[type="checkbox"][data-confirm-gate]').forEach(function (checkbox) {
            syncConfirmGate(checkbox);
        });
    }

    function restoreForm(form) {
        restoreButtons(form);
        form.removeAttribute('data-submitting');
        form.removeAttribute('aria-busy');
    }

    function resetLoadingState() {
        stopPageLoading();
        hideSkeletons();
        restoreButtons(document);
        document.querySelectorAll('form[data-submitting]').forEach(restoreForm);
    }

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            resetLoadingState();
        }
    });

    // =====================================================================
    // Hộp xác nhận (modal) thay cho window.confirm
    // =====================================================================
    function confirmAction(message, okLabel) {
        var el = document.getElementById('bmb-confirm');
        if (!el || !hasBootstrap) {
            return Promise.resolve(window.confirm(message));
        }
        return new Promise(function (resolve) {
            var ok = el.querySelector('[data-confirm-ok]');
            var accepted = false;
            el.querySelector('#bmb-confirm-text').textContent = message;
            ok.textContent = okLabel || 'Đồng ý';
            var onOk = function () {
                accepted = true;
                window.bootstrap.Modal.getOrCreateInstance(el).hide();
            };
            ok.addEventListener('click', onOk);
            el.addEventListener('hidden.bs.modal', function () {
                ok.removeEventListener('click', onOk);
                resolve(accepted);
            }, { once: true });
            // Mặc định đặt focus vào nút "Không" để tránh bấm nhầm thao tác khó hoàn tác.
            el.addEventListener('shown.bs.modal', function () {
                el.querySelector('[data-bs-dismiss]').focus();
            }, { once: true });
            window.bootstrap.Modal.getOrCreateInstance(el).show();
        });
    }

    // =====================================================================
    // Modal form tải bằng AJAX
    // =====================================================================
    var modalEl = document.getElementById('bmb-modal');
    var modalDialog = modalEl ? modalEl.querySelector('.modal-dialog') : null;
    var modal = modalEl && hasBootstrap ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;

    function modalUrl(href) {
        var url = new URL(href, window.location.href);
        url.searchParams.set('modal', '1');
        return url.toString();
    }

    function modalPlaceholder() {
        return '<div class="modal-content" aria-busy="true"><div class="modal-header">'
            + '<span class="skeleton skeleton-title" style="width:45%"></span>'
            + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>'
            + '<div class="modal-body"><div class="skeleton-stack gap-3">'
            + line(30, 'skeleton-sm') + '<span class="skeleton" style="height:46px"></span>'
            + line(25, 'skeleton-sm') + '<span class="skeleton" style="height:46px"></span>'
            + line(35, 'skeleton-sm') + '<span class="skeleton" style="height:80px"></span>'
            + '</div><span class="visually-hidden">Đang tải biểu mẫu…</span></div></div>';
    }

    function focusFirstField(root) {
        // Ưu tiên ô đang báo lỗi đầu tiên; không có lỗi thì ô nhập đầu tiên.
        var field = root.querySelector('.is-invalid:not([type="hidden"]), [aria-invalid="true"]:not([type="hidden"])')
            || root.querySelector('input:not([type="hidden"]):not([disabled]):not([type="checkbox"]), select, textarea');
        if (field) {
            field.focus();
        }
    }

    function loadModal(href) {
        modalDialog.setAttribute('data-url', href);
        return fetch(modalUrl(href), {
            headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function (response) {
            if (response.status === 403) {
                throw new Error('forbidden');
            }
            if (response.status === 429) {
                throw new Error('throttled');
            }
            if (!response.ok || response.redirected) {
                throw new Error('fallback');
            }
            return response.text();
        }).then(function (html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var content = doc.querySelector('.modal-content');
            if (!content) {
                throw new Error('fallback');
            }
            modalDialog.replaceChildren(document.importNode(content, true));
            initMoney(modalDialog);
            initQuantities(modalDialog);
            initUnitSelects(modalDialog);
            initPostForms(modalDialog);
            // Bootstrap tự focus vào khung modal khi hiệu ứng mở kết thúc; đợi xong rồi mới đặt focus vào ô đầu tiên.
            if (modalEl.classList.contains('show') && !modalEl.hasAttribute('data-opening')) {
                focusFirstField(modalDialog);
            } else {
                modalEl.addEventListener('shown.bs.modal', function () { focusFirstField(modalDialog); }, { once: true });
            }
        });
    }

    function openModal(href) {
        if (!modal) {
            window.location.href = href;
            return;
        }
        modalDialog.innerHTML = modalPlaceholder();
        modalEl.setAttribute('data-opening', '1');
        modalEl.addEventListener('shown.bs.modal', function () { modalEl.removeAttribute('data-opening'); }, { once: true });
        modal.show();
        loadModal(href).catch(function (error) {
            if (error.message === 'forbidden') {
                modal.hide();
                toast('Bạn không có quyền thực hiện thao tác này, hoặc chương trình đã bị khóa.', 'error');
                return;
            }
            if (error.message === 'throttled') {
                modal.hide();
                toast('Bạn thao tác quá nhanh. Vui lòng đợi một chút rồi thử lại.', 'error');
                return;
            }
            // Phiên hết hạn hoặc lỗi lạ: mở trang đầy đủ như khi không có JS.
            window.location.href = href;
        });
    }

    if (modalEl) {
        // Đóng modal (nút ×, Hủy, Esc, bấm ra ngoài) đóng ngay, không hỏi lại dù form đã sửa (theo yêu cầu 14/10/2026).
        modalEl.addEventListener('hidden.bs.modal', function () {
            modalDialog.innerHTML = '';
            modalDialog.removeAttribute('data-url');
        });
    }

    // =====================================================================
    // Cập nhật vùng dữ liệu (data-region) mà không tải lại trang
    // =====================================================================
    function refreshRegions() {
        var regions = document.querySelectorAll('[data-region][id]');
        if (!regions.length) {
            window.location.reload();
            return Promise.resolve();
        }
        return fetch(window.location.href, {
            headers: { 'Accept': 'text/html' },
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function (response) {
            if (!response.ok || response.redirected) {
                throw new Error('reload');
            }
            return response.text();
        }).then(function (html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var focusedId = document.activeElement ? document.activeElement.id : null;
            hideTip(); // biểu đồ sắp được thay bằng bản mới
            regions.forEach(function (region) {
                var fresh = doc.getElementById(region.id);
                if (fresh) {
                    region.replaceWith(document.importNode(fresh, true));
                }
            });
            if (focusedId && document.getElementById(focusedId)) {
                document.getElementById(focusedId).focus({ preventScroll: true });
            }
            initMoney(document);
            initQuantities(document);
            initUnitSelects(document);
            initLazyImages(document);
        }).catch(function () {
            window.location.reload();
        });
    }

    // =====================================================================
    // Gửi form bằng AJAX (form có data-ajax)
    // =====================================================================
    function clearErrors(form) {
        form.querySelectorAll('.ajax-feedback').forEach(function (el) { el.remove(); });
        form.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
            el.removeAttribute('aria-invalid');
        });
        var alertBox = form.querySelector('[data-form-alert]');
        if (alertBox) {
            alertBox.innerHTML = '';
        }
    }

    function showFormAlert(form, message) {
        var alertBox = form.querySelector('[data-form-alert]');
        if (alertBox) {
            alertBox.innerHTML = '<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><div>'
                + escapeHtml(message) + '</div></div>';
        } else {
            toast(message, 'error');
        }
    }

    function showErrors(form, errors, summary) {
        var unplaced = [];
        Object.keys(errors).forEach(function (name) {
            var message = errors[name][0];
            var fields = form.querySelectorAll('[name="' + name + '"]');
            if (!fields.length && name.indexOf('.') !== -1) {
                // Lỗi của mảng ("settings.webhook_url") ↔ ô "settings[webhook_url]" — chỉ ô đang hiện.
                var bracket = name.replace(/\.([^.]+)/g, '[$1]');
                fields = Array.prototype.filter.call(form.querySelectorAll('[name="' + bracket + '"]'), function (f) { return !f.disabled; });
            }
            if (!fields.length) {
                // Ô có tên khác khóa lỗi (ví dụ join_urls[slack] ↔ join_url): khai báo bằng data-error-name.
                fields = Array.prototype.filter.call(form.querySelectorAll('[data-error-name="' + name + '"]'), function (f) { return !f.disabled; });
            }
            if (!fields.length) {
                unplaced.push(message);
                return;
            }
            // Ô ẩn làm "proxy" (trình soạn thảo, ô nhập thẻ) trỏ tới phần tử hiển thị qua data-error-target.
            var visual = function (field) {
                var id = field.getAttribute('data-error-target');
                return (id && document.getElementById(id)) || field;
            };
            fields.forEach(function (field) {
                visual(field).classList.add('is-invalid');
                field.setAttribute('aria-invalid', 'true');
            });
            var last = fields[fields.length - 1];
            var shown = visual(last);
            var anchor = shown !== last ? last : (last.closest('.input-group') || (last.type === 'checkbox' || last.type === 'radio'
                ? last.closest('.form-check, .choice-card, fieldset') : null) || last);
            var feedback = document.createElement('div');
            feedback.className = 'invalid-feedback d-block ajax-feedback';
            feedback.id = (last.id || name) + '_ajax_error';
            feedback.textContent = message;
            anchor.insertAdjacentElement('afterend', feedback);
            last.setAttribute('aria-describedby', ((last.getAttribute('aria-describedby') || '') + ' ' + feedback.id).trim());
        });
        if (unplaced.length) {
            showFormAlert(form, unplaced.join(' '));
        } else {
            showFormAlert(form, summary || 'Chưa lưu được. Vui lòng sửa các mục được đánh dấu.');
        }
        focusFirstField(form);
        announce('Có lỗi trong biểu mẫu, vui lòng kiểm tra lại.');
    }

    function buildFormData(form, submitter) {
        var data;
        try {
            data = new FormData(form, submitter || undefined);
        } catch (e) {
            data = new FormData(form);
            if (submitter && submitter.name) {
                data.append(submitter.name, submitter.value);
            }
        }
        form.querySelectorAll('[data-money]').forEach(function (input) {
            data.set(input.name, digitsOnly(input.value));
        });
        return data;
    }

    function ajaxSubmit(form, submitter) {
        var inModal = modalDialog && modalDialog.contains(form);
        var hasImages = form.querySelector('[data-image-input]') !== null;

        form.setAttribute('data-submitting', '1');
        form.setAttribute('aria-busy', 'true');
        setButtonsLoading(form, submitter);
        clearErrors(form);
        startPageLoading(hasImages ? 'Đang xử lý ảnh và lưu…' : 'Đang lưu…', false);

        // Ảnh lớn được thu nhỏ trên máy trước (tải lên nhanh hơn khi dùng 4G); máy chủ vẫn kiểm tra lại toàn bộ.
        prepareImages(form, buildFormData(form, submitter)).then(function (body) {
            return fetch(form.action, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : ''
                }
            });
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                return { status: response.status, data: data };
            });
        }).then(function (result) {
            var data = result.data;
            stopPageLoading();

            if (result.status >= 200 && result.status < 300) {
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                toast(data.message || 'Đã lưu.');
                if (data.keepOpen && inModal) {
                    // "Lưu và thêm tiếp": giữ modal, xóa form để nhập mục tiếp theo.
                    restoreForm(form);
                    form.reset();
                    initMoney(form);
                    initQuantities(form);
                    initUnitSelects(form);
                    focusFirstField(form);
                } else {
                    if (inModal) {
                        modal.hide();
                    }
                }
                if (data.refresh !== false) {
                    refreshRegions();
                }
                return;
            }

            restoreForm(form);
            if (result.status === 422 && data.errors) {
                showErrors(form, data.errors);
            } else if (result.status === 422 && data.message) {
                // Lỗi nghiệp vụ không gắn với ô nhập (ví dụ gửi thử kênh thông báo thất bại): báo rõ lý do.
                showFormAlert(form, data.message);
                refreshRegions();
            } else if (result.status === 409 && data.reload && inModal) {
                // Dữ liệu vừa bị người khác sửa: tải lại form để thấy giá trị mới nhất.
                loadModal(modalDialog.getAttribute('data-url')).then(function () {
                    showFormAlert(modalDialog.querySelector('form'), data.message);
                });
                refreshRegions();
            } else if (result.status === 401 || result.status === 419) {
                toast('Phiên đăng nhập đã hết hạn. Trang sẽ tải lại để bạn đăng nhập.', 'error');
                setTimeout(function () { window.location.reload(); }, 1800);
            } else if (result.status === 429) {
                // Giới hạn chống spam theo tài khoản: báo thời gian chờ, giữ nguyên dữ liệu đã nhập.
                showFormAlert(form, data.message || 'Bạn thao tác quá nhanh. Vui lòng đợi một chút rồi thử lại.');
            } else if (result.status === 403) {
                showFormAlert(form, data.message && data.message !== 'This action is unauthorized.'
                    ? data.message : 'Bạn không có quyền thực hiện thao tác này.');
            } else {
                showFormAlert(form, 'Có lỗi xảy ra, chưa lưu được. Vui lòng thử lại.');
            }
        }).catch(function () {
            stopPageLoading();
            restoreForm(form);
            showFormAlert(form, 'Không kết nối được máy chủ. Kiểm tra mạng rồi thử lại.');
        });
    }

    // =====================================================================
    // Tooltip biểu đồ: data-tip='{"t": tiêu đề, "r": [[nhãn, giá trị, tone]], "n": ghi chú}'
    // Hiện khi rê chuột, focus bằng bàn phím hoặc chạm (phần tử có tabindex). Chỉ là phần bổ sung:
    // giá trị luôn có ở nhãn hoặc bảng "Xem dạng bảng".
    // =====================================================================
    var tipEl = null;
    var tipOwner = null;

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined) {
            node.textContent = text; // dữ liệu (tên khoản chi...) không bao giờ chèn bằng innerHTML
        }
        return node;
    }

    function showTip(owner) {
        var data;
        try {
            data = JSON.parse(owner.getAttribute('data-tip'));
        } catch (e) {
            return;
        }
        if (!tipEl) {
            tipEl = el('div', 'chart-tip');
            tipEl.setAttribute('aria-hidden', 'true'); // trình đọc màn hình đã có aria-label/bảng
            document.body.appendChild(tipEl);
        }
        tipEl.replaceChildren();
        // Bỏ tiêu đề khi nó trùng nhãn của dòng duy nhất (thanh ngang); giữ cho cột theo thời gian.
        if (!data.r || data.r.length !== 1 || data.r[0][0] !== data.t) {
            tipEl.appendChild(el('div', 'chart-tip-title', data.t));
        }
        (data.r || []).forEach(function (row) {
            var line = el('div', 'chart-tip-row');
            var key = el('span', 'chart-tip-key' + (row[2] ? ' tone-' + row[2] : ''));
            line.appendChild(key);
            line.appendChild(el('strong', null, row[1])); // giá trị đứng trước, nhãn theo sau
            line.appendChild(el('span', null, row[0]));
            tipEl.appendChild(line);
        });
        if (data.n) {
            tipEl.appendChild(el('div', 'chart-tip-note', data.n));
        }

        var rect = owner.getBoundingClientRect();
        tipEl.style.left = '0px';
        tipEl.style.top = '0px';
        tipEl.classList.add('is-visible');
        var w = tipEl.offsetWidth;
        var h = tipEl.offsetHeight;
        var left = Math.min(Math.max(8, rect.left + rect.width / 2 - w / 2), window.innerWidth - w - 8);
        var top = rect.top - h - 8;
        if (top < 8) {
            top = rect.bottom + 8;
        }
        tipEl.style.left = left + 'px';
        tipEl.style.top = top + 'px';
        tipOwner = owner;
    }

    function hideTip() {
        if (tipEl) {
            tipEl.classList.remove('is-visible');
        }
        tipOwner = null;
    }

    document.addEventListener('pointerover', function (event) {
        var owner = event.target.closest && event.target.closest('[data-tip]');
        if (owner && owner !== tipOwner) {
            showTip(owner);
        } else if (!owner && tipOwner && event.pointerType === 'mouse') {
            hideTip();
        }
    });
    document.addEventListener('focusin', function (event) {
        var owner = event.target.closest && event.target.closest('[data-tip]');
        if (owner) {
            showTip(owner);
        } else {
            hideTip();
        }
    });
    document.addEventListener('focusout', function (event) {
        if (event.target === tipOwner) {
            hideTip();
        }
    });
    window.addEventListener('scroll', hideTip, { passive: true });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            hideTip();
        }
    });

    // =====================================================================
    // Nút xem/ẩn mật khẩu (data-toggle-password="id ô")
    // =====================================================================
    function setPasswordVisible(toggle, show) {
        var input = document.getElementById(toggle.getAttribute('data-toggle-password'));
        if (!input) {
            return;
        }
        input.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
        toggle.setAttribute('aria-label', show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
        toggle.querySelector('.bi').className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
    }

    /** Ẩn lại mật khẩu khi gửi form: không để lộ trên màn hình, trình quản lý mật khẩu vẫn nhận đúng ô. */
    function hidePasswords(form) {
        form.querySelectorAll('[data-toggle-password][aria-pressed="true"]').forEach(function (toggle) {
            setPasswordVisible(toggle, false);
        });
    }

    // =====================================================================
    // Kiểm tra phía trình duyệt TRƯỚC khi gửi (giảm yêu cầu thừa xuống máy chủ).
    // Máy chủ vẫn kiểm tra lại mọi thứ — đây chỉ là lớp nhắc sớm cho người dùng.
    // Dựa trên thuộc tính HTML: required, minlength, min/max/step, type=email, pattern,
    // data-validate="email-or-phone", data-required (ô ẩn của trình soạn thảo).
    // Bỏ qua: form tìm kiếm/lọc (GET), form có data-no-validate, nút có formnovalidate.
    // =====================================================================
    var PHONE_RE = /^(?:\+84|0)\d{9,10}$/;
    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    function fieldLabel(form, field) {
        var text = '';
        if (field.type === 'radio' || field.type === 'checkbox') {
            var legend = field.closest('fieldset') && field.closest('fieldset').querySelector('legend');
            text = legend ? legend.textContent : '';
        }
        if (!text && field.id) {
            var label = form.querySelector('label[for="' + field.id + '"]')
                || (field.getAttribute('data-error-target') && form.querySelector('label[for="' + field.getAttribute('data-error-target') + '"]'));
            text = label ? label.textContent : '';
        }
        text = (text || field.getAttribute('aria-label') || 'trường này').replace(/\*|\(bắt buộc\)/g, '').replace(/\s+/g, ' ').trim();
        return text.charAt(0).toLowerCase() + text.slice(1);
    }

    function fieldError(form, field) {
        var value = (field.value || '').trim();
        var label = fieldLabel(form, field);
        var required = field.required || field.getAttribute('data-required') === '1';

        if (field.type === 'radio') {
            var group = form.querySelectorAll('input[type="radio"][name="' + field.name + '"]');
            var anyRequired = Array.prototype.some.call(group, function (r) { return r.required; });
            var checked = Array.prototype.some.call(group, function (r) { return r.checked; });
            return anyRequired && !checked ? 'Vui lòng chọn ' + label + '.' : null;
        }
        if (field.type === 'checkbox') {
            return field.required && !field.checked ? 'Vui lòng đánh dấu ' + label + '.' : null;
        }
        if (field.type === 'file') {
            return required && !field.files.length ? 'Vui lòng chọn ' + label + '.' : null;
        }
        if (value === '') {
            if (!required) {
                return null;
            }
            return field.tagName === 'SELECT' ? 'Vui lòng chọn ' + label + '.' : 'Vui lòng nhập ' + label + '.';
        }
        var min = parseInt(field.getAttribute('minlength'), 10);
        if (min && value.length < min) {
            return 'Vui lòng nhập ' + label + ' ít nhất ' + min + ' ký tự.';
        }
        var max = parseInt(field.getAttribute('maxlength'), 10);
        if (max && value.length > max) {
            return 'Vui lòng nhập ' + label + ' tối đa ' + max + ' ký tự.';
        }
        if (field.type === 'email' && !EMAIL_RE.test(value)) {
            return 'Email chưa đúng định dạng (ví dụ ten@gmail.com).';
        }
        if (field.getAttribute('data-validate') === 'email-or-phone') {
            var ok = value.indexOf('@') !== -1 ? EMAIL_RE.test(value) : PHONE_RE.test(value.replace(/[\s.\-()]/g, ''));
            if (!ok) {
                return 'Vui lòng nhập email hoặc số điện thoại hợp lệ (ví dụ 0901 234 567).';
            }
        }
        if (field.type === 'number' && field.validity) {
            if (field.validity.badInput || isNaN(Number(value))) {
                return 'Vui lòng nhập số cho ' + label + '.';
            }
            if (field.validity.rangeUnderflow) {
                return label.charAt(0).toUpperCase() + label.slice(1) + ' không được nhỏ hơn ' + field.min + '.';
            }
            if (field.validity.rangeOverflow) {
                return label.charAt(0).toUpperCase() + label.slice(1) + ' không được lớn hơn ' + field.max + '.';
            }
            if (field.validity.stepMismatch) {
                return 'Vui lòng nhập số nguyên cho ' + label + '.';
            }
        }
        if (field.validity && field.validity.patternMismatch) {
            return field.getAttribute('data-pattern-message') || ('Giá trị của ' + label + ' chưa đúng định dạng.');
        }
        return null;
    }

    function shouldValidate(form, submitter) {
        if ((form.getAttribute('method') || 'get').toLowerCase() === 'get' || form.hasAttribute('data-no-validate')) {
            return false;
        }
        return !(submitter && submitter.hasAttribute('formnovalidate'));
    }

    function clientErrors(form) {
        var errors = {};
        Array.prototype.forEach.call(form.elements, function (field) {
            if (!field.name || field.disabled || errors[field.name] || /^(submit|button|reset)$/.test(field.type)) {
                return;
            }
            if (field.type === 'hidden' && field.getAttribute('data-required') !== '1') {
                return;
            }
            // Trường nằm trong vùng đang ẩn (ví dụ tùy chọn chưa bật) không kiểm tra.
            if (field.type !== 'hidden' && field.type !== 'radio' && field.type !== 'checkbox' && field.offsetParent === null) {
                return;
            }
            var message = fieldError(form, field);
            if (message) {
                errors[field.name] = [message];
            }
        });
        return errors;
    }

    // Chạy ở pha capture: trước mọi xử lý gửi khác (AJAX, hộp xác nhận, màn chờ).
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!shouldValidate(form, event.submitter)) {
            return;
        }
        var errors = clientErrors(form);
        if (Object.keys(errors).length) {
            event.preventDefault();
            event.stopImmediatePropagation();
            clearErrors(form);
            showErrors(form, errors, 'Vui lòng kiểm tra các mục được đánh dấu.');
        }
    }, true);

    // Sửa xong một ô đang báo lỗi thì bỏ đánh dấu ngay.
    function revalidate(event) {
        var field = event.target;
        if (event.type === 'trix-change') {
            field = document.getElementById(field.getAttribute('input'));
            if (!field) {
                return;
            }
        }
        var form = field.form;
        if (!form || !field.name || field.getAttribute('aria-invalid') !== 'true') {
            return;
        }
        if (!fieldError(form, field)) {
            form.querySelectorAll('[name="' + field.name + '"]').forEach(function (el) {
                el.removeAttribute('aria-invalid');
                el.classList.remove('is-invalid');
                var target = el.getAttribute('data-error-target') && document.getElementById(el.getAttribute('data-error-target'));
                if (target) {
                    target.classList.remove('is-invalid');
                }
            });
            var group = form.querySelectorAll('[name="' + field.name + '"]');
            var last = group[group.length - 1];
            var feedback = document.getElementById((last.id || field.name) + '_ajax_error');
            if (feedback) {
                feedback.remove();
            }
        }
    }
    document.addEventListener('input', revalidate);
    document.addEventListener('change', revalidate);
    document.addEventListener('trix-change', revalidate);

    // =====================================================================
    // Bắt sự kiện submit
    // =====================================================================
    document.addEventListener('submit', function (event) {
        var form = event.target;
        hidePasswords(form);

        if (form.getAttribute('data-submitting') === '1') {
            event.preventDefault();
            return;
        }

        var message = form.getAttribute('data-confirm');
        if (message && form.getAttribute('data-confirmed') !== '1') {
            event.preventDefault();
            var submitter = event.submitter;
            confirmAction(message, form.getAttribute('data-confirm-label')).then(function (ok) {
                if (ok) {
                    form.setAttribute('data-confirmed', '1');
                    if (form.requestSubmit) {
                        form.requestSubmit(submitter || undefined);
                    } else {
                        form.submit();
                    }
                }
            });
            return;
        }
        form.removeAttribute('data-confirmed');

        if (form.hasAttribute('data-ajax') && window.fetch && window.FormData) {
            event.preventDefault();
            ajaxSubmit(form, event.submitter);
            return;
        }

        // Gửi form thường (chuyển trang)
        normalizeMoneyInputs(form);
        form.setAttribute('data-submitting', '1');
        form.setAttribute('aria-busy', 'true');
        setButtonsLoading(form, event.submitter);

        var hasSkeleton = form.getAttribute('role') === 'search' && showSkeletons();
        startPageLoading(hasSkeleton ? 'Đang tải dữ liệu…' : 'Đang gửi, vui lòng đợi…', !hasSkeleton);
    });

    // =====================================================================
    // Bắt sự kiện click: mở modal, loader chuyển trang, tiện ích nhỏ
    // =====================================================================
    document.addEventListener('click', function (event) {
        var target = event.target;

        // Tiện ích (delegation để vẫn chạy với nội dung vừa tải bằng AJAX)
        var printButton = target.closest('[data-print]');
        if (printButton) {
            window.print();
            return;
        }
        var toggle = target.closest('[data-toggle-password]');
        if (toggle) {
            var input = document.getElementById(toggle.getAttribute('data-toggle-password'));
            setPasswordVisible(toggle, input.type === 'password');
            return;
        }
        var showDates = target.closest('[data-show-dates]');
        if (showDates) {
            showDates.closest('form').querySelectorAll('[data-date-field]').forEach(function (field) {
                field.classList.remove('d-none');
            });
            showDates.remove();
            return;
        }
        var filterToggle = target.closest('[data-filter-toggle]');
        if (filterToggle) {
            var filterBox = document.getElementById(filterToggle.getAttribute('data-filter-toggle'));
            if (filterBox) {
                filterBox.classList.add('is-open');
                filterToggle.setAttribute('aria-expanded', 'true');
                filterToggle.hidden = true;
                var firstField = filterBox.querySelector('input, select');
                if (firstField) {
                    firstField.focus({ preventScroll: true });
                    filterBox.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
            }
            return;
        }

        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        var link = target.closest('a[href]');
        if (!link) {
            return;
        }

        // Mở form trong modal thay vì chuyển trang
        if (link.hasAttribute('data-modal') && modal) {
            event.preventDefault();
            openModal(link.href);
            return;
        }

        if (link.hasAttribute('download') || link.hasAttribute('data-no-loading') || link.hasAttribute('data-bs-toggle')) {
            return;
        }
        if (link.target && link.target !== '_self') {
            return;
        }
        var url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin || !/^https?:$/.test(url.protocol)) {
            return;
        }
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) {
            return;
        }
        var reloadsList = link.closest('.pagination, .chip-row') !== null && showSkeletons();
        startPageLoading(reloadsList ? 'Đang tải dữ liệu…' : null, !reloadsList);
    });

    // =====================================================================
    // Ô đơn vị "Khác"
    // (Không cảnh báo khi rời trang/đóng modal có form chưa lưu — đã bỏ theo yêu cầu 14/10/2026.)
    // =====================================================================
    function syncUnitSelect(select) {
        var other = document.getElementById(select.getAttribute('data-unit-select'));
        if (!other) {
            return;
        }
        var show = select.value === (select.getAttribute('data-other-value') || 'other');
        other.classList.toggle('d-none', !show);
        other.querySelector('input').required = show;
    }

    function initUnitSelects(root) {
        (root || document).querySelectorAll('[data-unit-select]').forEach(syncUnitSelect);
    }

    document.addEventListener('change', function (event) {
        if (event.target.matches && event.target.matches('[data-unit-select]')) {
            syncUnitSelect(event.target);
        }
    });

    // =====================================================================
    // Bài viết: trình soạn thảo, ảnh (xem trước + thu nhỏ trước khi gửi), ô nhập thẻ, album
    // Thư viện ngoài (Trix, PhotoSwipe) chỉ được tải khi trang thực sự cần.
    // =====================================================================
    var assetBase = (document.querySelector('script[src*="/js/app.js"]') || {}).src;
    assetBase = assetBase ? assetBase.replace(/js\/app\.js.*$/, '') : '/';
    var loadedAssets = {};

    /** Tải một file JS/CSS một lần duy nhất. */
    function loadAsset(path) {
        if (!loadedAssets[path]) {
            loadedAssets[path] = new Promise(function (resolve, reject) {
                var isCss = /\.css$/.test(path);
                var el = document.createElement(isCss ? 'link' : 'script');
                if (isCss) {
                    el.rel = 'stylesheet';
                    el.href = assetBase + path;
                } else {
                    el.src = assetBase + path;
                }
                el.onload = resolve;
                el.onerror = reject;
                document.head.appendChild(el);
            });
        }
        return loadedAssets[path];
    }

    // --- Trình soạn thảo (Trix): thay <textarea data-rich-editor> bằng trình soạn thảo, giữ tên trường ---
    var TRIX_LANG = {
        attachFiles: 'Đính kèm', bold: 'In đậm', bullets: 'Danh sách chấm', byte: 'Byte', bytes: 'Byte',
        captionPlaceholder: 'Thêm chú thích…', code: 'Mã', heading1: 'Tiêu đề nhỏ', indent: 'Tăng thụt lề',
        italic: 'In nghiêng', link: 'Liên kết', numbers: 'Danh sách số', outdent: 'Giảm thụt lề', quote: 'Trích dẫn',
        redo: 'Làm lại', remove: 'Bỏ', strike: 'Gạch ngang', undo: 'Hoàn tác', unlink: 'Bỏ liên kết', url: 'Địa chỉ',
        urlPlaceholder: 'Dán địa chỉ liên kết (https://…)', GB: 'GB', KB: 'KB', MB: 'MB', PB: 'PB', TB: 'TB'
    };

    function initRichEditors(root) {
        var areas = (root || document).querySelectorAll('textarea[data-rich-editor]:not([data-enhanced])');
        if (!areas.length) {
            return;
        }
        Promise.all([loadAsset('vendor/trix/trix.css'), loadAsset('vendor/trix/trix.umd.min.js')]).then(function () {
            // Đặt ngôn ngữ TRƯỚC khi tạo trình soạn thảo (thanh công cụ dựng từ cấu hình này).
            if (window.Trix && window.Trix.config && window.Trix.config.lang) {
                Object.assign(window.Trix.config.lang, TRIX_LANG);
            }
            areas.forEach(function (area) {
                area.setAttribute('data-enhanced', '1');
                var label = document.querySelector('label[for="' + area.id + '"]');
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = area.name;
                hidden.id = area.id + '_value';
                hidden.value = area.value;
                var editor = document.createElement('trix-editor');
                editor.id = area.id;
                editor.setAttribute('input', hidden.id);
                editor.className = 'form-control trix-content' + (area.classList.contains('is-invalid') ? ' is-invalid' : '');
                editor.setAttribute('aria-label', label ? label.textContent.replace(/\s*\*.*$/, '').trim() : 'Nội dung');
                if (area.getAttribute('aria-describedby')) {
                    editor.setAttribute('aria-describedby', area.getAttribute('aria-describedby'));
                }
                hidden.setAttribute('data-error-target', editor.id);
                if (area.required) {
                    hidden.setAttribute('data-required', '1');
                }
                area.replaceWith(editor);
                editor.insertAdjacentElement('afterend', hidden);
                if (label) {
                    label.addEventListener('click', function () { editor.focus(); });
                }
            });
        }).catch(function () {
            // Không tải được trình soạn thảo: vẫn dùng ô văn bản thường (máy chủ lọc HTML).
        });
    }

    // Không nhận ảnh/file kéo-thả hoặc dán vào nội dung: ảnh phải đi qua phần "Ảnh" (kiểm tra + WebP).
    document.addEventListener('trix-file-accept', function (event) {
        event.preventDefault();
        toast('Thêm ảnh ở phần “Ảnh” bên dưới để ảnh được kiểm tra và tối ưu.', 'error');
    });
    document.addEventListener('trix-attachment-add', function (event) {
        if (event.attachment && event.attachment.remove) {
            event.attachment.remove();
        }
    });

    // --- Ảnh: xem trước, giới hạn số ảnh, thu nhỏ trước khi gửi ---
    var IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    var RESIZE_MAX_EDGE = 2560;              // máy chủ cần tối đa 1600px; giữ dư để ảnh vẫn nét
    var RESIZE_ABOVE_BYTES = 1.5 * 1024 * 1024;

    function setFiles(input, files) {
        try {
            var transfer = new DataTransfer();
            files.forEach(function (f) { transfer.items.add(f); });
            input.files = transfer.files;
        } catch (e) {
            input.value = '';
        }
    }

    /** Nút × đặt góc ảnh xem trước. */
    function removeButton(label, onClick) {
        var button = el('button', 'preview-remove');
        button.type = 'button';
        button.setAttribute('aria-label', label);
        button.title = label;
        button.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
        button.addEventListener('click', onClick);
        return button;
    }

    /** Ảnh mới chọn cho album: hiện xem trước, mỗi ảnh có nút × để bỏ khỏi danh sách gửi. */
    function renderNewGallery(input) {
        var list = input.closest('fieldset') && input.closest('fieldset').querySelector('[data-preview-list]');
        if (!list) {
            return;
        }
        list.querySelectorAll('img[src^="blob:"]').forEach(function (img) { URL.revokeObjectURL(img.src); });
        list.replaceChildren();
        (input.bmbFiles || []).forEach(function (file, index) {
            var li = el('li', 'album-editor-item');
            var tile = el('div', 'preview-tile');
            var img = document.createElement('img');
            img.alt = 'Ảnh mới ' + (index + 1) + ' (chưa lưu)';
            img.src = URL.createObjectURL(file);
            tile.appendChild(img);
            tile.appendChild(removeButton('Bỏ ảnh mới ' + (index + 1), function () {
                input.bmbFiles.splice(index, 1);
                setFiles(input, input.bmbFiles);
                renderNewGallery(input);
                announce('Đã bỏ ảnh khỏi danh sách tải lên.');
                input.focus();
            }));
            li.appendChild(tile);
            li.appendChild(el('span', 'badge-new', 'Mới · ' + (file.size / 1048576).toFixed(1) + ' MB'));
            list.appendChild(li);
        });
    }

    /** Ảnh đại diện mới chọn: thay khung xem trước; nút × bỏ ảnh vừa chọn và trả lại ảnh cũ. */
    function renderNewCover(input, box) {
        if (!box.bmbOriginal) {
            box.bmbOriginal = Array.prototype.slice.call(box.childNodes);
        }
        var file = (input.bmbFiles || [])[0];
        if (!file) {
            box.replaceChildren.apply(box, box.bmbOriginal);
            box.classList.remove('has-new');
            return;
        }
        var img = document.createElement('img');
        img.alt = 'Ảnh đại diện mới (chưa lưu)';
        img.src = URL.createObjectURL(file);
        box.replaceChildren(img, removeButton('Bỏ ảnh đại diện vừa chọn', function () {
            URL.revokeObjectURL(img.src);
            input.bmbFiles = [];
            input.value = '';
            renderNewCover(input, box);
            announce('Đã bỏ ảnh đại diện vừa chọn.');
            input.focus();
        }));
        box.classList.add('has-new');
    }

    function onImageInputChange(input) {
        var files = Array.prototype.slice.call(input.files || []);
        var rejected = files.filter(function (f) { return IMAGE_TYPES.indexOf(f.type) === -1; });
        files = files.filter(function (f) { return IMAGE_TYPES.indexOf(f.type) !== -1; });
        if (rejected.length) {
            toast('Bỏ qua ' + rejected.length + ' file không phải ảnh JPG, PNG hoặc WebP.', 'error');
        }
        var max = input.hasAttribute('data-max-files') ? parseInt(input.getAttribute('data-max-files'), 10) : (input.multiple ? 99 : 1);
        if (files.length > max) {
            toast('Mỗi bài tối đa còn thêm được ' + max + ' ảnh. Chỉ giữ ' + max + ' ảnh đầu tiên.', 'error');
            files = files.slice(0, max);
        }
        if (rejected.length || files.length !== (input.files || []).length) {
            setFiles(input, files);
        }
        input.bmbFiles = files;

        var picker = input.closest('[data-image-picker]');
        var box = picker && picker.querySelector('[data-preview]');
        if (box) {
            renderNewCover(input, box);
        } else {
            renderNewGallery(input);
        }
    }

    /**
     * Ảnh đã lưu: thay ô tick "Xóa ảnh" (vẫn dùng được khi tắt JS) bằng nút × trên ảnh.
     * Bấm × → ảnh mờ đi, sẽ bị xóa khi lưu; bấm lại → hoàn tác.
     */
    function initExistingImageRemovers(root) {
        (root || document).querySelectorAll('[data-existing-image]:not([data-remover])').forEach(function (holder) {
            var item = holder.closest('.album-editor-item') || holder.closest('[data-image-picker]');
            var checkbox = item && item.querySelector('input[name="remove_images[]"], input[name="remove_cover"]');
            if (!checkbox) {
                return;
            }
            holder.setAttribute('data-remover', '1');
            var tile = holder.classList.contains('preview-tile') ? holder : holder.querySelector('.preview-tile');
            var isCover = checkbox.name === 'remove_cover';
            checkbox.closest('.form-check').classList.add('d-none');

            var sync = function () {
                var removing = checkbox.checked;
                tile.classList.toggle('is-removed', removing);
                button.setAttribute('aria-pressed', removing ? 'true' : 'false');
                button.setAttribute('aria-label', removing ? 'Giữ lại ảnh (hoàn tác)' : (isCover ? 'Xóa ảnh đại diện' : 'Xóa ảnh này'));
                button.title = button.getAttribute('aria-label');
                button.querySelector('.bi').className = 'bi ' + (removing ? 'bi-arrow-counterclockwise' : 'bi-x-lg');
                note.hidden = !removing;
            };
            var button = removeButton('', function () {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                sync();
                announce(checkbox.checked ? 'Ảnh sẽ bị xóa khi lưu.' : 'Đã giữ lại ảnh.');
            });
            var note = el('span', 'preview-removed-note', 'Sẽ xóa khi lưu');
            tile.appendChild(button);
            tile.appendChild(note);
            sync();
        });
    }

    document.addEventListener('change', function (event) {
        if (event.target.matches && event.target.matches('[data-image-input]')) {
            onImageInputChange(event.target);
        }
    });

    /** Thu nhỏ một ảnh lớn bằng canvas (giữ đúng hướng xoay của ảnh chụp điện thoại). Lỗi → giữ file gốc. */
    function shrinkImage(file) {
        if (!window.createImageBitmap || IMAGE_TYPES.indexOf(file.type) === -1) {
            return Promise.resolve(file);
        }
        return createImageBitmap(file, { imageOrientation: 'from-image' }).then(function (bitmap) {
            var scale = Math.min(1, RESIZE_MAX_EDGE / Math.max(bitmap.width, bitmap.height));
            if (scale === 1 && file.size <= RESIZE_ABOVE_BYTES) {
                return file;
            }
            var canvas = document.createElement('canvas');
            canvas.width = Math.round(bitmap.width * scale);
            canvas.height = Math.round(bitmap.height * scale);
            canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            return new Promise(function (resolve) {
                canvas.toBlob(function (blob) {
                    if (!blob || blob.size >= file.size) {
                        resolve(file);
                        return;
                    }
                    var ext = blob.type === 'image/webp' ? '.webp' : '.jpg';
                    resolve(new File([blob], file.name.replace(/\.[^.]+$/, '') + ext, { type: blob.type }));
                }, 'image/webp', 0.9);
            });
        }).catch(function () { return file; });
    }

    /** Thay các file ảnh trong FormData bằng bản đã thu nhỏ. */
    function prepareImages(form, data) {
        var inputs = form.querySelectorAll('[data-image-input]');
        if (!inputs.length || !window.Promise) {
            return Promise.resolve(data);
        }
        var jobs = Array.prototype.map.call(inputs, function (input) {
            var files = Array.prototype.slice.call(input.files || []);
            return Promise.all(files.map(shrinkImage)).then(function (shrunk) {
                if (!files.length) {
                    return;
                }
                data.delete(input.name);
                shrunk.forEach(function (file) { data.append(input.name, file, file.name); });
            });
        });
        return Promise.all(jobs).then(function () { return data; });
    }

    // --- Ô nhập thẻ: Enter / Tab / dấu phẩy để hoàn tất một thẻ, Backspace xóa thẻ cuối ---
    function initTagInputs(root) {
        (root || document).querySelectorAll('input[data-tags-input]:not([data-enhanced])').forEach(function (source) {
            source.setAttribute('data-enhanced', '1');
            var max = parseInt(source.getAttribute('data-max-tags') || '10', 10);
            var tags = source.value.split(/[,;]/).map(function (t) { return t.trim(); }).filter(Boolean);

            var field = el('div', 'form-control tags-field' + (source.classList.contains('is-invalid') ? ' is-invalid' : ''));
            field.id = source.id + '_field';
            var typing = document.createElement('input');
            typing.type = 'text';
            typing.id = source.id;
            typing.autocomplete = 'off';
            typing.maxLength = 40;
            if (source.getAttribute('list')) {
                typing.setAttribute('list', source.getAttribute('list'));
            }
            if (source.getAttribute('aria-describedby')) {
                typing.setAttribute('aria-describedby', source.getAttribute('aria-describedby'));
            }
            source.id = source.id + '_value';
            source.type = 'hidden';
            source.removeAttribute('list');
            source.setAttribute('data-error-target', field.id);
            source.classList.remove('is-invalid');

            var render = function () {
                field.querySelectorAll('.tags-chip').forEach(function (c) { c.remove(); });
                tags.forEach(function (tag, index) {
                    var chip = el('span', 'tags-chip');
                    chip.appendChild(el('span', null, tag));
                    var remove = el('button', null, '×');
                    remove.type = 'button';
                    remove.setAttribute('aria-label', 'Xóa thẻ ' + tag);
                    remove.addEventListener('click', function () {
                        tags.splice(index, 1);
                        sync();
                        typing.focus();
                    });
                    chip.appendChild(remove);
                    field.insertBefore(chip, typing);
                });
                typing.placeholder = tags.length ? '' : 'Gõ thẻ rồi nhấn Enter';
                typing.disabled = false;
            };
            var sync = function () {
                source.value = tags.join(', ');
                source.dispatchEvent(new Event('input', { bubbles: true }));
                render();
            };
            var add = function (text) {
                var value = text.replace(/\s+/g, ' ').trim();
                if (!value) {
                    return true;
                }
                var exists = tags.some(function (t) { return t.toLowerCase() === value.toLowerCase(); });
                if (exists) {
                    announce('Thẻ “' + value + '” đã có.');
                    return true;
                }
                if (tags.length >= max) {
                    toast('Mỗi bài tối đa ' + max + ' thẻ.', 'error');
                    return false;
                }
                tags.push(value);
                sync();
                announce('Đã thêm thẻ ' + value);
                return true;
            };

            typing.addEventListener('keydown', function (event) {
                var key = event.key;
                if ((key === 'Enter' || key === ',' || key === ';' || (key === 'Tab' && !event.shiftKey)) && typing.value.trim() !== '') {
                    event.preventDefault(); // Tab chỉ bị giữ lại khi đang gõ dở một thẻ
                    if (add(typing.value)) {
                        typing.value = '';
                    }
                } else if (key === 'Enter') {
                    event.preventDefault(); // không gửi form khi nhấn Enter trong ô thẻ
                } else if (key === 'Backspace' && typing.value === '' && tags.length) {
                    tags.pop();
                    sync();
                }
            });
            // Chọn từ gợi ý (datalist) hoặc dán "a, b, c"
            typing.addEventListener('input', function (event) {
                var value = typing.value;
                if (/[,;]/.test(value)) {
                    value.split(/[,;]/).slice(0, -1).forEach(add);
                    typing.value = value.split(/[,;]/).pop();
                } else if (!event.inputType || event.inputType === 'insertReplacementText') {
                    if (add(value)) {
                        typing.value = '';
                    }
                }
            });
            typing.addEventListener('blur', function () {
                if (add(typing.value)) {
                    typing.value = '';
                }
            });
            field.addEventListener('click', function (event) {
                if (event.target === field) {
                    typing.focus();
                }
            });

            field.appendChild(typing);
            source.insertAdjacentElement('beforebegin', field);
            render();
        });
    }

    // --- Form kênh thông báo: chỉ hiện (và chỉ gửi) thông số của loại kênh đang chọn ---
    function syncChannelForm(root) {
        root.querySelectorAll('[data-channel-form]').forEach(function (box) {
            var radio = box.querySelector('[data-channel-driver]:checked');
            var groups = box.querySelectorAll('[data-channel-fields]');
            var current = radio ? radio.value : (groups.length === 1 ? groups[0].getAttribute('data-channel-fields') : null);
            groups.forEach(function (group) {
                var on = group.getAttribute('data-channel-fields') === current;
                group.hidden = !on;
                group.querySelectorAll('input, select, textarea').forEach(function (input) { input.disabled = !on; });
            });
        });
    }
    document.addEventListener('change', function (event) {
        if (event.target.matches && event.target.matches('[data-channel-driver]')) {
            syncChannelForm(event.target.closest('[data-channel-form]').parentNode);
        }
    });

    // --- Chọn ngày/giờ: flatpickr (MIT) thay ô type="date"/"datetime-local" mặc định ---
    // Ô hiển thị dd/mm/yyyy (kèm giờ cho hạn công việc), giá trị gửi lên máy chủ giữ nguyên Y-m-d / Y-m-dTH:i.
    // Dùng cả trên điện thoại (disableMobile) để giao diện thống nhất, ô bấm lớn. Không JS / không tải được
    // thư viện: vẫn là ô ngày của trình duyệt.
    // Ô hiển thị chỉ đọc (allowInput: false): bấm vào chỉ mở lịch, không bật bàn phím/con trỏ gõ chữ.
    // Lịch có nút "Hôm nay" để quay nhanh về ngày hiện tại.
    function addTodayButton(picker) {
        var footer = document.createElement('div');
        footer.className = 'flatpickr-footer';
        var today = document.createElement('button');
        today.type = 'button';
        today.className = 'btn btn-sm btn-outline-primary';
        today.innerHTML = '<i class="bi bi-calendar-check" aria-hidden="true"></i> Hôm nay';
        today.addEventListener('click', function () {
            var now = new Date();
            if (!picker.config.enableTime) {
                now.setHours(0, 0, 0, 0);
            } else {
                now.setMinutes(Math.ceil(now.getMinutes() / 5) * 5, 0, 0);
            }
            picker.setDate(now, true);
            picker.jumpToDate(now);
            picker.close();
        });
        footer.appendChild(today);
        picker.calendarContainer.appendChild(footer);
    }

    function initDatePickers(root) {
        var inputs = root.querySelectorAll('input[type="date"]:not([data-fp]), input[type="datetime-local"]:not([data-fp])');
        if (!inputs.length) {
            return;
        }
        Promise.all([
            loadAsset('vendor/flatpickr/flatpickr.min.css'),
            loadAsset('vendor/flatpickr/flatpickr.min.js')
        ]).then(function () {
            return loadAsset('vendor/flatpickr/vn.js');
        }).then(function () {
            inputs.forEach(function (input) {
                if (input.hasAttribute('data-fp') || !input.isConnected) {
                    return;
                }
                input.setAttribute('data-fp', '1');
                var withTime = input.type === 'datetime-local';
                if (input.required) {
                    input.setAttribute('data-required', '1'); // ô gốc bị ẩn: bộ kiểm tra trước khi gửi vẫn biết là bắt buộc
                }
                var picker = window.flatpickr(input, {
                    locale: Object.assign({}, window.flatpickr.l10ns.vn, { firstDayOfWeek: 1 }),
                    dateFormat: withTime ? 'Y-m-d\\TH:i' : 'Y-m-d',
                    altInput: true,
                    altFormat: withTime ? 'd/m/Y H:i' : 'd/m/Y',
                    allowInput: false,
                    disableMobile: true,
                    enableTime: withTime,
                    time_24hr: true,
                    minuteIncrement: 5,
                    monthSelectorType: 'static',
                    onReady: function (selected, str, instance) {
                        addTodayButton(instance);
                    },
                    onChange: function () {
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
                var display = picker.altInput;
                if (!display) {
                    return;
                }
                // Nhãn, mô tả, lỗi trỏ sang ô hiển thị.
                display.id = (input.id || input.name) + '_display';
                display.setAttribute('inputmode', 'none');
                display.setAttribute('autocomplete', 'off');
                display.placeholder = withTime ? 'dd/mm/yyyy hh:mm' : 'dd/mm/yyyy';
                ['aria-describedby', 'aria-invalid'].forEach(function (attr) {
                    if (input.getAttribute(attr)) {
                        display.setAttribute(attr, input.getAttribute(attr));
                    }
                });
                if (input.classList.contains('is-invalid')) {
                    display.classList.add('is-invalid');
                }
                var label = input.id && document.querySelector('label[for="' + input.id + '"]');
                if (label) {
                    label.htmlFor = display.id;
                }
                input.setAttribute('data-error-target', display.id);
            });
        }).catch(function () {
            // Không tải được thư viện: giữ ô ngày mặc định của trình duyệt.
        });
    }

    // --- Ô xác nhận (data-confirm-gate): nút gửi của form bị khóa tới khi tích ô (hủy / mở lại chương trình) ---
    // Không JS thì nút vẫn bấm được; máy chủ vẫn bắt buộc ô xác nhận.
    function syncConfirmGate(checkbox) {
        var form = checkbox.form;
        if (!form) {
            return;
        }
        form.querySelectorAll('button[type="submit"]').forEach(function (button) {
            button.disabled = !checkbox.checked;
        });
    }
    function initConfirmGates(root) {
        (root || document).querySelectorAll('input[type="checkbox"][data-confirm-gate]').forEach(syncConfirmGate);
    }
    document.addEventListener('change', function (event) {
        if (event.target.matches && event.target.matches('input[type="checkbox"][data-confirm-gate]')) {
            syncConfirmGate(event.target);
        }
    });

    function initPostForms(root) {
        syncChannelForm(root);
        initDatePickers(root);
        initRichEditors(root);
        initTagInputs(root);
        initExistingImageRemovers(root);
        initConfirmGates(root);
    }

    // --- Album công khai: bấm ảnh để xem lớn, vuốt qua lại (PhotoSwipe, MIT) ---
    function initGalleries() {
        var gallery = document.querySelector('[data-gallery]');
        if (!gallery) {
            return;
        }
        Promise.all([
            loadAsset('vendor/photoswipe/photoswipe.css'),
            loadAsset('vendor/photoswipe/photoswipe.umd.min.js'),
            loadAsset('vendor/photoswipe/photoswipe-lightbox.umd.min.js')
        ]).then(function () {
            var lightbox = new window.PhotoSwipeLightbox({
                gallery: '[data-gallery]',
                children: 'a',
                pswpModule: window.PhotoSwipe,
                bgOpacity: 0.92,
                closeTitle: 'Đóng (Esc)',
                zoomTitle: 'Phóng to',
                arrowPrevTitle: 'Ảnh trước',
                arrowNextTitle: 'Ảnh sau',
                errorMsg: 'Không tải được ảnh.',
                indexIndicatorSep: ' / '
            });
            // Chú thích ảnh lấy từ <figcaption> (dựng bằng textContent).
            lightbox.on('uiRegister', function () {
                lightbox.pswp.ui.registerElement({
                    name: 'caption',
                    order: 9,
                    isButton: false,
                    appendTo: 'root',
                    onInit: function (node, pswp) {
                        node.className = 'pswp__custom-caption';
                        pswp.on('change', function () {
                            var link = pswp.currSlide.data.element;
                            var caption = link && link.closest('figure') && link.closest('figure').querySelector('figcaption');
                            node.textContent = caption ? caption.textContent : '';
                            node.hidden = !caption;
                        });
                    }
                });
            });
            lightbox.init();
        }).catch(function () {
            // Không tải được thư viện: liên kết vẫn mở ảnh lớn ở tab mới.
        });
    }

    // --- Ảnh tải lười: chỉ tải khi ảnh đi vào khung nhìn (x-post-img) ---
    // Ảnh ưu tiên (đầu trang) đã có src thật. Ảnh khác giữ URL ở data-src/data-srcset; khi ảnh (gần như) hiện
    // trong khung nhìn — kể cả khi cuộn ngang album — mới gán src để trình duyệt tải. Ảnh nằm ngoài khung
    // nhìn không bao giờ được tải. Vùng đệm 50px để ảnh kịp hiện khi vừa cuộn tới.
    var lazyObserver = 'IntersectionObserver' in window
        ? new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    lazyObserver.unobserve(entry.target);
                    loadLazyImage(entry.target);
                }
            });
        }, { rootMargin: '50px' })
        : null;

    /** Gán src thật cho một ảnh chờ; hiện dần khi tải xong. */
    function loadLazyImage(img) {
        img.addEventListener('load', function () { img.classList.add('is-loaded'); }, { once: true });
        img.addEventListener('error', function () { img.classList.add('is-loaded', 'is-broken'); }, { once: true });
        if (img.dataset.srcset) {
            img.srcset = img.dataset.srcset;
        }
        img.src = img.dataset.src;
        img.removeAttribute('data-lazy');
    }

    // Ảnh hỏng/mất file (kể cả ảnh tải ngay): ẩn đi để thấy nền thay thế thay vì biểu tượng ảnh vỡ.
    document.addEventListener('error', function (event) {
        var img = event.target;
        if (img && img.tagName === 'IMG') {
            img.classList.add('is-broken');
        }
    }, true);
    // Ảnh đã lỗi trước khi file JS này chạy (ảnh đầu trang tải ngay).
    document.querySelectorAll('img[src]').forEach(function (img) {
        if (img.complete && img.naturalWidth === 0) {
            img.classList.add('is-broken');
        }
    });

    function initLazyImages(root) {
        root.querySelectorAll('img[data-lazy]').forEach(function (img) {
            if (lazyObserver) {
                lazyObserver.observe(img);
            } else {
                loadLazyImage(img); // trình duyệt quá cũ: tải luôn
            }
        });
    }

    // --- Slider chương trình đang diễn ra (trang chủ) ---
    // Dải trượt ngang dùng scroll-snap (không JS vẫn vuốt được). JS thêm: nút trước/sau, chấm vị trí,
    // tự chuyển sau 6 giây; dừng khi rê chuột, focus bàn phím, chạm, tab ẩn hoặc người dùng chọn giảm chuyển động.
    // Ảnh của slide sắp hiện được tải trước để không thấy khung trống khi chuyển.
    function initSliders() {
        document.querySelectorAll('[data-slider]').forEach(function (slider) {
            var track = slider.querySelector('[data-slider-track]');
            var slides = slider.querySelectorAll('[data-slide]');
            var dots = slider.querySelectorAll('[data-slider-dot]');
            var prev = slider.querySelector('[data-slider-prev]');
            var next = slider.querySelector('[data-slider-next]');
            if (!track || slides.length < 2) {
                return;
            }
            var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var paused = false;
            var timer = null;
            var scrollTimer = null;

            function current() {
                return Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
            }
            function preload(i) {
                var slide = slides[(i + slides.length) % slides.length];
                slide.querySelectorAll('img[data-lazy]').forEach(function (img) {
                    if (lazyObserver) {
                        lazyObserver.unobserve(img);
                    }
                    loadLazyImage(img);
                });
            }
            function goTo(i) {
                i = (i + slides.length) % slides.length;
                preload(i);
                track.scrollTo({ left: i * track.clientWidth, behavior: reduced ? 'auto' : 'smooth' });
            }
            function update() {
                var i = current();
                dots.forEach(function (dot, k) {
                    if (k === i) {
                        dot.setAttribute('aria-current', 'true');
                    } else {
                        dot.removeAttribute('aria-current');
                    }
                });
                preload(i + 1);
            }
            function schedule() {
                clearInterval(timer);
                if (!reduced) {
                    timer = setInterval(function () {
                        if (!paused && !document.hidden) {
                            goTo(current() + 1);
                        }
                    }, 6000);
                }
            }

            if (prev) {
                prev.addEventListener('click', function () { goTo(current() - 1); schedule(); });
            }
            if (next) {
                next.addEventListener('click', function () { goTo(current() + 1); schedule(); });
            }
            dots.forEach(function (dot) {
                dot.addEventListener('click', function () { goTo(parseInt(dot.dataset.sliderDot, 10)); schedule(); });
            });
            track.addEventListener('scroll', function () {
                clearTimeout(scrollTimer);
                scrollTimer = setTimeout(update, 80);
            }, { passive: true });
            slider.addEventListener('mouseenter', function () { paused = true; });
            slider.addEventListener('mouseleave', function () { paused = false; });
            slider.addEventListener('focusin', function () { paused = true; });
            slider.addEventListener('focusout', function () { paused = false; });
            track.addEventListener('touchstart', function () { paused = true; }, { passive: true });
            slider.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowRight') {
                    goTo(current() + 1);
                } else if (event.key === 'ArrowLeft') {
                    goTo(current() - 1);
                }
            });
            update();
            schedule();
        });
    }

    // --- Ô tìm kiếm có data-submit-on-clear: bấm × (xóa) thì gửi form luôn để về danh sách đầy đủ ---
    // Bấm × phát sự kiện "search" (Chrome/Edge/Safari) và "input" không có inputType (không phải gõ phím).
    // Chỉ gửi khi trang đang có từ khóa (giá trị ban đầu khác rỗng); cờ riêng chống gửi hai lần.
    function submitOnClear(event) {
        var input = event.target;
        if (!input.matches || !input.matches('input[data-submit-on-clear]') || input.value !== ''
            || input.defaultValue === '' || input.clearSubmitted || !input.form) {
            return;
        }
        if (event.type === 'input' && event.inputType) {
            return; // người dùng tự xóa bằng phím: chờ họ gõ tiếp / bấm Enter
        }
        input.clearSubmitted = true;
        if (input.form.requestSubmit) {
            input.form.requestSubmit();
        } else {
            input.form.submit();
        }
    }
    document.addEventListener('search', submitOnClear, true);
    document.addEventListener('input', submitOnClear, true);

    // --- Form liên hệ: đổi gợi ý nội dung theo chủ đề đang chọn ---
    document.addEventListener('change', function (event) {
        var radio = event.target.closest && event.target.closest('[data-topic-hint]');
        var help = document.getElementById('f_message_help');
        if (radio && help) {
            help.textContent = radio.dataset.topicHint;
        }
    });

    // Liên hệ: danh sách chỉ có email/số điện thoại đã che; "Hiện" gọi máy chủ (có ghi nhật ký) lấy bản đầy đủ
    // rồi thay bằng link gửi mail/gọi điện. Không có JS thì nút không hiện.
    document.querySelectorAll('[data-reveal-url]').forEach(function (button) {
        button.hidden = false;
    });
    document.addEventListener('click', function (event) {
        var button = event.target.closest && event.target.closest('[data-reveal-url]');
        if (!button) {
            return;
        }
        button.disabled = true;
        fetch(button.getAttribute('data-reveal-url'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : ''
            }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error(String(response.status));
            }
            return response.json();
        }).then(function (data) {
            var target = button.closest('[data-reveal-target]');
            var link = document.createElement('a');
            link.href = data.href;
            link.className = 'tabular';
            link.textContent = data.contact; // textContent: không chèn HTML từ dữ liệu khách nhập
            target.querySelector('[data-reveal-value]').replaceWith(link);
            button.remove();
            link.focus();
        }).catch(function () {
            button.disabled = false;
            toast('Không lấy được thông tin liên lạc. Vui lòng thử lại.', 'error');
        });
    });

    // Băng trượt ngang (data-carousel, ví dụ "Đơn vị tham gia"): nút trước/sau cuộn gần một khung; chỉ hiện nút khi
    // nội dung tràn; nút bị mờ ở đầu/cuối. Không tự chạy. Không JS thì vẫn vuốt/cuộn ngang được.
    document.querySelectorAll('[data-carousel]').forEach(function (carousel) {
        var track = carousel.querySelector('[data-carousel-track]');
        var nav = carousel.querySelector('[data-carousel-nav]');
        var prev = carousel.querySelector('[data-carousel-prev]');
        var next = carousel.querySelector('[data-carousel-next]');
        if (!track || !nav) {
            return;
        }
        function sync() {
            var max = track.scrollWidth - track.clientWidth;
            nav.hidden = max <= 2;
            prev.disabled = track.scrollLeft <= 2;
            next.disabled = track.scrollLeft >= max - 2;
        }
        function move(direction) {
            track.scrollBy({ left: direction * track.clientWidth * 0.85 });
        }
        prev.addEventListener('click', function () { move(-1); });
        next.addEventListener('click', function () { move(1); });
        track.addEventListener('scroll', sync, { passive: true });
        window.addEventListener('resize', sync);
        sync();
    });

    // Nút "Lên đầu trang" (trang công khai): hiện khi đã cuộn quá ~1,5 màn hình; bấm thì cuộn lên và đưa focus về nội dung.
    var backToTop = document.querySelector('[data-back-to-top]');
    if (backToTop) {
        backToTop.hidden = false;
        var syncBackToTop = function () {
            backToTop.classList.toggle('is-visible', window.scrollY > window.innerHeight * 1.5);
        };
        window.addEventListener('scroll', syncBackToTop, { passive: true });
        syncBackToTop();
        backToTop.addEventListener('click', function () {
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
            var main = document.getElementById('main');
            if (main) {
                main.focus({ preventScroll: true });
            }
        });
    }

    // Bộ lọc thu gọn trên điện thoại (trang báo cáo, liên hệ, tài khoản, bài viết): chỉ thu gọn khi có JS để mở lại.
    document.querySelectorAll('[data-filter-toggle]').forEach(function (toggle) {
        var filterBox = document.getElementById(toggle.getAttribute('data-filter-toggle'));
        if (filterBox) {
            filterBox.classList.add('is-collapsible');
            toggle.hidden = false;
        }
    });

    initMoney(document);
    initQuantities(document);
    initUnitSelects(document);
    initPostForms(document);
    initLazyImages(document);
    initSliders();
    initGalleries();
})();
