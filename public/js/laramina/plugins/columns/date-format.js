import PluginManager from '../../core/plugin-manager.js'

PluginManager.register('column', 'date', {

    render(column, row) {

        const value = row[column.key]

        if (!value) return '-'

        const date = new Date(value)

        return date.toLocaleDateString('fa-IR')
    }

})
