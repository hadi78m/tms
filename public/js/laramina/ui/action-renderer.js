import PermissionManager from '../core/permission-manager.js'

export default class ActionRenderer {

    static STYLES = {
        view: 'px-2 py-1 text-indigo-600 hover:text-indigo-800',
        edit: 'px-2 py-1 text-blue-600 hover:text-blue-800',
        delete: 'px-2 py-1 text-red-600 hover:text-red-800',
        default: 'px-2 py-1 text-gray-600 hover:text-gray-800'
    }

    static ICONS = {
        view: '<i class="fas fa-eye"></i>',
        edit: '<i class="fas fa-edit"></i>',
        delete: '<i class="fas fa-trash"></i>'
    }

    static normalize(action) {
        if (typeof action === 'string') {
            return {
                name: action,
                roles: null,
                label: null
            }
        }

        return {
            name: action.name,
            roles: action.roles || null,
            label: action.label || null
        }
    }

    static render(actions = [], row, column = {}) {
        const baseUrl = column.baseUrl || ''

        return actions
            .map(a => this.renderSingle(a, row, baseUrl))
            .filter(Boolean)
            .join('')
    }

    static renderSingle(action, row, baseUrl) {
        const config = this.normalize(action)

        if (config.roles && !PermissionManager.checkRoles(config.roles)) {
            return ''
        }

        if (config.name === 'view' || config.name === 'edit') {
            return this.renderLink(config, row, baseUrl)
        }

        return this.renderButton(config, row)
    }

    static renderLink(config, row, baseUrl) {
        const href = `${baseUrl}/${row.id}${config.name === 'edit' ? '/edit' : ''}`
        const style = this.STYLES[config.name] || this.STYLES.default
        const icon = config.label || this.ICONS[config.name] || config.name

        return `
            <a href="${href}" class="${style}">
                ${icon}
            </a>
        `
    }

    static renderButton(config, row) {
        const style = this.STYLES[config.name] || this.STYLES.default
        const icon = config.label || this.ICONS[config.name] || config.name

        return `
            <button
                type="button"
                class="${style} js-table-action"
                data-action="${config.name}"
                data-id="${row.id}"
            >
                ${icon}
            </button>
        `
    }

    static bind(container) {
        if (container.__actionsBound) return
        container.__actionsBound = true

        container.addEventListener('click', (e) => {

            const btn = e.target.closest('.js-table-action')
            if (!btn) return

            const action = btn.dataset.action
            const id = btn.dataset.id

            const table = container.__tableInstance

            if (!table) {
                console.warn('TableEngine instance not found')
                return
            }

            const actions = table.config?.actions

            if (!actions || typeof actions[action] !== 'function') {
                console.warn(`Action "${action}" not defined`)
                return
            }

            actions[action](id, table, btn)
        })
    }


    static bindEvents(container) {
        this.bind(container)
    }

}
