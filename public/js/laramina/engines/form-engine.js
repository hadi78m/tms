import Ajax from '../adapters/ajax-adapter.js'

const FormEngine = {

    userRoles() {
        if (!window.AdminUser) return []
        return window.AdminUser.roles || []
    },
    hasRole(role) {
        return this.userRoles().includes(role)
    },

    checkRoles(roles) {
        if (!roles) return true
        // اگر رشته بود، به آرایه تبدیل کن
        const rolesArray = Array.isArray(roles) ? roles : [roles]
        return rolesArray.some(role => this.hasRole(role))
    },
    userPermissions() {
        return (window.AdminUser && window.AdminUser.permissions) || []
    },

    hasPermission(permission) {
        return this.userPermissions().includes(permission)
    },
    checkPermission(permission) {
        if (!permission) return true

        // اگر یک رشته تکی بود
        if (typeof permission === 'string') {
            return this.hasPermission(permission)
        }

        // اگر آرایه بود (حداقل یکی از موارد کافی است)
        if (Array.isArray(permission)) {
            return permission.some(p => this.hasPermission(p))
        }

        return false
    },
    submit(form, endpoint) {
        const data = new FormData(form);
        let url = endpoint;
        const isAbsolute = endpoint.startsWith('http') || endpoint.startsWith('/');

        if (!isAbsolute) {
            url = AppAlert.route(endpoint);
        }

        // ارسال همیشه با POST - نیازی به _method نیست
        // روت‌ها قبلاً به Route::post تغییر کرده‌اند

        // ✅ برگرداندن Promise با استفاده از AppAlert.post موجود
        return new Promise((resolve, reject) => {
            AppAlert.post(url, data, {
                loading: true,
                successAlert: true,
                errorAlert: false,  // جلوگیری از دوبار نمایش (خطاها توسط handleAjaxError نمایش داده می‌شوند)
                success: (res) => {
                    resolve(res);
                },
                error: (err) => {
                    // اگر خطا پیغامی دارد، نمایش بده
                    if (err?.responseJSON?.message) {
                        AppAlert.showError(err.responseJSON.message);
                    }
                    reject(err);
                }
            });
        });
    },
    canAccess(field) {
        // اگر نقش/دسترسی تعریف نشده → اجازه بده
        if (!field.role && !field.roles && !field.permission && !field.permissions) {
            return true
        }

        // 1. بررسی role (پشتیبانی از هر دو فرمت)
        const roleKeys = field.role || field.roles
        if (roleKeys) {
            const roles = Array.isArray(roleKeys) ? roleKeys : [roleKeys]
            if (!this.checkRoles(roles)) {
                return false
            }
        }

        // 2. بررسی permission (پشتیبانی از هر دو فرمت)
        const permKeys = field.permission || field.permissions
        if (permKeys) {
            const perms = Array.isArray(permKeys) ? permKeys : [permKeys]
            if (!this.checkPermission(perms)) {
                return false
            }
        }

        return true
    },


    filterFields(fields) {
        if (!fields) return []
        return fields.filter(field => this.canAccess(field))
    },


    async render(config, container, data = {}) {

        if (!config) return console.error('FormEngine: config not provided')

        let processedData = { ...data };
        
        const fields = this.filterFields(config.fields)
        
        // ✅ تابع بازگشتی برای بارگذاری options در فیلدهای عادی و گروهی
        const loadOptionsRecursive = async (fieldsArray) => {
            for (const field of fieldsArray) {
                if (field.type === 'group' && field.group && Array.isArray(field.group)) {
                    await loadOptionsRecursive(field.group);
                } else if (field.type === 'select' && field.optionEndpoint) {
                    field.options = await this.loadOptions(field);
                }
            }
        };

        await loadOptionsRecursive(fields);
        
        const form = document.createElement('form')
        form.className = 'p-6 space-y-1'
        form.dataset.endpoint = config.endpoint
        form.dataset.method = config.method || 'POST'


        // ---------------
        // helper برای ساخت input ساده
        const buildInput = (field, value = '') => {
            const required = field.required ? 'required' : ''
            const label = field.label
                ? `<label class="block text-sm font-semibold  mb-2">${field.label}${field.required ? ' <span class="text-red-500">*</span>' : ''}</label>`
                : ''

            if (field.type === 'textarea') {
                return `
                ${label}
                <textarea name="${field.name}" ${required}
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">${value}</textarea>
            `
            }

            if (field.type === 'select') {

                const multiple = field.multiple ? 'multiple' : ''
                let values = Array.isArray(value) ? value : [value]
                // حذف مقادیر خالی و تبدیل به string
                const valuesStr = values.filter(v => v !== '' && v !== null).map(v => String(v))

                const optionsArray = field.options || []

                let optionsHtml = ''

                // اضافه کردن گزینه خالی با selected اگر مقدار فعلی وجود نداشته باشد
                const hasValue = valuesStr.length > 0
                const hasMatch = hasValue && optionsArray.some(opt => String(opt.value) === valuesStr[0])

                if (!hasValue || !hasMatch) {
                    // مقدار فعلی وجود ندارد یا در گزینه‌ها نیست → گزینه خالی با selected
                    optionsHtml += `<option value="" disabled selected>${field.emptyOptionLabel || 'یک گزینه انتخاب کنید'}</option>`
                } else {
                    // گزینه خالی معمولی (غیرقابل انتخاب)
                    optionsHtml += `<option value="" disabled>${field.emptyOptionLabel || 'یک گزینه انتخاب کنید'}</option>`
                }

                // اضافه کردن گزینه‌های اصلی
                optionsHtml += optionsArray.map(opt => {
                    const optValueStr = String(opt.value)
                    const selected = valuesStr.includes(optValueStr) ? 'selected' : ''
                    return `<option value="${opt.value}" ${selected}>${opt.label}</option>`
                }).join('')

                // اگر هیچ گزینه‌ای وجود نداشت
                if (optionsArray.length === 0) {
                    optionsHtml = `<option value="" disabled>گزینه‌ای موجود نیست</option>`
                }

                return `
        ${label}
        <select name="${field.name}${field.multiple ? '[]' : ''}" ${required} ${multiple}
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            ${optionsHtml}
        </select>
                <p class="mt-1 pr-2 text-xs text-gray-500">
                ${field.helper || ''}
                </p>
    `
            }


            if (field.type === 'checkbox') {
                const checked = value ? 'checked' : ''
                return `
                <div class="flex items-center mt-2">
                    <input type="hidden" name="${field.name}" value="0">
                    <input type="checkbox" name="${field.name}" id="${field.name}" value="1" ${checked}
                        class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500">
                        <label for="${field.name}" class="mr-3 text-sm font-semibold ">
                        ${field.label || ''}
                        </label>
                <p class="mt-1 pr-2 text-xs text-gray-500">
                ${field.helper || ''}
                </p>
                </div>
                        `
            }
            if (field.type === 'password') {
                const uniqueId = 'password-' + Math.random().toString(36).substr(2, 9);

                // ✅ اگر hideValue=true باشد یا value خالی باشد، مقدار را نمایش نده
                const shouldHide = field.hideValue === true;
                const displayValue = shouldHide ? '' : (value || '');

                return `
    <div class="mb-4">
        <label class="block  mb-2 text-sm font-medium">
        ${label}
        </label>
<div class="relative">
    <input type="password"
           id="${uniqueId}"
           name="${field.name}"
           value="${displayValue}" 
           autocomplete="new-password"
           ${required}
           placeholder="${field.placeholder ?? ''}"
           minlength="${field.min || ''}"
           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 pl-12">
           <button type="button"
                   class="toggle-password absolute left-3 top-1/2 transform -translate-y-1/2 p-1 hover:bg-gray-100 rounded z-10"
                   data-target="${uniqueId}">
               <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                         d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                         d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
               </svg>
           </button>
</div>     
        <p class="mt-1 pr-2 text-xs text-gray-500">
            ${field.helper || 'گذرواژه مناسب باید حداقل 8 کاراکتر باشد'}
        </p>
    </div>
    `;
            }

            // default input
            return `
                    ${label}
            <input type="${field.type || 'text'}"
                name="${field.name}"
                minlength="${field.min || ''}"
                maxlength="${field.max || ''}"
                value="${value}"
                ${required} 
                placeholder="${field.placeholder ?? ''}"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                <p class="mt-1 pr-2 text-xs text-gray-500">
                ${field.helper || ''}
                </p>
        `
        }
        // ----------------

        let html = ''

        fields.forEach((field, i) => {
            // ✅ استفاده از processedData به جای data
            const value = processedData[field.name] ?? field.value ?? ''

            // ✅ اگر فیلد نوع group دارد (با type مشخص شده)
            if (field.type === 'group' && field.group && Array.isArray(field.group)) {
                // ساخت grid داخلی ۲ ستونی
                html += `
                <div class="grid grid-cols-2 gap-4">
                    ${field.group.map(sub => {
                        const subValue = processedData[sub.name] ?? sub.value ?? '';
                        return `<div>${buildInput(sub, subValue)}</div>`;
                    }).join('')}
                </div>
            `
            } else {
                // فیلد تکی
                html += `<div>${buildInput(field, value)}</div>`
            }
        })

        form.innerHTML = html

        // handle submit
        form.addEventListener('submit', async e => {
            e.preventDefault()

            try {

                const res = await this.submit(form, config.endpoint)

                // اگر ajax خطا داده باشد
                if (!res) {

                    document.dispatchEvent(new CustomEvent('admin:form:error', {
                        detail: {
                            form,
                            error: 'request_failed'
                        }
                    }))

                    return
                }
                document.dispatchEvent(new CustomEvent('admin:form:success', {
                    detail: {
                        form,
                        response: res,
                        row: res?.data ?? null
                    }
                }))



            } catch (err) {

                document.dispatchEvent(new CustomEvent('admin:form:error', {
                    detail: {
                        form,
                        error: err
                    }
                }))
            }
        })



        container.innerHTML = ''
        container.appendChild(form)
    },

    getGridClass(grid) {
        if (!grid) return 'col-span-12';
        if (typeof grid === 'number') return `col-span-${grid}`;
        const validClasses = [
            'col-span-1', 'col-span-2', 'col-span-3', 'col-span-4', 'col-span-5', 'col-span-6',
            'col-span-7', 'col-span-8', 'col-span-9', 'col-span-10', 'col-span-11', 'col-span-12'
        ];
        if (!validClasses.includes(grid)) {
            console.warn(`FormEngine: invalid grid '${grid}', using col-span-12`);
            return 'col-span-12';
        }
        return grid;
    },
    async loadOptions(field) {
        AppAlert.showLoading();

        try {
            if (!field.optionEndpoint) return field.options || []

            let optionUrl = field.optionEndpoint
            const isAbsolute = optionUrl.startsWith('http') || optionUrl.startsWith('/')

            if (!isAbsolute) {
                optionUrl = AppAlert.route(optionUrl)
            }

            const res = await Ajax.get(optionUrl)

            if (!Array.isArray(res)) {
                console.error('Option endpoint did not return array:', res)
                return []
            }

            const labelKey = field.optionLabel || 'name'
            const valueKey = field.optionValue || 'id'

            return res.map(item => ({
                label: item[labelKey],
                value: item[valueKey],
            }))

        } catch (err) {
            console.error('Error loading select options:', err)
            return [] // ← مهم
        } finally {
            AppAlert.hideLoading();
        }
    }
}

export default FormEngine

// اضافه کردن event listener به صورت کلی
document.addEventListener('click', function (e) {
    if (e.target.closest('.toggle-password')) {
        const button = e.target.closest('.toggle-password');
        const targetId = button.getAttribute('data-target');
        const passwordField = document.getElementById(targetId);
        passwordField.type === 'password' ? passwordField.type = 'text' : passwordField.type = 'password';
    }
});