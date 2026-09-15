import PluginManager from '../../core/plugin-manager.js'

PluginManager.register('column', 'boolean-icon', {

    // render(column, row) {

    //     const value = row[column.key]

    //     if (value) {
    //         return `<span class="text-green-600">✔</span>`
    //     }

    //     return `<span class="text-red-500">✖</span>`
    // },
    render(column, row) {

        const value = !!row[column.key]

        // آیکن پیش‌فرض
        const defaultIcons = {
            true: `<i class="fa fa-check text-green-600"></i>`,
            false: `<i class="fa fa-times text-red-500"></i>`
        }

        const icons = column.icons || defaultIcons
        const iconHtml = value ? icons.true : icons.false

        const actionName = column.action || null

        // فقط HTML خروجی + data-action + data-id
        return `
            <button 
                class="js-bool inline-flex items-center justify-center cursor-pointer"
                data-action="${actionName || ''}"
                data-id="${row.id}"
                data-endpoint="${column.endpoint || ''}" 
                data-confirm="${column.confirmTitle || ''}">
                ${iconHtml}
            </button>
        `
    },


})
