class ModuleRegistry {
    constructor() {
        this.modules = {};
    }

    register(module) {
        if (!module || !module.name) {
            throw new Error('Invalid module: "name" is required.');
        }

        this.modules[module.name] = module;
        // console.log(`[Laramina] Module registered: ${module.name}`);

        return module;
    }

    all() {
        return this.modules;
    }

    get(name) {
        return this.modules[name] || null;
    }

    has(name) {
        return !!this.modules[name];
    }
}

export default ModuleRegistry;
