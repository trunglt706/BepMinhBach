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
                toast('Bạn không có quyền thực hiện thao tác này, hoặc đợt nấu đã bị khóa.', 'error');
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
        var form = event.target.form || (event.target.closest && event.target.closest('form'));
        if (form && form.hasAttribute('data-warn-unsaved')) {
            form.setAttribute('data-dirty', '1');
        }
    }
    document.addEventListener('input', markDirty);
    document.addEventListener('change', markDirty);
    document.addEventListener('trix-change', markDirty);

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
                markDirty({ target: input });
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
            markDirty({ target: input });
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

    function initPostForms(root) {
        initRichEditors(root);
        initTagInputs(root);
        initExistingImageRemovers(root);
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

    initMoney(document);
    initUnitSelects(document);
    initPostForms(document);
    initGalleries();
})();
