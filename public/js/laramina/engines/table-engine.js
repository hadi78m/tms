// Adapter برای انتگره شدن با دیتاتیبل (اگر در بعضی پروژه‌ها استفاده شود)
import DT from '../adapters/datatable-adapter.js'

// سیستم پلاگین برای رندر ستون‌ها، مودال‌ها و ...
import PluginManager from '../core/plugin-manager.js'

// انجین فرم برای رندر کردن فرم‌های داخل مودال‌ها و چک کردن role/permission
import FormEngine from '../engines/form-engine.js'

// رندرر مستقل برای بخش‌های UI جدول (مثل pagination)
import { TableRenderer } from '../ui/table-renderer.js'

// اگر از ActionRenderer نیاز داری، این را هم نگه دار؛ در غیر این صورت می‌توانی حذفش کنی
// import ActionRenderer from '../ui/action-renderer.js'

/**
 * TableEngine
 * -----------
 * یک singleton (شیء) برای مدیریت:
 * - رندر HTML جدول
 * - بارگذاری داده‌ها از API
 * - مدیریت فیلتر، جستجو، pagination، per-page
 * - اکشن‌های سطری (edit/delete/...)
 * - bulk actions
 * - باز و بسته کردن مودال‌های مرتبط با جدول
 */
export const TableEngine = {

    /**
     * متد اصلی برای رندر جدول
     * @param {Object} config - تنظیمات جدول (ستون‌ها، endpoint، actions، filters، modals ...)
     * @param {HTMLElement} el - ریشه‌ی DOM که جدول داخل آن رندر می‌شود
     */
    async render(config, el) {

        // --- 1. تنظیم اولیه کانفیگ و شناسه جدول (tableId) ---

        // کپی سطحی از config تا تغییرات داخلی، سورس اصلی را بهم نزند
        this.config = { ...config }
        // اگر tableId از بیرون داده نشده، یک id تصادفی تولید می‌کنیم
        this.config.tableId = this.config.tableId || `table-${Math.random().toString(36).slice(2)}`

        // ذخیره‌ی ریشه‌ی DOM برای استفاده در متدهای دیگر
        this.container = el
        // نگه‌داشتن رفرنس به این اینستنس روی خود DOM (برای دسترسی بیرونی در صورت نیاز)
        this.container.__tableInstance = this

        // --- 2. ثبت لیسنرهای سراسری (Global Event Listeners) برای مدیریت از بیرون ---

        // این لیسنرها فقط یک بار برای هر TableEngine ثبت می‌شود
        if (!this.__listenersBound) {

            this.__listenersBound = true

            // admin:table:reload => رفرش کامل جدول
            document.addEventListener('admin:table:reload', (e) => {
                // اگر tableId در event مشخص شده و با این جدول متفاوت است، نادیده بگیر
                if (e.detail?.tableId && e.detail.tableId !== this.config.tableId) return
                this.loadData()
            })

            // admin:table:update-row => به‌روزرسانی یک ردیف خاص
            document.addEventListener('admin:table:update-row', (e) => {
                const row = e.detail?.row
                if (!row) return
                this.updateRow(row)
            })

            // admin:table:remove-row => حذف یک ردیف خاص
            document.addEventListener('admin:table:remove-row', (e) => {
                const id = e.detail?.id
                if (!id) return
                this.removeRow(id)
            })

            // admin:table:add-row => اضافه کردن یک ردیف جدید
            document.addEventListener('admin:table:add-row', (e) => {
                const row = e.detail?.row
                if (!row) return
                this.addRow(row)
            })
        }

        // --- 3. وضعیت داخلی جدول (State) ---

        // فیلترهای فعال، مثل is_active, type, ...
        this.filters = {}
        // عبارت جستجو
        this.search = ''
        // شناسه ردیف‌های انتخاب‌شده برای bulk actions
        this.selected = []
        // صفحه فعلی
        this.page = 1
        // تعداد آیتم در هر صفحه (per_page) – اگر در config نیامده باشد، null می‌ماند
        this.perPage = config.perPage ?? null
        // اطلاعات متای pagination (total, current_page, per_page, ...)
        this.meta = null
        // داده‌های فعلی جدول (لیست ردیف‌ها)
        this.currentRows = []

        // --- 4. فیلتر کردن bulkActions بر اساس نقش کاربر (RBAC) ---

        if (config.bulkActions) {
            config.bulkActions = config.bulkActions.filter(a =>
                // اگر برای اکشن roles تعریف شده باشد، باید کاربر آن را داشته باشد
                !a.roles || FormEngine.checkRoles(a.roles)
            )
        }

        // --- 5. مقادیر UI ابتدایی (عنوان، لیبل دکمه افزودن، نمایش/عدم نمایش دکمه) ---

        const headerTitle = config.headerTitle || 'مدیریت'
        const addButtonLabel = config.addButtonLabel || 'افزودن'
        let displayButton = config.displayButton ?? false

        // --- 5b. تنظیمات ظاهری (رنگ هدر، استایل thead و tbody) ---
        const headerGradient = config.headerGradient || 'bg-gradient-to-r from-blue-600 to-blue-800 text-white'
        const theadClasses = config.theadClass || 'bg-blue-700 text-center text-white py-2'
        const tbodyClasses = config.tbodyClass || 'divide-y text-center text-gray-800'


        // ✅ بررسی دسترسی برای دکمه افزودن (بر اساس مودال create)
        if (displayButton && config.modals?.create) {
            const createModal = config.modals.create;
            let requiredRoles = createModal.role || createModal.roles;
            if (requiredRoles) {
                // اگر نقش‌ها به صورت رشته هستند، به آرایه تبدیل کن
                if (!Array.isArray(requiredRoles)) {
                    requiredRoles = [requiredRoles];
                }
                const hasAccess = FormEngine.checkRoles(requiredRoles);
                if (!hasAccess) {
                    displayButton = false;   // مخفی کردن دکمه
                }
            }
        }

        // --- 6. رندر اسکلت ثابت جدول (Header, Filters, Table, Pagination Container) ---

        el.innerHTML = `
        <div class="admin-table">
            <!-- هدر کارت و دکمه افزودن -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="my-4 mb-4 ${headerGradient} px-6 py-4 flex justify-between items-center">
                    <h3 class="text-xl font-bold">${headerTitle}</h3>
                    ${displayButton ? `
                        <button data-add
                            class="bg-white text-indigo-600 px-4 py-2 rounded-lg hover:bg-indigo-50 transition-all duration-200 flex items-center gap-2 font-semibold">
                            <i class="fas fa-plus"></i>
                            ${addButtonLabel}
                        </button>
                    ` : ''}
                </div>
            </div>

            <!-- نوار ابزار بالا: bulk actions + search + filters -->
            <div class="flex flex-wrap items-center justify-between mb-4 gap-3">
                <div class="flex items-center gap-3">

                    <!-- انتخاب و اجرای عملیات گروهی -->
                    ${config.bulkActions?.length ? `
                    <select data-bulk class="border rounded px-2 py-1 text-sm">
                        <option value="">عملیات گروهی</option>
                        ${config.bulkActions.map(a =>
            `<option value="${a.name}">${a.label}</option>`).join('')}
                    </select>` : ''}

                    <!-- جستجو -->
                    ${config.search !== false ? `
                        <input data-search placeholder="جستجو..." class="border rounded px-3 py-1 text-sm w-48">
                    ` : ''}

                </div>

                <!-- فیلترها -->
                ${this.renderFilters(config) ? `
                <div>
                    <span class="text-lg mr-3">فیلتر:</span>
                    ${this.renderFilters(config)}
                </div>
                ` : ''}
            </div>

            <!-- بدنه جدول -->
            <div class="overflow-x-auto">
                <table id="${this.config.tableId}" class="w-full border text-sm">
                    <thead class="${theadClasses}">
                        <tr class="py-2 ">
                            ${config.bulkActions?.length ? `
                                <th class="px-2 py-2">
                                    <input type="checkbox" data-select-all>
                                </th>` : ''}
                            <!-- ستون‌ها بعداً بعد از loadData با renderHeader ساخته می‌شوند -->
                            
                        </tr>
                    </thead>


                    <tbody class="${tbodyClasses}">
                        <!-- وضعیت اولیه: در حال بارگذاری -->
                        <tr class="text-center">
                            <td colspan="100%" class="text-center py-6">
                                در حال بارگذاری...
                            </td>
                        </tr>
                    </tbody>

                </table>
            </div>

            <!-- محل رندر pagination -->
            <div class="admin-pagination mt-4"></div>
        </div>
        `

        // رفرنس به جدول و tbody برای استفاده در متدهای دیگر
        this.tableEl = el.querySelector(`#${this.config.tableId}`)
        this.tbody = this.tableEl.querySelector('tbody')

        // --- 7. بایند رویدادهای UI (کلیک، تغییر، جستجو، ...) ---
        this.bindEvents()

        // --- 8. بایند اکشن‌های ستونی (edit/delete/...) ---
        this.bindTableActions()

        // --- 9. لود اولیه داده‌ها از سرور ---
        await this.loadData()

        // --- 10. مقداردهی اولیه پلاگین‌ها (برای انواع ستون‌ها) ---
        this.initPlugins()
        // اگر هنوز از ActionRenderer استفاده می‌کنی:
        // ActionRenderer.bind(this.container)
    },

    /**
     * محاسبه ستون‌های قابل نمایش بر اساس:
     * - شرط visible (تابع)
     * - نقش‌های مورد نیاز (roles)
     * - دسترسی‌های مورد نیاز (permission)
     * اگر هر کدام از این شرایط احراز نشود، ستون نمایش داده نمی‌شود.
     */
    getVisibleColumns(rows) {
        return this.config.columns.filter(col => {
            // اولویت اول: بررسی roles
            if (col.roles && !FormEngine.checkRoles(col.roles)) return false;
            if (col.role && !FormEngine.checkRoles(col.role)) return false;
            // اولویت دوم: بررسی permission
            if (col.permission && !FormEngine.checkPermission(col.permission)) return false;
            if (col.permissions && !FormEngine.checkPermission(col.permissions)) return false;
            // اولویت سوم: بررسی visible (اگر تابع باشد)
            if (typeof col.visible === 'function') {
                // ستون عادی بدون شرط visible → نمایش داده شود
                // if (typeof col.visible !== 'function') return true
                // اگر visible برای حتی یک ردیف true باشد، ستون نمایش داده می‌شود
                return rows.some(row => col.visible(row));
            }
            return true;
        });
    },


    /* رندر کردن هدر جهت نمایش یا عدم نمایش برخی آیتم ها با شرط visible */

    renderHeader() {
        const thead = this.tableEl.querySelector('thead tr')
        if (!thead) return

        const visibleCols = this.getVisibleColumns(this.currentRows)

        thead.innerHTML = `
        ${this.config.bulkActions?.length ? `
            <th class="px-2 py-2">
                <input type="checkbox" data-select-all>
            </th>` : ''}
        ${visibleCols.map(col =>
            `<th class="px-4 py-4">${col.label ?? ''}</th>`
        ).join('')}
    `
    },


    // ========================================================================
    //  رندر کردن فیلترها (renderFilters)
    // ========================================================================

    /**
     * رندر فیلترها (در حال حاضر فقط نوع select را پوشش می‌دهد)
     * - از config.filters می‌خواند
     * - هم object و هم array از options را پشتیبانی می‌کند
     */
    renderFilters(config) {

        if (!config.filters) return ''

        return `
        <div class="flex flex-wrap gap-3 mt-2">
            ${config.filters.map(f => {

            if (f.type === 'select') {

                // حالت 1: options به شکل آرایه [{value, label}, ...]
                if (Array.isArray(f.options)) {

                    return `
                            <select data-filter="${f.key}" class="border px-2 py-1 rounded text-sm">
                                <option value="">${f.label ?? f.key}</option>
                                ${f.options.map(opt =>
                        // String(...) برای جلوگیری از نمایش [object Object]
                        `<option value="${opt.value}">${String(opt.label)}</option>`
                    ).join('')}
                            </select>
                        `
                }

                // حالت 2: options به شکل object ساده {1:'فعال',0:'غیرفعال'}
                const entries = Object.entries(f.options || {})

                return `
                        <select data-filter="${f.key}" class="border px-2 py-1 rounded text-sm">
                            <option value="">${f.label ?? f.key}</option>
                            ${entries.map(([k, v]) =>
                    `<option value="${k}">${String(v)}</option>`
                ).join('')}
                        </select>
                    `
            }

            // اگر نوع فیلتر ناشناخته است، فعلاً چیزی رندر نمی‌کنیم
            return ''

        }).join('')}
        </div>
        `
    },

    // ========================================================================
    //  بایند رویدادهای اصلی جدول (bindEvents)
    // ========================================================================

    bindEvents() {

        const c = this.container

        // --- A) دکمه افزودن (باز کردن مودال create) ---

        c.querySelector('[data-add]')?.addEventListener('click', () => {
            this.openModal('create')
        })

        // --- B) جستجو (search) ---

        const searchInput = c.querySelector('[data-search]')

        if (searchInput) {
            searchInput.addEventListener('input', e => {

                // متن جستجو (با trim برای حذف فاصله‌های ابتدا/انتها)
                this.search = e.target.value.trim()
                // رفتن به صفحه اول بعد از جستجو
                this.page = 1
                // رفرش داده‌های جدول با پارامترهای جدید
                this.loadData()

            })
        }

        // --- C) فیلترها (data-filter) ---

        c.querySelectorAll('[data-filter]').forEach(el => {

            el.addEventListener('change', e => {

                // ثبت مقدار فیلتر در state داخلی
                this.filters[e.target.dataset.filter] = e.target.value
                // همیشه بعد از تغییر فیلتر، از صفحه 1 شروع می‌کنیم
                this.page = 1

                // رفرش داده‌ها با فیلترهای جدید
                this.loadData()

            })

        })

        // --- D) pagination (کلیک روی شماره صفحه - data-page) ---

        c.addEventListener('click', e => {
            if (e.target.dataset.page) {
                const p = parseInt(e.target.dataset.page)
                if (!isNaN(p) && p !== this.page) {
                    this.page = p
                    this.loadData()
                }
            }

        })

        // --- E) per-page (تعداد آیتم در هر صفحه - data-perpage) ---

        c.addEventListener('change', e => {
            if (e.target.dataset.perpage) {
                this.perPage = parseInt(e.target.value)
                this.page = 1
                this.loadData()
            }
        })

        // --- F) bulk actions (انتخاب اکشن گروهی و اجرای آن روی selected) ---

        c.querySelector('[data-bulk]')?.addEventListener('change', async (e) => {

            const actionName = e.target.value
            if (!actionName) return

            const action = this.config.bulkActions.find(a => a.name === actionName)
            if (!action) return

            if (action.handler) {
                // اجرای هندلر اکشن، با آرایه‌ی this.selected و خود اینستنس جدول
                await action.handler(this.selected, this)
            }

            // بعد از اجرا، select را ریست می‌کنیم
            e.target.value = ''

            // لیست selected را خالی می‌کنیم
            this.selected = []

            // بروزرسانی وضعیت چک‌باکس‌ها (تیک‌ها)
            this.refreshCheckboxes()

        })

        // --- G) select-all (تیک زدن/برداشتن همه‌ی ردیف‌ها) ---

        c.querySelector('[data-select-all]')?.addEventListener('change', (e) => {

            if (!this.tableEl) return

            // تمام چک‌باکس‌های data-select را مطابق تیک select-all تنظیم می‌کنیم
            this.tableEl.querySelectorAll('input[data-select]').forEach(cb => {
                cb.checked = e.target.checked
            })

            // بروزرسانی this.selected بر اساس چک‌باکس‌های جدید
            this.updateSelected()

        })

        // --- H) تغییر در چک‌باکس‌های ردیف‌ها (data-select) ---

        this.tbody.addEventListener('change', (e) => {

            if (e.target.dataset.select) {

                // وقتی تیک یک ردیف عوض می‌شود، لیست انتخاب‌شده‌ها را بروزرسانی کن
                this.updateSelected()
                // و وضعیت select-all را هم-sync کن
                this.refreshCheckboxes()

            }

        })
    },

    // ========================================================================
    //  متدهای مرتبط با مودال‌ها
    // ========================================================================

    /**
     * بستن و حذف مودال از DOM
     */
    closeModal(modal) {
        modal?.remove()
    },

    /**
     * باز کردن مودال بر اساس config.modals[name]
     * @param {string} name - نام مودال (مثلاً 'create' یا 'edit')
     * @param {Object} data - داده‌ی اولیه‌ای که به فرم داخل مودال پاس می‌دهیم
     */
    openModal(name, data = {}) {
        const modalConfig = this.config.modals?.[name];
        if (!modalConfig) {
            console.warn(`⚠️ Modal '${name}' not defined in table config`);
            return;
        }

        // ✅ بررسی دسترسی
        const requiredRoles = modalConfig.role || modalConfig.roles;
        if (requiredRoles) {
            const hasAccess = FormEngine.checkRoles(requiredRoles);
            if (!hasAccess) {
                AppAlert.showError('شما دسترسی لازم برای این عملیات را ندارید.');
                return;
            }
        }

        const modal = PluginManager.get('ui', 'modal');
        modal.open({
            title: modalConfig.title,
            theme: this.config.modalTheme || 'light',
            width: modalConfig.width || '600px',
            type: name, // 'create' یا 'edit' برای استایل هدر
            content: container => {
                FormEngine.render(modalConfig.form, container, data);
            }
        });
    },

    // ========================================================================
    //  انتخاب گروهی (bulk selection)
    // ========================================================================

    /**
     * بروزرسانی this.selected بر اساس چک‌باکس‌های تیک خورده
     */
    updateSelected() {

        if (!this.tableEl) return

        const checkboxes = this.tableEl.querySelectorAll('input[data-select]:checked')

        this.selected = Array.from(checkboxes).map(cb => cb.value)

    },

    /**
     * sync کردن وضعیت چک‌باکس‌ها و select-all بر اساس this.selected
     */
    refreshCheckboxes() {

        if (!this.tableEl) return

        const selectAll = this.tableEl.querySelector('input[data-select-all]')
        const checkboxes = this.tableEl.querySelectorAll('input[data-select]')

        if (!selectAll) return

        const allChecked =
            this.selected.length === this.currentRows.length &&
            this.currentRows.length > 0

        selectAll.checked = allChecked

        checkboxes.forEach(cb => {
            cb.checked = this.selected.includes(cb.value)
        })

    },

    // ========================================================================
    //  بارگذاری داده از API (loadData)
    // ========================================================================

    /**
     * فراخوانی API برای دریافت داده‌های جدول بر اساس:
     * - page, per_page, search, filters, tableId
     * و سپس رندر ردیف‌ها و pagination
     */
    async loadData() {

        try {

            // ساخت route نهایی با کمک AppAlert.route
            const route = AppAlert.route(this.config.endpoint)

            const url = new URL(route, window.location.origin)

            // پارامترهای pagination
            url.searchParams.append('page', this.page)

            if (this.perPage) url.searchParams.append('per_page', this.perPage)
            // پارامتر جستجو
            if (this.search) url.searchParams.append('search', this.search)

            // فیلترها
            Object.entries(this.filters).forEach(([k, v]) => {
                if (v) url.searchParams.append(k, v)
            })

            // اگر برای تشخیص جدول در response لازم است
            if (this.config.tableId) {
                url.searchParams.append('tableId', this.config.tableId)
            }

            const res = await fetch(url)

            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`)

            const json = await res.json()

            // داده‌ی اصلی و meta
            this.currentRows = json.data ?? []
            this.meta = json.meta ?? null

            // رندر بدنه‌ی جدول
            this.renderRows(this.currentRows)

            // ⭐ رندر هدر جدول بعد از تعیین ستون‌های visible
            this.renderHeader()

            const paginationContainer = this.container.querySelector('.admin-pagination')

            // فقط وقتی pagination لازم است که total > per_page
            if (this.meta && this.meta.total > (this.meta.per_page || this.perPage)) {
                TableRenderer.renderPagination(this.meta, this)
            } else {
                paginationContainer.innerHTML = ''
            }

        } catch (e) {

            console.error(`⚠ Table ${this.config.tableId} load error:`, e)

            // نمایش پیام خطا داخل جدول
            this.tbody.innerHTML = `
                <tr>
                    <td colspan="100%" class="text-center py-6 text-red-500">
                        خطا در دریافت داده‌ها: ${e.message}
                    </td>
                </tr>
            `
        }
    },

    // ========================================================================
    //  رندر ردیف‌ها و ستون‌ها
    // ========================================================================

    /**
     * رندر کل tbody بر اساس آرایه‌ی rows
     */
    renderRows(rows) {

        if (!this.tbody) return

        // اگر داده‌ای نیست
        if (!rows || rows.length === 0) {

            this.tbody.innerHTML =
                `<tr><td colspan="100%" class="text-center py-6">داده‌ای یافت نشد</td></tr>`

            // وقتی لیست خالی می‌شود، انتخاب‌ها را هم ریست کن
            this.selected = []

            return
        }

        // ساخت HTML ردیف‌ها
        const visibleCols = this.getVisibleColumns(rows)

        this.tbody.innerHTML = rows.map(row => `
            <tr class="border-b hover:bg-gray-300 group/row" data-row-id="${row.id}">
                ${this.config.bulkActions?.length ? `
                    <td class="px-2 py-2">
                        <input type="checkbox"
                               data-select
                               value="${row.id}"
                               ${this.selected.includes(String(row.id)) ? 'checked' : ''}>
                    </td>` : ''}
                
                ${visibleCols.map(col =>
            `<td class="px-4 py-2">${this.renderColumn(col, row)}</td>`
        ).join('')}
            </tr>
        `).join('')


        // بعد از رندر، وضعیت چک‌باکس‌ها و select-all را sync کنیم
        this.refreshCheckboxes()

    },

    /**
     * رندر یک سلول (ستون خاص برای یک ردیف)
     * - اگر نوع ستون actions باشد، دکمه‌ها را می‌سازد
     * - اگر پلاگین نوع ستون موجود باشد، از آن استفاده می‌کند
     * - در غیر این صورت، مقدار خام row[key] را نشان می‌دهد
     */
    renderColumn(column, row) {

        // ✅ پشتیبانی از visible
        if (typeof column.visible === 'function' && !column.visible(row)) {
            return '' // سلول خالی
        }

        // ستون مخصوص اکشن‌ها
        if (column.type === 'actions') {
            return this.buildActionButtons(row, column)
        }

        // اگر پلاگین برای نوع ستون تعریف شده
        const plugin = PluginManager.get('column', column.type)

        if (plugin?.render) {
            return plugin.render(column, row, this)
        }

        // حالت ساده: فقط یک مقدار از row را نمایش بده
        const value = row[column.key]

        return value ?? '-'
    },

    // ========================================================================
    //  ساخت دکمه‌های اکشن در هر ردیف (edit/delete/...)
    // ========================================================================

    buildActionButtons(row, column) {

        // تعریف اکشن‌ها در config.actions
        const configActions = this.config.actions ?? {}
        // لیست اکشن‌هایی که این ستون باید نمایش دهد
        let definedColumnActions = column.actions ?? []

        if (typeof definedColumnActions === 'function') {
            definedColumnActions = definedColumnActions(row)
        }



        const btns = []

        definedColumnActions.forEach(name => {

            const action = configActions[name]

            if (!action) return

            let config = {}

            if (typeof action === 'function') {

                // اگر خود action یک تابع باشد، meta را از روی خود تابع می‌خوانیم
                config = {
                    handler: action,
                    icon: action.icon,
                    color: action.color,
                    tooltip: action.tooltip,
                    confirm: action.confirm,
                    size: action.size,
                    roles: action.roles,
                    permission: action.permission
                }

            } else {

                // اگر action یک object config باشد
                config = action

            }

            /* RBAC CHECK */
            // console.log('Action name:', name, 'Config:', config);


            // بررسی roles (آرایه یا رشته)
            if (config.roles) {
                const rolesArray = Array.isArray(config.roles) ? config.roles : [config.roles];
                if (!FormEngine.checkRoles(rolesArray)) return;
            }
            if (config.role) {
                const roleArray = Array.isArray(config.role) ? config.role : [config.role];
                if (!FormEngine.checkRoles(roleArray)) return;
            }
            // بررسی permission (آرایه یا رشته)
            if (config.permission) {
                const permArray = Array.isArray(config.permission) ? config.permission : [config.permission];
                if (!FormEngine.checkPermission(permArray)) return;
            }
            if (config.permissions) {
                const permsArray = Array.isArray(config.permissions) ? config.permissions : [config.permissions];
                if (!FormEngine.checkPermission(permsArray)) return;
            }


            const sizeClass = config.size || 'text-sm'

            const iconHTML = config.icon
                ? `<i class="${config.icon} ${sizeClass}"></i>`
                : name

            const colorClass = config.color || 'text-gray-600 hover:text-black'

            const tooltip = config.tooltip || name

            // دکمه‌ی اکشن
            btns.push(`
            <button
                type="button"
                data-action="${name}"
                data-id="${row.id}"
                class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-gray-100 transition ${colorClass}"
                title="${tooltip}">
                ${iconHTML}
            </button>
        `)

        })

        if (!btns.length) return ''

        return `<div class="flex justify-center items-center gap-1">${btns.join('')}</div>`
    },


    // ========================================================================
    //  بایند هندلر اکشن‌های ستونی (bindTableActions)
    // ========================================================================

    /**
     * لیسنر کلیک روی دکمه‌های data-action داخل جدول
     * هندلر مناسب را از this.config.actions پیدا و اجرا می‌کند
     */
    bindTableActions() {

        // فقط یکبار بایند شود
        if (this.__actionsBound) return

        this.__actionsBound = true

        this.container.addEventListener('click', (e) => {

            // نزدیک‌ترین المنت با data-action (خود دکمه یا آیکن داخلش)
            const button = e.target.closest('[data-action]')

            if (!button) return

            const actionName = button.dataset.action
            const id = button.dataset.id

            const actionConfig = this.config.actions?.[actionName]

            if (!actionConfig) return

            // هندلر واقعی اکشن
            const handler =
                typeof actionConfig === 'function'
                    ? actionConfig
                    : actionConfig?.handler

            if (typeof handler !== 'function') return

            // پیدا کردن ردیف مربوطه در currentRows
            const row = this.currentRows.find(r => String(r.id) === String(id))

            if (!row) return

            // پیام تأیید (در صورت تعریف شدن)
            let confirmMessage = actionConfig?.confirm || handler?.confirm

            if (confirmMessage) {

                if (!confirm(confirmMessage)) return

            }

            // اجرای هندلر: handler(row, tableInstance, event)
            handler(row, this, e)

        })

    },

    // ========================================================================
    //  متدهای کمکی برای به‌روزرسانی، حذف و افزودن ردیف‌ها
    // ========================================================================

    /**
     * به‌روزرسانی یک ردیف (اگر پیدا نشود، آن را اضافه می‌کند)
     * @param {Object} newRow - ردیف جدید از سرور
     */
    updateRow(newRow) {

        const index = this.currentRows.findIndex(r => String(r.id) === String(newRow.id))

        if (index === -1) {

            // اگر ردیف در لیست فعلی نبود، به عنوان ردیف جدید اضافه کن
            this.addRow(newRow)

            return

        }

        // جایگزینی داده‌ی قدیمی با داده‌ی جدید
        this.currentRows[index] = newRow

        const tbody = this.tableEl.querySelector('tbody')

        // DOM ردیف مورد نظر (بر اساس همان index)
        const rowToUpdate = tbody.children[index]

        if (!rowToUpdate) {

            // اگر به هر دلیلی ردیف در DOM نبود، کل جدول را از نو رندر کن
            this.renderRows(this.currentRows)
            // رندر مجدد هدر
            this.renderHeader()

            return

        }

        // بازسازی HTML آن ردیف
        const visibleCols = this.getVisibleColumns(this.currentRows)

        rowToUpdate.innerHTML = `
        ${this.config.bulkActions?.length ? `
            <td class="px-2 py-2">
                <input type="checkbox"
                       data-select
                       value="${newRow.id}"
                       ${this.selected.includes(String(newRow.id)) ? 'checked' : ''}>
            </td>` : ''}
        
        ${visibleCols.map(col =>
            `<td class="px-4 py-2">${this.renderColumn(col, newRow)}</td>`
        ).join('')}
`

    },

    /**
     * حذف یک ردیف از currentRows و DOM
     * @param {string|number} id - شناسه ردیف
     */
    removeRow(id) {

        const index = this.currentRows.findIndex(r => String(r.id) === String(id))

        if (index === -1) return

        // حذف از آرایه‌ی currentRows
        this.currentRows.splice(index, 1)

        const tbody = this.tableEl.querySelector('tbody')

        const rowElement = tbody.children[index]

        // اگر ردیف در DOM هست، فقط همان را حذف کن؛
        // در غیر این صورت کل جدول را دوباره رندر می‌کنیم
        if (rowElement) { rowElement.remove() }
        else {
            this.renderRows(this.currentRows)
            this.renderHeader()
        }


        // حذف شناسه از selected
        this.selected = this.selected.filter(selectedId =>
            String(selectedId) !== String(id)
        )

        // بروزرسانی وضعیت چک‌باکس‌ها
        this.refreshCheckboxes()

    },

    /**
     * اضافه کردن ردیف جدید به ابتدای جدول
     * @param {Object} row - ردیف جدید
     */
    addRow(row) {

        // اضافه کردن در ابتدای لیست
        this.currentRows.unshift(row)

        // رندر مجدد ردیف‌ها
        this.renderRows(this.currentRows)
        // رندر مجدد هدر
        this.renderHeader()

    },


    // ========================================================================
    //  مقداردهی و اجرای پلاگین‌ها (برای ستون‌ها)
    // ========================================================================

    /**
     * اجرای init برای پلاگین ستون‌ها (به جز ستون actions)
     */
    initPlugins() {

        this.config.columns.forEach(col => {

            if (col.type === 'actions') return

            const plugin = PluginManager.get('column', col.type)

            plugin?.init?.(this, col)

        })

    },

}

export default TableEngine
