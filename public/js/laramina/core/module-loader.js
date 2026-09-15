import TableEngine from '../engines/table-engine.js';

class ModuleLoader {
    constructor() {
        this.modules = [];
        this.loadingPromises = new Map();
        this.failedModules = new Set();
    }

    resolvePath(moduleName) {
        const paths = [
            `/js/modules/${moduleName}/module.js`,
            `/js/modules/${moduleName}.js`,
            `/js/laramina/modules/${moduleName}/module.js`,
            `/vendor/laramina/js/modules/${moduleName}/module.js`
        ];

        console.log('host name is :',window.location.hostname );
        // ✅ در محیط توسعه، یک پارامتر نسخه (timestamp) به مسیر اضافه کن تا کش مرورگر و ماژول باطل شود
        const isDevelopment = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' || window.location.hostname === '*.test' || window.location.hostname === '*.dev';
        const versionParam = isDevelopment ? `?t=${Date.now()}` : '';

        return paths.map(path => path + versionParam);
    }

    async loadByName(moduleName, mountEl, force = false) {
        // اگر force=true بود، کش قدیمی را حذف کن
        if (force) {
            if (this.loadingPromises.has(moduleName)) {
                this.loadingPromises.delete(moduleName);
            }
            if (this.failedModules.has(moduleName)) {
                this.failedModules.delete(moduleName);
            }
            // حذف از modules registry هم لازم است
            if (window.Laramina?.registry?.modules?.[moduleName]) {
                delete window.Laramina.registry.modules[moduleName];
            }
        }

        if (this.loadingPromises.has(moduleName)) {
            return this.loadingPromises.get(moduleName);
        }

        if (this.failedModules.has(moduleName)) {
            this.renderMountError(mountEl, `Module "${moduleName}" previously failed to load`);
            return null;
        }

        const loadPromise = this.loadModule(moduleName, mountEl);
        this.loadingPromises.set(moduleName, loadPromise);

        try {
            const module = await loadPromise;
            return module;
        } catch (error) {
            this.failedModules.add(moduleName);
            return null;
        } finally {
            this.loadingPromises.delete(moduleName);
        }
    }

    async loadModule(moduleName, mountEl) {
        const paths = this.resolvePath(moduleName);
        let lastError = null;

        for (const path of paths) {
            try {
                // ✅ import با cache-busting
                const imported = await import(/* @vite-ignore */ path);
                const module = imported.default || imported;

                if (!window.Laramina?.registry) {
                    throw new Error('Laramina registry is not available.');
                }

                window.Laramina.registry.register(module);
                this.modules.push(module);

                await this.mount(module, mountEl);

                if (module.init && typeof module.init === 'function') {
                    await module.init();
                }

                return module;
            } catch (error) {
                lastError = error;
                console.warn(`Failed to load module from "${path}":`, error);
            }
        }

        console.error(`[ModuleLoader] Failed to load module "${moduleName}" from all paths`, lastError);
        this.renderMountError(mountEl, `Failed to load module: ${moduleName}`);
        return null;
    }

    async discover() {
        const mounts = document.querySelectorAll('[data-module]');
        for (const mountEl of mounts) {
            const moduleName = mountEl.dataset.module;
            if (!moduleName) continue;
            await this.loadByName(moduleName, mountEl);
        }
    }

    async mount(module, mountEl) {
        if (!module || !mountEl) return;
        mountEl.innerHTML = '';
        if (module.table) {
            await TableEngine.render(module.table, mountEl);
            return;
        }
        mountEl.innerHTML = `
            <div class="p-4 text-yellow-600 border border-yellow-300 rounded">
                ماژول "${module.name}" لود شد اما هیچ table configای برای render ندارد.
            </div>
        `;
    }

    renderMountError(el, message) {
        if (!el) return;
        el.innerHTML = `
            <div class="p-4 text-red-600 border border-red-300 rounded">
                ${message}
            </div>
        `;
    }
}

export default ModuleLoader;