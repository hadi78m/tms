# مستند استفاده و آشنایی با TableEngine

فایل TableEngine یک ماژول ساده و قابل توسعه برای مدیریت جدول‌های سمت ادمین است که کارهای زیر را برای شما انجام می‌دهد:

- رندر HTML جدول با ستون‌ها، فیلترها، جستجو، pagination، per-page
- لود داده از API با پارامترهای page, per_page, search, filters
- مدیریت اکشن‌های سطری (edit, delete, ...)
- انتخاب گروهی (bulk selection) و bulk actions
- باز و بسته کردن مودال‌ها (ایجاد/ویرایش) با استفاده از FormEngine
- به‌روزرسانی، حذف و افزودن ردیف‌ها بدون رفرش کامل جدول

---

## راه‌اندازی اولیه table.js
```js
import TableEngine from 'path/to/table-engine.js'

const container = document.getElementById('providers-table')

TableEngine.render({
endpoint: '/admin/providers',    // آدرس API
tableId: 'providers-table',      // (اختیاری) برای تمایز چند جدول
headerTitle: 'مدیریت تامین‌کننده‌ها',
addButtonLabel: 'افزودن تامین‌کننده',
displayButton: true,             // نمایش دکمه افزودن
perPage: 20,                     // تعداد در هر صفحه (اختیاری)

// تنظیمات ظاهری (اختیاری - مقادیر پیش‌فرض در صورت عدم تنظیم اعمال می‌شود)
headerGradient: 'bg-gradient-to-r from-blue-600 to-blue-800 text-white',  // کلاس گرادیانت هدر جدول
theadClass: 'bg-blue-700 text-center text-white py-2',                   // کلاس هدر ستون‌ها
tbodyClass: 'divide-y text-center text-gray-800',                         // کلاس بدنه جدول

// تعریف ستون‌ها
columns: [
{ key: 'id', label: 'شناسه', type: 'text' },
{ key: 'name', label: 'نام', type: 'text' },
{ key: 'is_active', label: 'وضعیت', type: 'badge' },
{
type: 'actions',
label: 'عملیات',
actions: ['edit', 'delete'] // نام اکشن‌هایی که در این ستون نمایش داده می‌شوند
}
],

// فیلترها
filters: [
{
key: 'is_active',
type: 'select',
label: 'وضعیت',
// می‌توانید object یا array بدهید:
options: [
{ value: 1, label: 'فعال' },
{ value: 0, label: 'غیر فعال' }
]
}
],

// اکشن‌های سطری
actions: {
edit: {
icon: 'fas fa-edit',
color: 'text-blue-600 hover:text-blue-800',
tooltip: 'ویرایش',
handler(row, table, event) {
table.openModal('edit', row)
}
},
delete: {
icon: 'fas fa-trash',
color: 'text-red-600 hover:text-red-800',
tooltip: 'حذف',
confirm: 'آیا از حذف این مورد مطمئن هستید؟',
async handler(row, table) {
await fetch(`/admin/providers/${row.id}`, { method: 'DELETE' })
// بعد از حذف از سمت سرور، ردیفرا از جدول حذف کن
table.removeRow(row.id)
}
}
},

// اکشن‌های گروهی (bulk)
bulkActions: [
{
name: 'activate',
label: 'فعال‌سازی گروهی',
async handler(selectedIds, table) {
await fetch('/admin/providers/bulk/activate', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ ids: selectedIds })
})

// رفرش جدول بعد از انجام عملیات
table.loadData()
}
}
],

// تنظیمات مودال‌ها
modals: {
create: {
title: 'افزودن تامین‌کننده جدید',
width: '600px',
form: {
// تنظیمات فرم برای FormEngine
// ...
}
},
edit: {
title: 'ویرایش تامین‌کننده',
width: '600px',
form: {
// تنظیمات فرم و فیلدها
}
}
}

}, container)
```
## رویدادهای عمومی (Global Events)

**فایل TableEngine برای کنترل از بیرون چند event عمومی روی document گوش می‌دهد:**
### 1. رفرش جدول

**برای رفرش کامل داده‌های جدول ( دوباره زدن loadData ):**

**1. بارگذاری مجدد کل جدول (Refresh کل داده‌ها)**

```js
// روش 1: استفاده از event (ترجیحی - غیرمستقیم)
document.dispatchEvent(new CustomEvent('admin:table:reload', {
    detail: {
        tableId: 'users-table' // اختیاری: اگر مشخص شود فقط همان جدول ریفرش می‌شود
    }
}))

// روش 2: استفاده مستقیم از instance (اگر دسترسی دارید)
const tableInstance = document.querySelector('#table-container').__tableInstance
if (tableInstance) {
    tableInstance.loadData()
}

```
 ### 2. بارگذاری مجدد یک ردیف خاص (Reload Row)

```javascript
// روش 1: استفاده از event (ترجیحی)
document.dispatchEvent(new CustomEvent('admin:table:reload-row', {
    detail: {
        tableId: 'users-table', // اختیاری
        rowId: 123 // شناسه ردیف مورد نظر
    }
}))

// روش 2: استفاده مستقیم
const tableInstance = document.querySelector('#table-container').__tableInstance
if (tableInstance) {
    tableInstance.reloadRow(123) // 123 = شناسه ردیف
}
```

**اگر tableId نفرستید، در پیاده‌سازی فعلی فقط روی این instance چک می‌شود که tableId اش برابر باشد؛ پیشنهاد می‌شود همیشه tableId بفرستید.**
### 3. به‌روزرسانی یک ردیف (updateRow)

**وقتی از API یک ردیف جدید یا به‌روزرسانی‌شده دریافت می‌کنید و نمی‌خواهید کل جدول را رفرش کنید:**

```js
document.dispatchEvent(new CustomEvent('admin:table:update-row', {
detail: {
row: updatedRowObject // باید شامل id باشد
}
}))
```
**در فایل های اسکریپت**

```js
// ارسال ردیف جدید از سرور
document.dispatchEvent(new CustomEvent('admin:table:update-row', {
    detail: {
        row: {
            id: 123,
            name: 'محمد جدید',
            email: 'new@example.com',
            status: 'active'
        }
    }
}))

```

**اگر id در آرایه‌ی currentRows یافت نشود، addRow صدا زده می‌شود و ردیف به جدول اضافه می‌گردد.**

### 4. حذف یک ردیف (removeRow)


```js
document.dispatchEvent(new CustomEvent('admin:table:remove-row', { detail: { id: someId } }))
```

**این متد:**
- **ردیف با `id` مورد نظر را از `currentRows` حذف می‌کند**
- **و DOM همان ردیف را از `<tbody>` حذف می‌کند (یا کل جدول را دوباره رندر می‌کند)**
- **در صورت نیاز، انتخاب‌ها (selected) را بروزرسانی می‌کند.**
### 5. افزودن یک ردیف جدید (addRow)

```js
document.dispatchEvent(new CustomEvent('admin:table:add-row', { detail: { row: newRow } }))
```

**در فایل اسکریپت**
```js
document.dispatchEvent(new CustomEvent('admin:table:add-row', {
    detail: {
        row: {
            id: 456,
            name: 'کاربر جدید',
            email: 'newuser@example.com',
            status: 'pending'
        }
    }
}))
```


**ردیف جدید در ابتدای جدول اضافه می‌شود (`unshift`).**

## رفرش جدول از داخل ماژول

**اگر به اینستنس جدول دسترسی دارید (مثلاً از روی `container.__tableInstance`):**


```js
const table = container.__tableInstance  // رفرش کامل جدول (با حفظ page و filters جاری) 
table.loadData()
```
------


### مثال‌های کاربردی:
#### مثال 1: پس از ذخیره فرم در مودال

```javascript
// در success callback فرم
function onFormSubmitSuccess(response) {
    // بستن مودال
    modal.close()
    
    // اگر ویرایش بود، فقط همان ردیف را reload کن
    if (response.data.id && response.data.isUpdate) {
        document.dispatchEvent(new CustomEvent('admin:table:reload-row', {
            detail: { rowId: response.data.id }
        }))
    } 
    // اگر ایجاد جدید بود، کل جدول را refresh کن
    else {
        document.dispatchEvent(new CustomEvent('admin:table:reload'))
    }
    
    // نمایش پیام موفقیت
    AppAlert.show({
        type: 'success',
        text: response.message || 'عملیات با موفقیت انجام شد'
    })
}
```

#### مثال 2: در اکشن delete/restore

```javascript
// در اکشن حذف
async function deleteItem(row, tableInstance) {
    if (!confirm('آیا از حذف این آیتم اطمینان دارید؟')) return
    
    try {
        const response = await fetch(`/api/users/${row.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        
        if (response.ok) {
            // حذف ردیف از جدول
            document.dispatchEvent(new CustomEvent('admin:table:remove-row', {
                detail: { id: row.id }
            }))
            
            AppAlert.show({
                type: 'success',
                text: 'آیتم با موفقیت حذف شد'
            })
        }
    } catch (error) {
        console.error('Delete error:', error)
        AppAlert.show({
            type: 'error',
            text: 'خطا در حذف آیتم'
        })
    }
}

// در اکشن بازگردانی
async function restoreItem(row, tableInstance) {
    try {
        const response = await fetch(`/api/users/${row.id}/restore`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        
        if (response.ok) {
            // ریفرش ردیف
            document.dispatchEvent(new CustomEvent('admin:table:reload-row', {
                detail: { rowId: row.id }
            }))
            
            AppAlert.show({
                type: 'success',
                text: 'آیتم با موفقیت بازگردانی شد'
            })
        }
    } catch (error) {
        console.error('Restore error:', error)
        AppAlert.show({
            type: 'error',
            text: 'خطا در بازگردانی آیتم'
        })
    }
}
```

#### مثال 3: در bulk actions

```javascript
// در config جدول
bulkActions: [
    {
        name: 'delete',
        label: 'حذف انتخاب‌شده‌ها',
        roles: ['admin'],
        handler: async (selectedIds, tableInstance) => {
            if (!selectedIds.length) {
                AppAlert.show({ type: 'warning', text: 'لطفاً ابتدا آیتم‌ها را انتخاب کنید' })
                return
            }
            
            if (!confirm(`آیا از حذف ${selectedIds.length} آیتم اطمینان دارید؟`)) return
            
            try {
                const response = await fetch('/api/users/bulk-delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ ids: selectedIds })
                })
                
                if (response.ok) {
                    // ریفرش کل جدول
                    document.dispatchEvent(new CustomEvent('admin:table:reload'))
                    
                    AppAlert.show({
                        type: 'success',
                        text: `${selectedIds.length} آیتم با موفقیت حذف شد`
                    })
                }
            } catch (error) {
                console.error('Bulk delete error:', error)
                AppAlert.show({
                    type: 'error',
                    text: 'خطا در حذف گروهی آیتم‌ها'
                })
            }
        }
    },
    {
        name: 'activate',
        label: 'فعال‌سازی انتخاب‌شده‌ها',
        handler: async (selectedIds, tableInstance) => {
            // ... کد فعال‌سازی
            
            // بعد از موفقیت، هر ردیف را جداگانه reload کن
            selectedIds.forEach(id => {
                setTimeout(() => {
                    document.dispatchEvent(new CustomEvent('admin:table:reload-row', {
                        detail: { rowId: id }
                    }))
                }, 100) // تأخیر برای جلوگیری از overload
            })
        }
    }
]
```

#### مثال 4: استفاده با WebSocket یا Polling برای به‌روزرسانی زنده

```javascript
// در صورت استفاده از WebSocket
socket.on('userStatusChanged', (data) => {
    // فقط ردیف کاربر مورد نظر را reload کن
    document.dispatchEvent(new CustomEvent('admin:table:reload-row', {
        detail: { rowId: data.userId }
    }))
})

// در صورت استفاده از polling
setInterval(async () => {
    try {
        const response = await fetch('/api/users/updates')
        const updates = await response.json()
        
        updates.forEach(update => {
            document.dispatchEvent(new CustomEvent('admin:table:reload-row', {
                detail: { rowId: update.id }
            }))
        })
    } catch (error) {
        console.error('Polling error:', error)
    }
}, 30000) // هر 30 ثانیه

```
#### مثال 5: استفاده در اکشن‌های custom

```javascript
// در config.actions جدول
actions: {
    refresh: {
        icon: 'fas fa-sync-alt',
        tooltip: 'بارگذاری مجدد',
        color: 'text-blue-600 hover:text-blue-800',
        handler: (row, tableInstance) => {
            // reload این ردیف خاص
            document.dispatchEvent(new CustomEvent('admin:table:reload-row', {
                detail: { rowId: row.id }
            }))
            
            // یا اگر می‌خواهید کل جدول ریفرش شود:
            // document.dispatchEvent(new CustomEvent('admin:table:reload'))
        }
    },
    toggleStatus: {
        icon: 'fas fa-power-off',
        tooltip: 'تغییر وضعیت',
        handler: async (row, tableInstance) => {
            const newStatus = row.status === 'active' ? 'inactive' : 'active'
            
            try {
                const response = await fetch(`/api/users/${row.id}/status`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ status: newStatus })
                })
                
                if (response.ok) {
                    // فقط این ردیف را reload کن
                    document.dispatchEvent(new CustomEvent('admin:table:reload-row', {
                        detail: { rowId: row.id }
                    }))
                    
                    AppAlert.show({
                        type: 'success',
                        text: 'وضعیت با موفقیت تغییر کرد'
                    })
                }
            } catch (error) {
                console.error('Toggle status error:', error)
                AppAlert.show({
                    type: 'error',
                    text: 'خطا در تغییر وضعیت'
                })
            }
        }
    }
}
```

#### نکات مهم:

    - 1. استفاده از events روش ترجیحی است زیرا:
        - جدول خودش event را گوش می‌دهد
        - امکان مدیریت چند جدول به صورت همزمان
        - جداسازی منطق (decoupling)

    - 2. تفاوت بین reload vs reloadRow:
        - از reload برای به‌روزرسانی کل جدول استفاده کنید
        - از reloadRow برای به‌روزرسانی یک ردیف خاص

    بهینه‌سازی:
        - برای تغییرات کوچک از reloadRow استفاده کنید
        - از setTimeout برای تأخیر در reloadهای متعدد استفاده کنید
        - در صورت امکان از WebSocket برای به‌روزرسانی‌های real-time استفاده کنید

    مدیریت حالت loading:
        - متد reloadRow به صورت خودکار loading state را مدیریت می‌کند
        - برای reload کل جدول، می‌توانید loading state را خودتان اضافه کنید

-----




## جستجو و فیلترها

- مورد Input با `data-search`:
    
    - هر تغییر (`input`) مقدار `this.search` را آپدیت می‌کند
    - و `page` را روی 1 می‌گذارد
    - و `loadData()` را صدا می‌زند

- فیلترها با `data-filter="key"`:
    
    - روی `change` مقدار `this.filters[key]` را آپدیت می‌کند
    - و `page` را روی 1 می‌گذارد
    - و `loadData()` را صدا می‌زند

**نمونه HTML فیلتر:**

```html
<select data-filter="is_active">
  <option value="">همه</option>
  <option value="1">فعال</option>
  <option value="0">غیر فعال</option>
</select>
```

**(این select معمولاً توسط `renderFilters` خود TableEngine ساخته می‌شود)**

### نمونه فیلتر با table.js

```js
    filters: [
        {
            key: 'is_active',
            label: publicLang.is_active,
            type: 'select',
            options: {
                1: publicLang.active,
                0: publicLang.inactive,
            }
        },
    ],
```


## قابلیت pagination و per-page

**فایل TableEngine از این data-attribute‌ها استفاده می‌کند:**

- و `data-page`: روی هر دکمه/لینک شماره صفحه
- و `data-perpage`: روی `select` تعداد آیتم در هر صفحه

### مثال per-page:

```
<select data-perpage>
  <option value="10">10 تایی</option>
  <option value="20">20 تایی</option>
  <option value="50">50 تایی</option>
</select>
```

### نمونه per-page در table.js

```js
export default {
    perPage: 10
}
```
**در `bindEvents`:**

- وقتی `change` روی المانی با `data-perpage` اتفاق بیفتد:
    - مقدار `this.perPage` را تنظیم می‌کند
    - صفحه را به 1 برمی‌گرداند
    - و `loadData()` را صدا می‌زند

## اکشن‌های سطری (Row Actions)

### تعریف در table.js.columns

```js
{   type: 'actions',   label: 'عملیات',   actions: ['edit', 'delete'] 
// نام اکشن‌ها 
}
```

### تعریف هندلرها در actions.js

```js
actions: {   
edit: { 
icon: 'fas fa-edit', 
tooltip: 'ویرایش', 
handler(row, table, event) 
{ 
table.openModal('edit', row) 
}   
},   
delete: { 
icon: 'fas fa-trash', 
tooltip: 'حذف', 
confirm: 'از حذف مطمئن هستید؟', 
async handler(row, table) { 
await fetch(`/admin/providers/${row.id}`, { method: 'DELETE' }) 
table.removeRow(row.id) 
}   
} 
}
```

**در TableEngine:**

- **دکمه‌ای با `data-action="edit"` و `data-id="<row.id>"` می‌سازد**
- **در `bindTableActions` روی کلیک این دکمه‌ها گوش می‌دهد**
- **هندلر مناسب را پیدا و اجرا می‌کند.**

## امکان bulk actions و انتخاب گروهی

- **ستون چک‌باکس‌ها وقتی فعال می‌شود که `config.bulkActions` تعریف شده باشد.**
- چک‌باکس‌ها:
    - **و `data-select-all` برای انتخاب/لغو انتخاب همه**
    - **و `input[data-select]` روی هر ردیف**

**لیست انتخاب‌شده‌ها در `this.selected` نگهداری می‌شود.**

### تعریف bulkAction


```js
bulkActions: [{ 
   name: 'delete', 
   label: 'حذف گروهی', 
   async handler(selectedIds, table) { 
   await fetch('/admin/providers/bulk/delete', { 
   method: 'POST', 
   headers: { 'Content-Type': 'application/json' }, 
   body: JSON.stringify({ ids: selectedIds }) }) 
   table.loadData() 
   }
   } ]
```

**در UI:**

```html
<select data-bulk>
  <option value="">عملیات گروهی</option>
  <option value="delete">حذف گروهی</option>
</select>
```

**در TableEngine:**

- **روی `change` این select، اکشن را با `selectedIds` و `tableInstance` صدا می‌زند**
- **بعد از اجرا، `selected` را خالی و چک‌باکس‌ها را sync می‌کند.**

## کار با مودال‌ها

**در `config.modals` مودال‌ها را تعریف می‌کنید:**

```js
modals: {   
create: { 
title: 'افزودن', 
width: '600px', 
form: { 
// کانفیگ فرم برای FormEngine 
}   },   
edit: { title: 'ویرایش', width: '600px', form: { // ... 
}   } }
```

### باز کردن مودال

**از داخل TableEngine:**

```js
this.openModal('create') this.openModal('edit', rowData)
```

**از بیرون (اگر instance را دارید):**

```js
const table = container.__tableInstance table.openModal('create')
```

## متدهای مهم TableEngine

**خلاصه متدها برای استفاده در ماژول‌ها:**

- **متد `render(config, el)`**
        - **رندر اولیه جدول و لود داده‌ها**
- **متد `loadData()`**
    - **رفرش کامل داده‌ها از API با وضعیت فعلی (page, perPage, search, filters)**
- **متد `openModal(name, data?)`**
    - **باز کردن مودال (create/edit/…)**
- **متد `updateRow(row)`**
    - **به‌روزرسانی یک ردیف موجود (یا اضافه کردن اگر وجود نداشت)**
- **متد `removeRow(id)`**
    - **حذف یک ردیف از state و DOM**
- **متد `addRow(row)`**
    - **افزودن یک ردیف جدید در ابتدای جدول**
- **متد `updateSelected()`**
    - **به‌روزرسانی لیست `selected` بر اساس چک‌باکس‌های تیک خورده**
- **متد `refreshCheckboxes()`**
    - **امکان sync وضعیت select-all با `selected` و تعداد ردیف‌ها**

---

## نکات مهم

- **1. برای جلوگیری از `[object Object]` در فیلترها، `renderFilters` همیشه `label` را با `String(...)` به رشته تبدیل می‌کند.**
- **2. فایل TableEngine بر اساس `config.columns` و `PluginManager` قابل توسعه است؛ برای هر `column.type` می‌توان پلاگین اختصاصی تعریف کرد.**
-  **3. برای سناریوهای real-time، می‌توانید از WebSocket/Channel سمت سرور استفاده کنید و بر اساس پیام‌ها، رویدادهای `admin:table:update-row`، `...:remove-row` و `...:add-row` را dispatch کنید.**