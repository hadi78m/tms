import PluginManager from '../../core/plugin-manager.js'

PluginManager.register('column', 'badge', {

    render(column, row) {

        const value = row[column.key]

        const map = column.map || {}

        const item = map[value] || {
            label: value,
            color: 'gray'
        }

        return `
            <span class="px-2 py-1 text-xs rounded bg-${item.color}-100 text-${item.color}-700">
                ${item.label}
            </span>
        `
    }

})
