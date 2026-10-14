// BếpMinhBạch — JavaScript tăng cường (progressive enhancement).
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
    var overlay = ensure('.page-overlay', '<div class="page-overlay" aria-hidden="true"><div class="page-overlay-box">'
        + '<span class="spinner-border" aria-hidden="true"></span><span data-overlay-text>Đang tải…</span></div></div>');

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

    function hasDirtyForm() {
        return document.querySelector('form[data-warn-unsaved][data-dirty="1"]:not([data-submitting="1"])') !== null;
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
        var field = root.querySelector('.is-invalid, input:not([type="hidden"]):not([disabled]):not([type="checkbox"]), select, textarea');
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
            initUnitSelects(modalDialog);
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
                toast('Bạn không có quyền thực hiện thao tác này, hoặc đợt nấu đã bị khóa.', 'error');
                return;
            }
            // Phiên hết hạn hoặc lỗi lạ: mở trang đầy đủ như khi không có JS.
            window.location.href = href;
        });
    }

    if (modalEl) {
        // Hỏi lại trước khi đóng modal có thay đổi chưa lưu.
        modalEl.addEventListener('hide.bs.modal', function (event) {
            var dirty = modalDialog.querySelector('form[data-warn-unsaved][data-dirty="1"]:not([data-submitting="1"])');
            if (dirty && !window.confirm('Bỏ các thay đổi chưa lưu?')) {
                event.preventDefault();
            }
        });
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
            initUnitSelects(document);
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

    function showErrors(form, errors) {
        var unplaced = [];
        Object.keys(errors).forEach(function (name) {
            var message = errors[name][0];
            var fields = form.querySelectorAll('[name="' + name + '"]');
            if (!fields.length) {
                unplaced.push(message);
                return;
            }
            fields.forEach(function (field) {
                field.classList.add('is-invalid');
                field.setAttribute('aria-invalid', 'true');
            });
            var last = fields[fields.length - 1];
            var anchor = last.closest('.input-group') || (last.type === 'checkbox' || last.type === 'radio'
                ? last.closest('.form-check, .choice-card, fieldset') : null) || last;
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
            showFormAlert(form, 'Chưa lưu được. Vui lòng sửa các mục được đánh dấu.');
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
        var body = buildFormData(form, submitter);

        form.setAttribute('data-submitting', '1');
        form.setAttribute('aria-busy', 'true');
        setButtonsLoading(form, submitter);
        clearErrors(form);
        startPageLoading('Đang lưu…', false);

        fetch(form.action, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : ''
            }
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
                    form.removeAttribute('data-dirty');
                    initMoney(form);
                    initUnitSelects(form);
                    focusFirstField(form);
                } else {
                    form.removeAttribute('data-dirty');
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
            } else if (result.status === 409 && data.reload && inModal) {
                // Dữ liệu vừa bị người khác sửa: tải lại form để thấy giá trị mới nhất.
                loadModal(modalDialog.getAttribute('data-url')).then(function () {
                    showFormAlert(modalDialog.querySelector('form'), data.message);
                });
                refreshRegions();
            } else if (result.status === 401 || result.status === 419) {
                toast('Phiên đăng nhập đã hết hạn. Trang sẽ tải lại để bạn đăng nhập.', 'error');
                setTimeout(function () { window.location.reload(); }, 1800);
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
        if (hasDirtyForm()) {
            return; // trình duyệt sẽ hỏi lại
        }
        var reloadsList = link.closest('.pagination, .chip-row') !== null && showSkeletons();
        startPageLoading(reloadsList ? 'Đang tải dữ liệu…' : null, !reloadsList);
    });

    // =====================================================================
    // Form chưa lưu, ô đơn vị "Khác"
    // =====================================================================
    function markDirty(event) {
        var form = event.target.form;
        if (form && form.hasAttribute('data-warn-unsaved')) {
            form.setAttribute('data-dirty', '1');
        }
    }
    document.addEventListener('input', markDirty);
    document.addEventListener('change', markDirty);

    window.addEventListener('beforeunload', function (event) {
        if (hasDirtyForm()) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    function syncUnitSelect(select) {
        var other = document.getElementById(select.getAttribute('data-unit-select'));
        if (!other) {
            return;
        }
        var show = select.value === 'other';
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

    initMoney(document);
    initUnitSelects(document);
})();
