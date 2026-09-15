

# یک نمونه از table.js  با توضیحات مرتبط 

## ایمپورت فایل هایی که استفاده میکنیم مانند فرم ها

```js
// استفاده از 2 فرم مجزا برای ایجاد و ویرایش
import { createForm, editForm } from './forms/create-form.js'
import { createPhone } from './forms/providerPhoneForm.js'
import { providerActions } from './actions.js'
```

## زبان های استفاده شده در جدول (استفاده اختیاری) یا تغییر lable بر اساس نیاز

```js
// زبان عمومی
const publicLang = AdminLang.getNamespace('common');
// فیلد های اصلی جدول مرتبط با credential
const moduleFields = AdminLang.getNamespace('modules.credentials.fields');
// فیلدهای مرتبط با اکشن مرتبط با credential
const moduleActions = AdminLang.getNamespace('modules.credentials.actions');
```

## قسمت نمایش آیتم های اصلی جدول

```js
export default {
}
```

## اطلاعات مرتبط به جدول در export default

```js
export default {
// مسیر دریافت اطلاعات جدول که روت استفاده شده است
    endpoint: 'sms.credentials.json',
// قابلیت جستجو در جدول 
    search: true,
// نام نمایشی در قسمت بالای جدول
    headerTitle: moduleFields.header_title || (publicLang.manage + ' ' + (moduleFields.title || 'sms.credentials')),
// نام نمایشی برای افزودن ایتم جدید در قسمت هدر
    addButtonLabel: moduleActions.create || (publicLang.create + ' ' + (moduleActions.item || publicLang.item)),
// نمایش دکمه افزودن
    displayButton: true,
// اکشن استفاده شده برای این جدول ها که در قسمت بالای صفحه ایمپورت کرده ایم
    actions: credentialActions,
// رنگ نمایشی مودال ها که شامل light  و dark است
    modalTheme: 'light',
// رنگ گرادیانت هدر جدول (قابل سفارشی‌سازی در هر ماژول)
// پیش‌فرض: bg-gradient-to-r from-blue-600 to-blue-800 text-white
    headerGradient: "bg-gradient-to-r from-blue-600 to-blue-800 text-white",
// کلاس CSS هدر ستون‌های جدول
    theadClass: "bg-blue-700 text-center text-white py-2",
// کلاس CSS بدنه جدول
    tbodyClass: "divide-y text-center text-gray-800",
// تعداد ردیف های نمایش داده شده در هر صفحه یک جدول
    perPage: 10,
    }
```

### مثال : headerTitle و addButtonLable  و محل نمایش دکمه displayButton
![[Pasted image 20260423112031.png]]

## اطلاعات مرتبط با مودال ها در export default

```js
    modals: {
// مودال ایجاد که به صورت پیش فرض موجود است
        create: {
// نام نمایش داده شده در  هدر مودال
            title: 'افزودن پرووایدر',
// سایز مودال
            width: '500px',
// فرم استفاده شده برای مودال که در اول صفحه ایمپورت شده و در این مثال بصورت زیر است 
// import { createForm } from './forms/create-form.js'
            form: createForm
        },
// فرم ویرایش که به صورت پیش فرض موجود است 
        edit: {            
            title: 'ویرایش پرووایدر',
            width: '500px',
// همان فرم که برای اضافه کردن استفاده می‌کنیم یا می توانیم فرم جدیدی برای ویرایش ایجاد کنیم 
            form: editForm   
        },
// مودال های اختصاصی براساس نیاز کاربر
        addPhone: {
            title: 'افزودن شماره',
            width: '400px',
// نام فرمی که در مسیر forms/ ایجاد کرده و در قسمت بالا ایمپورت کرده ایم که به صورت زیر بوده است
// import { createPhone } from './forms/providerPhoneForm.js'
            form: createPhone
        }

    },
```


## اطلاعات مرتبط با فیلتر جدول در export-default 

```js
    filters: [
        {
// کلیدی که قرار است بر اساس آن فیلتر انجام شود
            key: 'is_active',
// نام نمایشی
            label: publicLang.is_active,
// نوع فیلتر
            type: 'select',
// چون گزینه بالا انتخابی است موارد مرتبط را در زیر جهت انخاب می نویسیم
            options: {
                1: publicLang.active,
                0: publicLang.inactive,
            }
        },
         {
            key: 'is_default',
            label: publicLang.is_default,
            type: 'select',
            options:
            {
                1: publicLang.is_default,
                0: publicLang.indefault,
            }
        },
    ],
```

## موارد مرتبط با خود جدول که در columns داخل export default قرار دارد

```js
    columns: [
// فقط رول ادمین می تواند این آیتم را ببیند    
        { key: 'id', label: 'شناسه', sortable: true,role:['admin'] },
// قابلیت sort را اضافه کرده ایم
        { key: 'name', label: publicLang.name, sortable: true },
// نمونه ساده استفاده از اکشن اختصاصی مورد نظر که در فایل actions.js ایجاد کرده ایم
        { key: 'static_token_exists', label: 'توکن', type: 'actions', actions: ['copyStaticToken'] },

        {
            key: 'static_token_exists',
            label: 'توکن',
// در صورت استفاده از اکشن ها نوع type  را برابر با actions قرار میدهیم
            type: 'actions',
// اعمال شرط که در صورت صحیح بودن نمایش داده شود            
            visible: row => row.has_token === 1,
// استفاده از اکشن اختصاصی مورد نظر که در فایل actions.js ایجاد کرده ایم           
            actions: ['copyStaticToken']
        },
// نمایش به صورت بولین دارای آیکن         
          {
            key: 'is_active',
            label: 'فعال',
// جهت نمایش آیکن های فعال و غیر فعال حتما نوع را به صورت زیر قرار دهید
            type: 'boolean-icon'
        },

        {
            key: 'is_active',
            label: publicLang.status || moduleFields.status,
// نوع اکشن برای موارد که فعال و غیر فعال و شبیه به آن است به صورت زیر action-toggle میگذاریم
            type: 'action-toggle',
// روت و مسیر انجام کار مرتبط با تغییرات مد نظر
            endpoint: 'sms.credentials.toggle-status',
// متن نمایش داده شده در مودال سوالات انجام کار همانند آیا راضی به تغییر هستید؟
            confirmTitle: publicLang.confirm_toggle_status,
// نمایش های شرطی 
            map: {
                true: { label: publicLang.active, color: 'green' },
                false: { label: publicLang.inactive, color: 'gray' }
		         },
// میتوانیم از آیکن دلخواه استفاده کنیم 
// الویت با آیکون است و در صورت نبودن آیکون مپ اعمال میشود            
            icons: {
			    true: { html: '<i class="fa-solid fa-toggle-on  text-lg"></i>', color: 'green' },
			    false: { html: '<i class="fa-solid fa-toggle-off  text-lg"></i>', color: 'red' }
					}
			  },
			   {
            key: 'rate_unit',
            label: 'واحد نرخ',
// ایجاد نمایش های شرطی
            type: 'badge',
            map: {
                per_second: { label: 'در ثانیه', color: 'gray' },
                per_minuts: { label: 'در دقیقه', color:'blue' },
                per_hour: { label: 'در ساعت',color:'purple' },
                unlimited: { label: 'نامحدود', color: 'green' },
            }
        },
        

// در این قسمت از نمایش ساده استفاده کرده ایم ولی در ورودی بصورت html نمابش داده ایم همانند 
/**
$generateStatickToken = '
<button id="createStaticToken" 
data-id="' . $cred->id . '" 
onclick="createStaticToken(' . $cred->id . ')" 
class="px-2 py-1 bg-purple-600 text-white rounded text-sm ml-1" 
title="ایجاد توکن استاتیک">
<i class="fas fa-key"></i></button>';
 */
 // نکته در این صورت بایست اسکریپت متناظر با اجرای onclick مدنظر را ایجاد نیم
 
        { key: 'generateStatickToken', label: 'ایجاد توکن' },
// دکمه های عملیات که اکشن های حذف و ویرایش به صورت پیش فرض تعریف شده وموارد اضافه تر بایست در فایل actions.js ساخته شوند همانند مورد آخر زیر
        {
            label: publicLang.actions || 'عملیات',
            type: 'actions',
            actions: ['edit', 'delete', 'regenerateStaticToken']
        }
    ],
```


