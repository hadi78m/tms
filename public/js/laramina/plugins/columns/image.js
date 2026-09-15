import PluginManager from '../../core/plugin-manager.js'

PluginManager.register('column', 'image', {

    render(column, row) {

        const src = row[column.key]

        if (!src) return '-'

        return `
            <img
                src="${src}"
                class="w-10 h-10 rounded object-cover"
            >
        `
    }

})
