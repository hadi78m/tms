# Laramina – Developer Guide

# راهنمای توسعه ماژول‌ها در Laramina
## 1. معرفی

###### این پروژه یک Laramina ماژولار برای Laravel است که با JavaScript و Tailwind ساخته شده و امکان ایجاد سریع پنل‌های مدیریتی را فراهم می‌کند.

### ویژگی‌های اصلی:

-    معماری Module Based
-    سیستم Plugin
-    موتورهای CRUD / Table / Form
-    سیستم Permission
-    سیستم Alert
-    Generator برای ساخت سریع ماژول

## 2. ساختار پروژه

```text
laramina/
```
## پلتفرم از چند لایه اصلی تشکیل شده است.
## 3. Config

```text
config/
   laramina.php
```

## تنظیمات کلی پلتفرم در این فایل قرار دارد.

نمونه تنظیمات:

```php
return [

    'route_prefix' => 'admin',

    'middleware' => [
        'web',
        'auth'
    ],

    'pagination' => 20,

];
```

### موارد قابل تنظیم:

-    prefix مسیرهای admin
-    middleware
-    تعداد رکورد table
-    تنظیمات alert
-    تنظیمات module

## 4. Backend (Laravel)
### مسیر

```text
app/Laramina
```
### این بخش منطق سمت سرور را مدیریت می‌کند.
### Controllers

```text
app/Laramina/Controllers/
   ModuleController.php
```
### وظیفه:

-    مدیریت درخواست‌های ماژول
-    ارسال داده برای table
-    ذخیره فرم‌ها

### Services

```text
app/Laramina/Services/
   ModuleService.php
```
### منطق بیزینس در اینجا قرار می‌گیرد.

### مثال:

-    CRUD
-    فیلتر داده‌ها
-    ارتباط با مدل‌ها

### Support

```text
app/Laramina/Support/
   ModuleRegistry.php
```
### وظیفه:

-    ثبت ماژول‌ها
-    مدیریت ماژول‌های فعال

### Contracts

```text
app/Laramina/Contracts/
   AdminModule.php
```
**این interface مشخص می‌کند هر ماژول باید چه متدهایی داشته باشد.**

مثال:

```text
getName()
getRoute()
getPermissions()
```

### Console

```text
app/Console/Commands
```
**دستورات artisan در اینجا قرار دارند.**

```text
MakeAdminUI.php
```
**دستور ساخت ماژول:**

```text
php artisan admin:make-ui
```

### Providers

```text
app/Providers/LaraminaServiceProvider.php
```

**وظیفه**:

    ثبت command ها
    پلتفرم bootstrap 

## 5. Views


```text
resources/views
```

**قالب‌های blade در اینجا قرار دارند.**
### Layout


```text
layouts/app.blade.php
```

**قالب layout اصلی پنل مدیریت.**
### Module Views

مثال:

```text
sms/index.blade.php
```

که در آن جدول و فرم‌ها render می‌شوند.
## 6. JavaScript Platform


```text
js/laramina
```

**مهم‌ترین بخش سیستم UI.**
### Adapters


```text
adapters/
```

**این لایه ارتباط با کتابخانه‌ها را مدیریت می‌کند.**

**مثال:**
```text
ajax-adapter.js
alert-adapter.js
datatable-adapter.js
policy-adapter.js
```

**مزیت:**

**اگر در آینده library عوض شود فقط adapter تغییر می‌کند.**
### Bootstrap

```text
bootstrap/laramina.js
```

**فایل اصلی شروع پلتفرم.**

وظیفه:

    load module ها
    register plugin ها
    initialize system

Components

                                                                    text
components/

کامپوننت‌های قابل استفاده مجدد.

مثال:

                                                                    text
reusable-form.js
reusable-modal.js

Core

                                                                    text
core/

هسته اصلی پلتفرم.

فایل‌ها:

                                                                    text
event-bus.js
module-loader.js
module-registry.js
plugin-manager.js
permission-manager.js
route-manager.js
state-manager.js

وظایف:

EventBus

ارتباط بین ماژول‌ها

ModuleLoader

لود ماژول‌ها

PluginManager

مدیریت plugin ها

PermissionManager

کنترل دسترسی

RouteManager

مدیریت مسیرها

StateManager

مدیریت state
Engines

                                                                    text
engines/

موتورهای اصلی UI.

                                                                    text
crud-engine.js
table-engine.js
form-engine.js
action-engine.js
bulk-engine.js

وظیفه:

اجرای عملیات اصلی سیستم.

مثال:

                                                                    text
TableEngine.render()
FormEngine.submit()

UI

                                                                    text
ui/

رندر UI.

                                                                    text
table-renderer.js
form-renderer.js
action-renderer.js
modal.js

Plugins

دو نوع plugin وجود دارد.
Column Plugins

                                                                    text
plugins/columns

برای نوع ستون‌های جدول.

مثال:

                                                                    text
badge
boolean-icon
copy
image
toggle-status
date-format

نمونه استفاده:

                                                                    text
{
   key: 'status',
   type: 'badge'
}

Action Plugins

                                                                    text
plugins/actions

برای دکمه‌های عملیات.

                                                                    text
view
edit
delete

مثال:

                                                                    text
actions: [
   'view',
   'edit',
   'delete'
]

7. Generator

                                                                    text
laravel-integration/stubs

قالب فایل‌های ماژول.

                                                                    text
module.stub
table.stub
form.stub
actions.stub
index.stub

هنگام اجرای command از این فایل‌ها استفاده می‌شود.
8. ساخت یک ماژول جدید

با دستور:

                                                                    text
php artisan admin:make-ui

مثال:

                                                                    text
php artisan admin:make-ui Users

ساختار ایجاد شده:

                                                                    text
resources/js/modules/users/

   module.js
   table.js
   form.js
   actions.js

9. تعریف Table

نمونه:

                                                                    text
export default {

 endpoint:'/admin/users',

 columns:[

   {
     key:'id',
     label:'ID'
   },

   {
     key:'name',
     label:'نام'
   },

   {
     key:'status',
     type:'badge'
   },

   {
     type:'actions',
     actions:['view','edit','delete']
   }

 ]

}

10. تعریف Form

                                                                    text
export default {

 fields:[

  {
    name:'name',
    label:'نام',
    type:'text'
  },

  {
    name:'email',
    type:'email'
  }

 ]

}

11. تعریف Actions

                                                                    text
export default {

 delete:{
   method:'DELETE',
   endpoint:'/admin/users/{id}'
 }

}

12. Alert System

Alert از طریق adapter اجرا می‌شود.

مثال:

                                                                    text
Alert.success('عملیات موفق بود')
Alert.error('خطا رخ داد')

فایل:

                                                                    text
alert-adapter.js

می‌تواند به:

    SweetAlert
    Toast
    Custom UI

متصل شود.
13. Permission System

در action یا column می‌توان role مشخص کرد.

مثال:

                                                                    text
{
   name:'delete',
   roles:['superAdmin']
}

PermissionManager بررسی می‌کند:

                                                                    text
user.roles

14. اضافه کردن Plugin جدید

مثال:

                                                                    text
plugins/columns/currency.js

                                                                    text
export default {

 render(column,row){

   return new Intl.NumberFormat().format(row[column.key])

 }

}

سپس register:

                                                                    text
PluginManager.register('column','currency',plugin)

15. معماری کلی سیستم

                                                                    text
Module
   ↓
Config
   ↓
Engine
   ↓
Renderer
   ↓
Plugin
   ↓
UI

16. مزایای این معماری

    ماژولار
    قابل توسعه
    وابسته نبودن به library خاص
    قابل تبدیل به package
    امکان ساخت admin panel بزرگ



# ایجاد Traits لاراول برای pagination

## 1️⃣ ساخت Trait
### مسیر

```text
app/Laramina/Traits/AdminTableTrait.php
```

### Code

```php
<?php

namespace App\Laramina\Traits;

use Illuminate\Http\Request;

trait AdminTableTrait
{

    public static function adminTable(Request $request, array $config = [])
    {

        $model = new static;

        $query = $model->newQuery();

        $perPage = $request->get('per_page', 10);

        $searchColumns = $config['search'] ?? [];

        $filters = $config['filters'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search') && count($searchColumns)) {

            $search = $request->search;

            $query->where(function ($q) use ($searchColumns, $search) {

                foreach ($searchColumns as $col) {

                    $q->orWhere($col, 'like', "%{$search}%");

                }

            });

        }

        /*
        |--------------------------------------------------------------------------
        | FILTERS
        |--------------------------------------------------------------------------
        */

        foreach ($filters as $filter) {

            if ($request->filled($filter)) {

                $query->where($filter, $request->$filter);

            }

        }

        /*
        |--------------------------------------------------------------------------
        | SORT
        |--------------------------------------------------------------------------
        */

        if ($request->filled('sort')) {

            $direction = $request->get('direction','asc');

            $query->orderBy($request->sort, $direction);

        } else {

            $query->orderByDesc('id');

        }

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */

        $rows = $query->paginate($perPage);

        /*
        |--------------------------------------------------------------------------
        | TRANSFORM
        |--------------------------------------------------------------------------
        */

        if (method_exists(static::class,'adminTransform')) {

            $data = collect($rows->items())
                ->map(fn($row) => static::adminTransform($row));

        } else {

            $data = $rows->items();

        }

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'success' => true,

            'data' => $data,

            'meta' => [

                'current_page' => $rows->currentPage(),

                'per_page' => $rows->perPage(),

                'total' => $rows->total(),

            ]

        ]);

    }

}


```
## 2️⃣ استفاده در Model  مثلا provider

### Code

```php

use App\Laramina\Traits\AdminTableTrait;

class Provider extends Model
{
    use AdminTableTrait;

    public static function adminTransform($cred)
    {
        return [
            'id' => $cred->id,
            'name' => $cred->name,
            'is_active' => $cred->is_active,
            'is_default' => $cred->is_default,
            'rate_unit' => $cred->rate_unit,
            'url' => $cred->url,
            'send_rate' => $cred->send_rate,

            'created_at' => "<div dir='ltr' class='text-center'>" .
                (verta($cred->created_at)->format('Y/m/d') ?? '') .
                "</div>",
        ];
    }
}

```

## 3️⃣ کدهای فوق‌العاده تمیز

### کنترلر
```php
use App\Http\Controllers\Controller;
use App\Traits\AdminTableTrait;
use App\Models\SmsProvider;
use Illuminate\Http\Request;

class SmsProviderController extends Controller
{
    use AdminTableTrait;

public function json(Request $request)
{
    return Provider::adminTable($request, [
        'search' => ['name','url'],
        'filters' => ['is_active','is_default'],
    ]);
}
}
```

### Blade = > table.js

```php
perpage: 10
```

## 
پایان