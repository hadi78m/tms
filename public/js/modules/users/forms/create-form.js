const publicLang   = AdminLang.getNamespace('common');
const moduleFields = AdminLang.getNamespace('modules.users.fields');
const moduleActions = AdminLang.getNamespace('modules.users.actions');

// ─── فیلدهای مشترک بین هر دو فرم ───
const commonFields = [
        {
            name: 'name',
            label: publicLang.name,
            type: 'text'
        },
        {
            name: 'email',
            label: publicLang.email,
            type: 'email'
        },
        {
            name: 'email_verified_at',
            label: publicLang.email_verified_at || moduleFields.email_verified_at,
            type: 'email'
        },
        {
            name: 'remember_token',
            label: publicLang.remember_token || moduleFields.remember_token,
            type: 'text'
        }
];

// ─── فرم ایجاد (با رمز عبور اجباری) ───
export const createForm = {

    endpoint: 'users.store',
    deleteEndpoint: 'users.destroy',

    title: moduleActions.create || publicLang.create,

    fields: [
        ...commonFields,
        {
            name: 'password',
            label: publicLang.password,
            type: 'password',
            required: true,
            min: 6,
            placeholder: 'حداقل ۶ کاراکتر',
            helper: 'گذرواژه مناسب باید حداقل ۸ کاراکتر و شامل حروف، اعداد و نمادها باشد',
        },
    ],

    buttons: {
        submit: publicLang.save,
        cancel: publicLang.cancel,
    }
};

// ─── فرم ویرایش (با رمز عبور اختیاری) ───
export const editForm = {

    updateEndpoint: 'users.update',

    title: moduleActions.edit || publicLang.edit,

    fields: [
        ...commonFields,
        {
            name: 'password',
            label: 'رمز عبور جدید',
            type: 'password',
            placeholder: 'در صورت تمایل تغییر دهید',
            hideValue: true,
            value: '',
            helper: 'در صورت تمایل رمز عبور جدید وارد کنید در غیر این صورت خالی بگذارید',
        },
    ],

    buttons: {
        submit: publicLang.save,
        cancel: publicLang.cancel,
    }
};