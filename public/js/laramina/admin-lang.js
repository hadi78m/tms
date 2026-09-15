(function (global) {
    /**
     * AdminLang: ساده، قابل گسترش، بدون وابستگی به فریم‌ورک
     */
    const AdminLang = {
        locale: (global.AdminLang && global.AdminLang.locale) || 'fa',
        messages: (global.AdminLang && global.AdminLang.messages) || {},

        t(key, defaultValue = null, locale = null) {
            const lang = locale || this.locale;
            const langMessages = this.messages[lang] || {};
            const segments = key.split('.');
            let current = langMessages;

            for (let i = 0; i < segments.length; i++) {
                const k = segments[i];
                if (current && Object.prototype.hasOwnProperty.call(current, k)) {
                    current = current[k];
                } else {
                    return defaultValue !== null ? defaultValue : key;
                }
            }
            return current;
        },

        setLocale(locale) {
            if (this.messages[locale]) {
                this.locale = locale;
            }
        },

        /**
         * افزودن/مرج ترجمه‌ها در runtime
         */
        mergeMessages(newMessages) {
            this.messages = this._deepMerge(this.messages, newMessages);
        },

        _deepMerge(target, source) {
            const output = Object.assign({}, target);
            if (isObject(target) && isObject(source)) {
                Object.keys(source).forEach(key => {
                    if (isObject(source[key])) {
                        if (!(key in target)) {
                            Object.assign(output, { [key]: source[key] });
                        } else {
                            output[key] = this._deepMerge(target[key], source[key]);
                        }
                    } else {
                        Object.assign(output, { [key]: source[key] });
                    }
                });
            }
            return output;

            function isObject(obj) {
                return obj && typeof obj === 'object' && !Array.isArray(obj);
            }
        },
        getNamespace(ns, locale = null) {
            const lang = locale || this.locale;
            const langMessages = this.messages[lang] || {};
            const segments = ns.split('.');
            let current = langMessages;

            for (let i = 0; i < segments.length; i++) {
                const k = segments[i];
                if (current && Object.prototype.hasOwnProperty.call(current, k)) {
                    current = current[k];
                } else {
                    return {};
                }
            }
            return current || {};
        }
    };


    global.AdminLang = AdminLang;
})(window);
