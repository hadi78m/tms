import PluginManager from '../../core/plugin-manager.js'

PluginManager.register('column', 'edit-action', {

    render(column, row) {

        return `
            <a href="${column.url}/${row.id}/edit"
               class="text-blue-600 hover:text-blue-800">
                ویرایش
            </a>
        `
    }

})
