import PluginManager from '../../core/plugin-manager.js'

PluginManager.register('column', 'action-toggle', {

    render(column, row) {

        const active = !!row[column.key]

        let label, color

        // 1) اگر icons تعریف شده باشد → اولویت دارد
        if (column.icons) {
            const item = active ? column.icons.true : column.icons.false
            label = item.html
            color = item.color || (active ? 'green' : 'gray')
        }

        // 2) اگر icons نبود → map استفاده شود
        else if (column.map) {
            label = column.map[active]?.label || (active ? 'فعال' : 'غیرفعال')
            color = column.map[active]?.color || (active ? 'green' : 'gray')
        }

        // 3) پیش‌فرض نهایی بدون icons و بدون map
        else {
            label = active ? 'فعال' : 'غیرفعال'
            color = active ? 'green' : 'gray'
        }

        return `
        <button
            type="button"
            data-toggle-action
            data-id="${row.id}"
            data-endpoint="${column.endpoint || ''}"
            data-confirm="${column.confirmTitle || ''}"
            class="px-2 py-1 rounded text-xs transition bg-${color}-100 text-${color}-700"
        >
            ${label}
        </button>
    `
    }
    ,

    init(table) {

        if (table.container.__actionToggleBound) return
        table.container.__actionToggleBound = true

        table.container.addEventListener('click', async (e) => {

            const btn = e.target.closest('[data-toggle-action]')
            if (!btn) return

            const id = btn.dataset.id
            const endpoint = btn.dataset.endpoint

            const column = table.config.columns.find(c => c.endpoint === endpoint)

            const confirmed = await Swal.fire({
                title: column.confirmTitle || 'تغییر وضعیت؟',
                text: column.confirmText || '',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'تایید',
                cancelButtonText: 'انصراف',
            })
            if (!confirmed.isConfirmed) return

            // Loader در دکمه
            const originalHTML = btn.innerHTML
            btn.disabled = true
            btn.innerHTML = `
                <i class="fa fa-spinner fa-spin"></i>
                <span>در حال انجام...</span>
            `

            try {
                await table.config.actions.setToggle(id, table, endpoint)
            } catch (err) {
                console.error(err)
            } finally {
                btn.disabled = false
                btn.innerHTML = originalHTML
            }
        })
    }

})
