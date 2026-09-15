// 1️⃣ register plugins
import '../plugins/index.js'
// 2️⃣ core
import ModuleRegistry from '../core/module-registry.js';
import ModuleLoader from '../core/module-loader.js';
// 3️⃣ engines
// 4️⃣ renderers
// 5️⃣ start system


/*
 * no need this  now i add new register plugins
 */

// import '../plugins/columns/toggle-status.js'
// import '../plugins/columns/is-default.js'
// import '../plugins/columns/badge.js'
// import '../plugins/columns/boolean-icon.js'
// import '../plugins/columns/date-format.js'
// import '../plugins/columns/image.js'
// import '../plugins/columns/copy.js'
// import '../plugins/actions/actions.js'




class Laramina {
    constructor() {
        this.registry = new ModuleRegistry();
        this.loader = new ModuleLoader();
    }

    async boot() {
        await this.loader.discover();
    }

    register(module) {
        return this.registry.register(module);
    }
}

window.Laramina = new Laramina();

document.addEventListener('DOMContentLoaded', async () => {
    await window.Laramina.boot();
});

export default window.Laramina;
