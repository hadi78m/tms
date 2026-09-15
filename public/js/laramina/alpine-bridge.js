// js/laramina/alpine-bridge.js
import Alpine from 'alpinejs';
import { TableEngine } from './engines/table-engine.js';
import FormEngine from './engines/form-engine.js';

// ثبت کامپوننت Alpine برای جدول
Alpine.data('adminTable', (moduleName) => ({
    tableInstance: null,
    loading: true,
    
    async init() {
        const module = await window.Laramina.loader.loadByName(moduleName);
        if (module && module.table) {
            this.tableInstance = { ...TableEngine };
            await this.tableInstance.render(module.table, this.$el);
            this.loading = false;
        }
    }
}));

// ثبت کامپوننت برای فرم
Alpine.data('adminForm', (formConfig) => ({
    formData: {},
    errors: {},
    loading: false,
    
    async submit() {
        this.loading = true;
        this.errors = {};
        
        try {
            const result = await FormEngine.submit(this.$refs.form, formConfig.endpoint);
            if (result.success) {
                this.$dispatch('form-success', result);
                if (formConfig.onSuccess) {
                    formConfig.onSuccess(result);
                }
            }
        } catch (error) {
            if (error.errors) {
                this.errors = error.errors;
            }
        } finally {
            this.loading = false;
        }
    }
}));