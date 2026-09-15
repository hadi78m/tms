import PluginManager from '../../core/plugin-manager.js'

PluginManager.register('column', 'toggle-status', {

    render(column, row) {

        const active = row[column.key] ? true : false

        return `
            <button
                data-toggle-status
                data-id="${row.id}"
                class="px-2 py-1 rounded text-xs
                ${active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'}">
                ${active ? 'فعال' : 'غیرفعال'}
            </button>
        `
    },

    init(table, column) {

        table.container.addEventListener('click', async e => {

            const btn = e.target.closest('[data-toggle-status]')
            if (!btn) return

            const id = btn.dataset.id

            const res = await fetch(`${column.endpoint}/${id}/toggle`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]').content
                }
            })

            if (res.ok) {
                table.loadData(table.config, table.tableEl)
            }

        })

    }

})
