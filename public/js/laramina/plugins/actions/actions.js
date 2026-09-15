import PluginManager from '../../core/plugin-manager.js'
import ActionRenderer from '../../ui/action-renderer.js'

PluginManager.register('column', 'actions', {

    render(column, row) {

        return `
            <div class="flex gap-2">
                ${ActionRenderer.render(column.actions, row, column)}
            </div>
        `
    },

    init(table) {

        ActionRenderer.bind(table.container)

    }

})
