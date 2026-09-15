const ModalPlugin = {

    instance: null,
    theme: 'light',
    successHandler: null,
    errorHandler: null,
    isClosing: false,
    activeForm: null,
    type: 'add',

    init() {
        if (this.instance) return;

        const wrapper = document.createElement('div');
        wrapper.className = `
            admin-modal hidden fixed inset-0
            bg-black/40 flex items-center justify-center z-50
        `;
        wrapper.innerHTML = `
            <div class="admin-modal-box bg-white rounded-lg shadow-xl overflow-hidden"
                 style="width:550px; max-width:90%; max-height:90vh; display:flex; flex-direction:column;">
                <div class="admin-modal-header p-4 border-b flex justify-between items-center">
                    <h3 class="modal-title font-bold text-lg"></h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="admin-modal-body p-4 overflow-y-auto flex-1">
                    <!-- محتوای مودال اینجا قرار می‌گیرد -->
                </div>
                <div class="border-t px-4 py-3 flex justify-center gap-3">
                    <button data-submit class="saveBtn flex-1 px-4 py-2 bg-gradient-to-r">ذخیره</button>
                    <button data-cancel class="flex-1 px-4 py-2 bg-gray-200 text-black rounded hover:bg-gray-300">انصراف</button>
                </div>
            </div>
        `;
        document.body.appendChild(wrapper);

        // ✅ فقط دکمه close (X) و دکمه انصراف مودال را می‌بندند
        wrapper.querySelector('.modal-close').addEventListener('click', () => this.close());
        // ✅ بسته شدن با کلیک روی پس‌زمینه (غیرفعال)
        // wrapper.addEventListener('click', e => { if (e.target === wrapper) this.close(); });

        this.instance = wrapper;
    },

    async open(options = {}) {
        this.init();

        const modal = this.instance;
        const bodyEl = modal.querySelector('.admin-modal-body');
        const titleEl = modal.querySelector('.modal-title');
        const submitBtn = modal.querySelector('[data-submit]');
        const cancelBtn = modal.querySelector('[data-cancel]');
        const boxEl = modal.querySelector('.admin-modal-box');

        this.isClosing = false;
        this.activeForm = null;

        // در open متد، قبل از titleEl.innerText
        const escapeHtml = (str) => {
            if (!str) return '';
            return str.replace(/[&<>]/g, function (m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        };
        titleEl.innerText = escapeHtml(options.title || '');
        if (options.width) boxEl.style.width = options.width;
        if (options.maxHeight) boxEl.style.maxHeight = options.maxHeight;
        if (options.theme) this.setTheme(options.theme);
        if (options.type) this.setHeader(options.type);

        bodyEl.innerHTML = '';
        if (typeof options.content === 'function') {
            await options.content(bodyEl);
        }

        const form = bodyEl.querySelector('form');
        this.activeForm = form;

        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerText = 'ذخیره';

        // Remove previous event listeners
        if (this.successHandler) {
            document.removeEventListener('admin:form:success', this.successHandler);
            this.successHandler = null;
        }
        if (this.errorHandler) {
            document.removeEventListener('admin:form:error', this.errorHandler);
            this.errorHandler = null;
        }

        // Define new handlers
        this.successHandler = (e) => {
            // یافتن دکمه در مودال جاری بدون مقایسه form
            const currentModal = this.instance;
            if (currentModal) {
                const submitBtn = currentModal.querySelector('[data-submit]');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerText = 'ذخیره';
                }
            }

            const row = e.detail?.row;
            if (row) {
                document.dispatchEvent(new CustomEvent('admin:table:update-row', { detail: { row } }));
            } else {
                document.dispatchEvent(new CustomEvent('admin:table:reload'));
            }

            this.close();
        };

        this.errorHandler = (e) => {
            // یافتن دکمه در مودال جاری
            const currentModal = this.instance;
            if (currentModal) {
                const submitBtn = currentModal.querySelector('[data-submit]');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerText = 'ذخیره';
                }
            }
        };

        document.addEventListener('admin:form:success', this.successHandler);
        document.addEventListener('admin:form:error', this.errorHandler);

        // Submit button click handler
        submitBtn.onclick = () => {
            const currentForm = this.activeForm || bodyEl.querySelector('form');
            if (!currentForm) {
                console.error('No form to submit');
                return;
            }
            if (submitBtn.disabled) return;
            if (!currentForm.checkValidity()) {
                currentForm.reportValidity();
                return;
            }
            submitBtn.disabled = true;
            submitBtn.innerText = 'در حال ارسال...';
            currentForm.requestSubmit();
        };

        cancelBtn.onclick = () => this.close();

        modal.classList.remove('hidden', 'fade-out');
        modal.classList.add('fade-in');
        this.applyTheme(this.theme);
        this.applyHeader(this.type);
    },

    close() {
        if (!this.instance || this.isClosing) return;

        this.isClosing = true;
        const modal = this.instance;
        const body = modal.querySelector('.admin-modal-body');
        const submitBtn = modal.querySelector('[data-submit]');

        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerText = 'ذخیره';
        }

        modal.classList.remove('fade-in');
        modal.classList.add('fade-out');

        setTimeout(() => {
            modal.classList.add('hidden');
            if (body) body.innerHTML = '';
            this.activeForm = null;

            if (this.successHandler) {
                document.removeEventListener('admin:form:success', this.successHandler);
                this.successHandler = null;
            }
            if (this.errorHandler) {
                document.removeEventListener('admin:form:error', this.errorHandler);
                this.errorHandler = null;
            }

            this.isClosing = false;
        }, 250);
    },

    setTheme(theme) {
        this.theme = theme;
        this.applyTheme(theme);
    },
    setHeader(type) {
        this.type = type;
        this.applyHeader(type);
    },

    applyTheme(theme) {
        if (!this.instance) return;
        const box = this.instance.querySelector('.admin-modal-box');
        if (theme === 'dark') {
            box.classList.remove('bg-white', 'text-black');
            box.classList.add('bg-gray-600', 'text-white');
        } else {
            box.classList.remove('bg-gray-600', 'text-white');
            box.classList.add('bg-white', 'text-black');
        }
    },

    applyHeader(type) {
        if (!this.instance) return;
        const header = this.instance.querySelector('.admin-modal-header');
        const saveBtn = this.instance.querySelector('.saveBtn');
        const headerBgAdd = ['bg-gradient-to-r', 'from-cyan-600', 'to-blue-600', 'hover:from-cyan-700', 'hover:to-blue-700', 'text-white'];
        const headerBgEdit = ['bg-gradient-to-r', 'from-green-600', 'to-emerald-600', 'hover:from-teal-700', 'hover:to-emerald-700', 'text-white'];
        header.classList.remove(...headerBgAdd, ...headerBgEdit);
        saveBtn.classList.remove(...headerBgAdd, ...headerBgEdit);
        if (type === 'edit') {
            header.classList.add(...headerBgEdit);
            saveBtn.classList.add(...headerBgEdit);
        } else {
            header.classList.add(...headerBgAdd);
            saveBtn.classList.add(...headerBgAdd);
        }
    },
};

const style = document.createElement('style');
style.innerHTML = `
    .fade-in{animation:adminModalFadeIn .25s ease}
    .fade-out{animation:adminModalFadeOut .25s ease}
    @keyframes adminModalFadeIn {
        from{opacity:0;transform:scale(.95)}
        to{opacity:1;transform:scale(1)}
    }
    @keyframes adminModalFadeOut {
        from{opacity:1;transform:scale(1)}
        to{opacity:0;transform:scale(.95)}
    }
`;
document.head.appendChild(style);

export default ModalPlugin;