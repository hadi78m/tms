class PluginManager {

    constructor() {
        this.plugins = {
            column: {},
            action: {},
            table: {}
        }
    }

    register(type, name, plugin) {
        if (!this.plugins[type]) {
            this.plugins[type] = {}
        }

        this.plugins[type][name] = plugin
    }

    get(type, name) {
        return this.plugins[type]?.[name] || null
    }

}

export default new PluginManager()
