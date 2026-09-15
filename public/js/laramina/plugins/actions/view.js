import PluginManager from '../../core/plugin-manager.js'

PluginManager.register('column', 'view-action', {

    render(column, row) {

        return `
            <a href="${column.url}/${row.id}"
               class="text-gray-600 hover:text-gray-800">
                مشاهده
            </a>
        `
    }

})
