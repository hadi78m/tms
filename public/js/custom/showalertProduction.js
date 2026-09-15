/*!
 * showalert.production.js
 * Enterprise SweetAlert + Ajax Helper
 * Namespace: AppAlert (Backward Compatible)
 * Requires: jQuery, SweetAlert2
 */

window.AppAlert = (function ($, Swal) {
    'use strict';

    if (!$) throw new Error('AppAlert requires jQuery');
    if (!Swal) throw new Error('AppAlert requires SweetAlert2');

    let loadingCount = 0;

    /* =========================
     * Utils
     * ========================= */

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function isFormData(data) {
        return typeof FormData !== 'undefined' && data instanceof FormData;
    }

    /* =========================
     * Loading
     * ========================= */

    function showLoading() {
        if (loadingCount === 0) {
            Swal.fire({
                title: 'در حال پردازش...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });
        }
        loadingCount++;
    }

    function hideLoading() {
        loadingCount--;
        if (loadingCount <= 0) {
            loadingCount = 0;
            Swal.close();
        }
    }

    /* =========================
     * Toast / Alerts
     * ========================= */

    function showToast(icon, title, text) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: icon,
            title: title,
            text: text || '',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    }

    function showSuccess(msg) {
        showToast('success', msg || 'عملیات با موفقیت انجام شد');
    }

    function showError(msg) {
        showToast('error', msg || 'خطا در انجام عملیات');
    }

    function showWarning(msg) {
        showToast('warning', msg || 'هشدار');
    }

    /* =========================
     * Validation Errors
     * ========================= */

    function clearValidation(form) {
        if (!form) return;
        $(form).find('.is-invalid').removeClass('is-invalid');
        $(form).find('.invalid-feedback').remove();
    }

    function showValidationErrors(form, errors) {
        clearValidation(form);
        if (!errors || typeof errors !== 'object') return;

        Object.keys(errors).forEach(field => {
            const messages = Array.isArray(errors[field]) ? errors[field] : [errors[field]];
            const $input = $(form).find(`[name="${field}"]`);
            if ($input.length) {
                $input.addClass('is-invalid');
                $input.after(`<div class="invalid-feedback text-red-500 text-sm mt-1">${messages[0]}</div>`);
            }
        });
    }

    function parseValidationErrors(xhr) {
        if (xhr?.responseJSON?.errors) {
            return xhr.responseJSON.errors;
        }
        if (xhr?.responseJSON?.message) {
            return { message: xhr.responseJSON.message };
        }
        return null;
    }

    /* =========================
     * Error Handler
     * ========================= */

    function handleAjaxError(xhr, reject) {
        hideLoading();

        if (xhr?.status === 422) {
            const errors = parseValidationErrors(xhr);
            if (errors) {
                showError('لطفاً خطاهای فرم را بررسی کنید');
                return reject ? reject(xhr) : null;
            }
        }

        if (xhr?.status === 404) {
            showError('آدرس مورد نظر یافت نشد');
        } else if (xhr?.status === 403) {
            showError('شما دسترسی لازم را ندارید');
        } else if (xhr?.status === 500) {
            showError('خطای سرور. لطفاً دوباره تلاش کنید');
        } else {
            const msg = xhr?.responseJSON?.message || xhr?.statusText || 'خطا در ارتباط با سرور';
            showError(msg);
        }

        if (reject) reject(xhr);
    }

    /* =========================
     * Route Helper
     * ========================= */

    function route(name, params) {
        // Try window.LaravelRoutes first
        if (window.LaravelRoutes && window.LaravelRoutes[name]) {
            let url = window.LaravelRoutes[name];
            if (params) {
                Object.keys(params).forEach(key => {
                    url = url.replace(`{${key}}`, params[key]);
                });
            }
            return url;
        }

        // Fallback: convert dot notation to URL
        let url = '/' + name.replace(/\./g, '/');
        if (params) {
            Object.keys(params).forEach(key => {
                url = url.replace(`{${key}}`, params[key]);
            });
        }
        return url;
    }

    /* =========================
     * Ajax Core
     * ========================= */

    function ajax(url, options) {
        const defaults = {
            url: url,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        const settings = $.extend(true, defaults, options);

        // ✅ For FormData: disable jQuery processing so browser handles it
        if (isFormData(settings.data)) {
            settings.processData = false;
            settings.contentType = false;
        }

        return $.ajax(settings);
    }

    /* =========================
     * POST Helper (returns jQuery Deferred for .done() compat)
     * ========================= */

    function _post(url, data, options) {
        options = options || {};

        const showLoadingFlag = options.loading !== false;
        const showSuccessFlag = options.successAlert !== false;
        const showErrorFlag = options.errorAlert !== false;

        if (showLoadingFlag) showLoading();

        const deferred = $.Deferred();

        ajax(url, {
            type: 'POST',
            data: data,
            success: function (res) {
                if (showLoadingFlag) hideLoading();
                if (showSuccessFlag && res?.success !== false) {
                    showSuccess(options.successMessage || res?.message || 'عملیات با موفقیت انجام شد');
                }
                if (options.success) options.success(res);
                deferred.resolve(res);
            },
            error: function (xhr) {
                if (showErrorFlag) {
                    handleAjaxError(xhr);
                } else {
                    hideLoading();
                }
                if (options.error) options.error(xhr);
                deferred.reject(xhr);
            }
        });

        return deferred.promise();
    }

    function post(url, data, options) {
        return _post(url, data, options);
    }

    /* =========================
     * DELETE Helper
     * ========================= */

    function del(url, options) {
        return _post(url, {}, options);
    }

    /* =========================
     * Confirm Delete
     * ========================= */

    async function confirmDelete(url, options) {
        options = options || {};

        const result = await Swal.fire({
            title: options.title || 'آیا مطمئن هستید؟',
            text: options.text || 'این عمل قابل بازگشت نیست',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: options.confirmText || 'بله، حذف شود',
            cancelButtonText: options.cancelText || 'انصراف',
            reverseButtons: true
        });

        if (!result.isConfirmed) return false;

        showLoading();

        try {
            const res = await $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            hideLoading();
            showSuccess(options.successMessage || 'حذف با موفقیت انجام شد');
            return res;
        } catch (xhr) {
            hideLoading();
            handleAjaxError(xhr);
            return false;
        }
    }

    /* =========================
     * Confirm Action
     * ========================= */

    async function confirmAction(url, options) {
        options = options || {};

        const result = await Swal.fire({
            title: options.title || 'آیا مطمئن هستید؟',
            text: options.text || '',
            icon: options.icon || 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: options.confirmText || 'تایید',
            cancelButtonText: options.cancelText || 'انصراف',
        });

        if (!result.isConfirmed) return false;
        return await _post(url, options.data || {}, options);
    }

    /* =========================
     * Ajax Form
     * ========================= */

    function ajaxForm(form, options) {
        options = options || {};
        const $form = $(form);
        const url = options.url || $form.attr('action');
        const formData = new FormData(form[0] || form);

        // Append additional data
        if (options.data) {
            Object.keys(options.data).forEach(key => {
                formData.append(key, options.data[key]);
            });
        }

        return _post(url, formData, options);
    }

    /* =========================
     * Reload DataTable
     * ========================= */

    function reloadDataTable(tableId) {
        document.dispatchEvent(new CustomEvent('admin:table:reload', {
            detail: { tableId: tableId }
        }));
    }

    /* =========================
     * Public API
     * ========================= */

    return {
        route,
        post,
        del,
        ajax,
        confirmDelete,
        confirmAction,
        ajaxForm,
        reloadDataTable,
        showLoading,
        hideLoading,
        showSuccess,
        showError,
        showWarning,
        showToast,
        clearValidation,
        showValidationErrors,
        parseValidationErrors,
        handleAjaxError
    };

})(window.jQuery || window.$, window.Swal);
