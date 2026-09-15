# شیوه ساخت فرم ها

## 1. قابلیت: Select با API (Dynamic AJAX Options)

### در فایل createForm می‌توانی داخل یک فیلد Select این‌طور تعریف کنی:

```javascript
{
    name: 'city_id',
    label: 'شهر',
    type: 'select',
    ajax: {
        url: '/api/cities',
        valueKey: 'id',
        labelKey: 'name',
        params: { country_id: 1 }
    }
}
```

## قابلیت: Select جستجوپذیر (Searchable Select)

### بدون کتابخانه خارجی! (فقط Tailwind + JS)

###### در createForm:

```javascript
{
    name: "provider_type",
    label: "نوع پرووایدر",
    type: "select",
    searchable: true,
    options: [
        { value: "ippanel", label: "IP Panel" },
        { value: "kavenegar", label: "Kavenegar" },
        { value: "magfa", label: "Magfa" }
    ]
}
```

## 3. قابلیت: Dependent Select (استان → شهر)


```javascript
{
    name: 'province_id',
    type: 'select',
    label: 'استان',
    options: provinces
},
{
    name: 'city_id',
    type: 'select',
    label: 'شهر',
    dependsOn: 'province_id',
    ajax: {
        url: '/api/cities?province={value}',
        valueKey: 'id',
        labelKey: 'name'
    }
}
```

## 4. چطور ۲ فیلد را در یک ردیف قرار دهیم؟

Tailwind خیلی راحت:
در فیلد تعریف:

```javascript
 {
	   type: 'group',
       group: [
          { name: 'user', label: 'نام کاربری', type: 'text' },
          { name: 'password', label: 'رمز عبور', type: 'password' }
        ]
 },
```

## یک مثال کامل 

```javascript
const providerFormConfig = {
    id: 'providerForm',
    endpoint: 'sms.providers.store',
    method: 'POST',
    title: 'افزودن ارائه‌دهنده پیامک',
    fields: [
        {
            name: 'name',
            label: 'نام ارائه‌دهنده',
            type: 'text',
            required: true,
            value:'',
            helpert:'نام اپراتور را وارد نمایید'
        },
        {
            name: 'status',
            label: 'وضعیت',
            type: 'select',
            required: true,
            options: [
                { value: 'active', label: 'فعال' },
                { value: 'inactive', label: 'غیرفعال' },
            ]
        },
        {
	        type: 'group',
            group: [
                { name: 'user', label: 'نام کاربری', type: 'text' },
                { name: 'password', label: 'رمز عبور', type: 'password' }
            ]
        },
        {
	        type: 'group',
            group: [
                {
                    name: 'country_id',
                    label: 'کشور',
                    type: 'select',
                    ajax: {
                        url: '/api/countries',
                        valueKey: 'id',
                        labelKey: 'name'
                    },
                    searchable: true,
                },
                {
                    name: 'city_id',
                    label: 'شهر',
                    type: 'select',
                    ajax: {
                        // {value} به صورت داینامیک با مقدار country_id جایگزین می‌شود
                        url: '/api/countries/{value}/cities',
                        valueKey: 'id',
                        labelKey: 'name'
                    },
                    dependsOn: 'country_id',
                    searchable: true,
                },
            ]
        },
    ]
};

```

## یک نمونه از فرم های ایجاد و ویرایش با امکان حذف برخی آیتم ها مانند رمز عبور

```js
// فرم برای ایجاد جدید
export const createForm = {
    endpoint: 'manager.users.store',
    updateEndpoint: 'manager.users.update',
    deleteEndpoint: 'manager.users.destroy',
    title: moduleActions.create || publicLang.create,
    fields: [
        { name: 'name', label: publicLang.name, type: 'text', required: true },
        { name: 'email', label: publicLang.email, type: 'email', required: true },
        { name: 'password', label: publicLang.password, type: 'password', required: true },  // ✅ فقط در ایجاد
        { name: 'national_code', label: publicLang.national_code, type: 'tel', required: true },
        {
            name: 'roles',
            label: moduleFields.roles || 'نقش‌ها',
            type: 'select',
            multiple: true,
            optionEndpoint: 'manager.roles.list',
            optionLabel: 'name',
            optionValue: 'name',
            role: ['SuperAdmin']
        },
        {
            name: 'is_active',
            label: publicLang.status,
            type: 'select',
            options: [
                { value: 1, label: 'فعال' },
                { value: 0, label: 'غیرفعال' }
            ],
            required: true,
        },
    ],
    buttons: { submit: publicLang.save, cancel: publicLang.cancel }
};

// فرم برای ویرایش (بدون فیلد password)
export const editForm = {
    ...createForm,  // Spread operator برای کپی کردن
    title: moduleActions.edit || publicLang.edit,
    fields: createForm.fields.filter(f => f.name !== 'password')  // ✅ حذف فیلد password
};
```

## نمونه دیگری از فرم های ایجاد و ویرایش با فیلدهای مشترک 

```js
const publicLang = AdminLang.getNamespace('common');
const moduleFields = AdminLang.getNamespace('modules.users.fields');
const moduleActions = AdminLang.getNamespace('modules.users.actions');

// فیلدهای مشترک بین هر دو فرم
const commonFields = [
    { name: 'name', label: publicLang.name, type: 'text', required: true },
    { name: 'email', label: publicLang.email, type: 'email', required: true },
    { name: 'national_code', label: publicLang.national_code, type: 'tel', pattern: '[0-9]{10}', maxlength: '10', minlength: '10', required: true },
    {
        name: 'roles',
        label: moduleFields.roles || 'نقش‌ها',
        type: 'select',
        multiple: true,
        optionEndpoint: 'manager.roles.list',
        optionLabel: 'name',
        optionValue: 'name',
        role: ['SuperAdmin']
    },
    {
        name: 'is_active',
        label: publicLang.status,
        type: 'select',
        options: [
            { value: 1, label: 'فعال' },
            { value: 0, label: 'غیرفعال' }
        ],
        required: true,
    },
];

// فرم ایجاد جدید (با رمز عبور)
export const createForm = {
    endpoint: 'manager.users.store',
    updateEndpoint: 'manager.users.update',
    deleteEndpoint: 'manager.users.destroy',
    title: moduleActions.create || publicLang.create,
    fields: [
        ...commonFields,
        { name: 'password', label: publicLang.password, type: 'password', required: true },
    ],
    buttons: { submit: publicLang.save, cancel: publicLang.cancel }
};

// فرم ویرایش (بدون رمز عبور)
export const editForm = {
    ...createForm,
    title: moduleActions.edit || publicLang.edit,
    fields: commonFields,  // فقط فیلدهای مشترک، بدون password
};
```

# نمونه دیگری از فرم با فیلدهای مشترک و آیتم های مجزا با تنظیمات مجزا

```js
const publicLang = AdminLang.getNamespace('common');
const moduleFields = AdminLang.getNamespace('modules.users.fields');
const moduleActions = AdminLang.getNamespace('modules.users.actions');

// ✅ فیلدهای مشترک بین هر دو فرم

const commonFields = [
    {
        name: 'name',
        label: publicLang.name,
        type: 'text',
        required: true,
    },
    {
        name: 'email',
        label: publicLang.email,
        type: 'email',
        required: true,
    },
    {
        name: 'national_code',
        label: publicLang.national_code,
        type: 'tel',
        pattern: '[0-9]{10}',
        maxlength: '10',
        minlength: '10',
        required: true,
    },
    {
        name: 'roles',
        label: moduleFields.roles || 'نقش‌ها',
        type: 'select',
        multiple: true,
        optionEndpoint: 'manage.roles.list',
        optionLabel: 'name',
        optionValue: 'name',
        role: ['SuperAdmin']
    },
    {
        name: 'is_active',
        label: publicLang.status,
        type: 'checkbox',
        options: [
            { value: 1, label: 'فعال' },
            { value: 0, label: 'غیرفعال' }
        ],
        required: true,
    },
];
// ✅ فرم ایجاد (با رمز عبور اجباری)
export const createForm = {
    endpoint: 'manage.users.store',
    updateEndpoint: 'manage.users.update',
    deleteEndpoint: 'manage.users.destroy',

    title: moduleActions.create || publicLang.create,
    fields: [
        ...commonFields,
        {
            name: 'password',
            label: publicLang.password,
            type: 'password',
            required: true,
            min: 6,
            placeholder: 'گذرواژه باید حداقل ۶ کاراکتر باشد',
        },
    ],
    buttons: {
        submit: publicLang.save,
        cancel: publicLang.cancel,
    }
};

// ✅ فرم ویرایش (با رمز عبور اختیاری و placeholder مجزا)
export const editForm = {
    title: moduleActions.edit || publicLang.edit,
    fields: [
        ...commonFields,
        {
            name: 'password',
            label: 'رمز عبور جدید',        // ✅ عنوان مجزا
            type: 'password',
            placeholder: 'در صورت تمایل تغییر دهید',  // ✅ placeholder مجزا
            min: 6,
            value:'',
            placeholder: 'در صورت تمایل رمز عبور جدید وارد کنید، در غیر این صورت خالی بگذارید',
        },
    ],
    buttons: {
        submit: publicLang.save,
        cancel: publicLang.cancel,
    }
};
```