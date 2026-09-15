import ModalPlugin from '/js/laramina/plugins/ui/modal/modal-plugin.js'
import FormEngine from '/js/laramina/engines/form-engine.js'
import { createForm, editForm } from './forms/create-form.js'
import { action } from '/js/laramina/core/action.js'

const publicLang    = AdminLang.getNamespace('common');
const moduleActions = AdminLang.getNamespace('modules.users.actions');

export const userActions = {

    view: (row) => {
        console.log('view userActions', row)
    },

    edit: action({
        icon: 'fas fa-edit',
        color: 'text-blue-600',
        size: 'text-lg',
        tooltip: publicLang.edit,
    }, (row, table, event) => {
        const url = AppAlert.route(editForm.updateEndpoint, { id: row.id });
        event?.preventDefault()

        ModalPlugin.open({
            title: moduleActions.edit || publicLang.edit,
            width: '500px',

            content: (container) => {

                const config = {
                    ...editForm,
                    endpoint: url,
                    method: 'POST'
                }

                FormEngine.render(config, container, row)
            }
        })
    }),

    delete: action({
        icon: 'fas fa-trash',
        color: 'text-red-600',
        size: 'text-lg',
        tooltip: publicLang.delete,
    }, async (row, table, event) => {

        event?.preventDefault()

        const url = AppAlert.route(createForm.deleteEndpoint, { id: row.id });

        const res = await AppAlert.confirmDelete(url, {
            title: moduleActions.delete_title || (publicLang.delete + ' ' + (moduleActions.item || publicLang.item)),
        })

        if (res) {
            document.dispatchEvent(
                new CustomEvent('admin:table:remove-row', {
                    detail: { id: row.id }
                })
            )
        }
    }),

    setToggle(id, table, endpoint) {
        const url = AppAlert.route(endpoint, { id });

        return AppAlert.post(url, {}, {
            loading: true,
            successAlert: true
        }).done((res) => {

            // اگر update شامل 'table' بود، کل جدول را رفرش کن
            if (res.update && res.update == 'table') {
                if (typeof table.loadData === 'function') {
                    table.loadData();  // رفرش با حفظ page/filter
                }
                else {
                    console.warn('table.loadData is not a function');
                }
                return;
            }

            // در غیر اینصورت، فقط همان ردیف را آپدیت کن

            // ۱) اگر سرور ردیف کامل را برگرداند
            if (res.provider && typeof table.updateRow === 'function') {
                table.updateRow(res.provider);
                return;
            }

            // ۲) fallback: ردیف قدیمی را بگیر و patch کن (در مواقعی که فقط چند فیلد ساده برگشتند)

            const oldRow = table.getRowById
                ? table.getRowById(id)
                : (table.currentRows || []).find(r => r.id == id);

            if (!oldRow) {
                console.warn("Row not found:", id);
                return;
            }

            // برای جلوگیری از خراب‌کاری، همه res را merge نکن
            // فقط فیلدهای مجاز را patch کن
            const allowedKeys = ['is_active', 'is_default'];
            const patch = {};
            allowedKeys.forEach(k => {
                if (k in res) patch[k] = res[k];
            });

            const newRow = { ...oldRow, ...patch };

            if (typeof table.updateRow === 'function') {
                table.updateRow(newRow);
            } else {
                console.warn('table.updateRow is not a function');
            }
        });
    },
};