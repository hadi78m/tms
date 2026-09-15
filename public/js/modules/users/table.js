import { createForm, editForm } from './forms/create-form.js'
import { userActions } from './actions.js'

const publicLang   = AdminLang.getNamespace('common');
const moduleFields = AdminLang.getNamespace('modules.users.fields');
const moduleActions = AdminLang.getNamespace('modules.users.actions');

export default {

    endpoint: 'users.json',
    search: true,

    headerTitle: moduleFields.header_title || (publicLang.manage + ' ' + (moduleFields.title || 'users')),
    addButtonLabel: moduleActions.create || (publicLang.create + ' ' + (moduleActions.item || publicLang.item)),
    displayButton: true,

    actions: userActions,

    perPage: 10,

    modalTheme: 'light',

    modals: {
        create: {
            title: moduleActions.create || publicLang.create,
            width: '500px',
            form: createForm
        },
        edit: {
            title: moduleActions.edit || publicLang.edit,
            width: '500px',
            form: editForm
        }
    },

    filters: [

    ],

    columns: [
        { key: 'id', label: publicLang.id, sortable: true },
        { key: 'name', label: publicLang.name, sortable: true },
        { key: 'email', label: publicLang.email },
        { key: 'email_verified_at', label: publicLang.email_verified_at || moduleFields.email_verified_at },
        { key: 'created_at', label: publicLang.created_at, sortable: true },
        {
            label: publicLang.actions,
            type: 'actions',
            actions: ['edit', 'delete']
        }
    ],

}