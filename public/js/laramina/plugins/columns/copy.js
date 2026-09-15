import PluginManager from '../../core/plugin-manager.js'

PluginManager.register('column', 'copy', {

    render(column, row) {

        const value = row[column.key]

        return `
            <div class="flex items-center gap-2">
                <span class="text-xs">${value}</span>
                <button
                    data-copy="${value}"
                    class="text-blue-600 text-xs">
                    کپی
                </button>
            </div>
        `
    },

    init(table) {

        table.container.addEventListener('click', e => {

            const btn = e.target.closest('[data-copy]')
            if (!btn) return

            navigator.clipboard.writeText(btn.dataset.copy)

        })

    }

})
