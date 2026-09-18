# Phase V1.1 — UI & Presentation Layer

هدف این فاز توسعه‌ی لایه‌ی نمایش (Presentation) پروژه با استفاده از Blade و Tailwind CSS است. این لایه درخواست‌های کاربر را از طریق کنترلرها و Form Requestها اعتبارسنجی کرده و پس از تبدیل به DTOهای استاندارد، تحویل سرویس‌های دامین می‌دهد.

## User Review Required

> [!IMPORTANT]
> **پکیج Laramina**
> پکیج `hadii/laramina` در پروژه نصب است. طبق دستورالعمل این پکیج:
> **آیا تمایل دارید ساخت جدول و مدیریت این بخش بر اساس مستندات INSTALL.md و با پکیج Laramina انجام شود یا بدون در نظر گرفتن پکیج؟**
> (اگر از لارامینا استفاده کنیم، بخش لیست‌ها و فرم‌های ساده با جاوااسکریپت و API آن مدیریت می‌شود. اگر بدون لارامینا پیش برویم، تمام صفحات و فرم‌ها با ساختار خالص Blade و Tailwind و به صورت کاملاً سفارشی طراحی می‌شوند). لطفاً تصمیم خود را اعلام کنید.

## Open Questions

> [!WARNING]
> ۱. صفحه لاگین (Auth): آیا نیاز به پیاده‌سازی کامل صفحه لاگین اختصاصی با کد ملی (`username`) و پسورد از صفر داریم یا از پکیج خاصی مثل Breeze استفاده کنیم؟ (در طرح فعلی ساخت کنترلر و صفحه اختصاصی از صفر پیشنهاد شده است).
> ۲. آیا طراحی صفحات با فرض عدم استفاده از لارامینا (Pure Blade) انجام شود تا امکان شخصی‌سازی بالا برای جزئیات تسک‌ها، نمایش تایمر SLA و آپلود فایل با طراحی اختصاصی وجود داشته باشد؟

## Proposed Changes

### 1. Controllers & Requests
- **`Http/Controllers/AuthController.php`**: مدیریت ورود و خروج کاربران با استفاده از فیلد `username` (کد ملی).
- **`Http/Controllers/TaskController.php`**: عملیات CRUD تسک‌ها و نمایش صفحه کارتابل.
- **`Http/Controllers/TaskAssignmentController.php`**: مدیریت فرم تخصیص تسک به پیمانکار.
- **`Http/Controllers/ApprovalController.php`**: مدیریت فرم تاییدات ناظر/مدیر.
- **`Http/Controllers/DocumentController.php`**: مدیریت آپلود فایل.
- **`Http/Requests/*`**: ساخت Form Requestهای متناظر برای Validation و تبدیل ورودی‌ها به DTO (مانند `CreateTaskData` و `AssignTaskData`).

### 2. Routes & Permissions
در فایل `routes/web.php` سه گروه اصلی تعریف می‌شود:
1. **عمومی/احراز هویت**: `login`، `logout`، داشبورد اصلی.
2. **کارتابل پیمانکار**: دسترسی محدود به تسک‌های منتسب شده (محافظت شده با فیلتر `TaskScopeService`).
3. **دسترسی ناظر/مدیر**: امکان تخصیص، بررسی و تایید نهایی (محافظت شده با Spatie Permissions مانند `approve tasks`).

### 3. Views (Blade & Tailwind CSS)
- **`layouts/app.blade.php`**: ساختار اصلی صفحه با رعایت کامل `dir="rtl"`، استفاده از کلاس‌های خالص Tailwind CSS و ساخت Sidebar واکنش‌گرا بر اساس نقش و دسترسی کاربر.
- **`auth/login.blade.php`**: صفحه ورود.
- **`tasks/index.blade.php`**: لیست تسک‌ها به شکل جدولی با ظاهر مدرن.
- **`tasks/show.blade.php`**: صفحه جزئیات تسک (نمایش تایمرهای SLA، بخش پیوست‌ها، تاریخچه تاییدات و Audit).
- **`tasks/form.blade.php`**: فرم‌های ساخت و ویرایش.
- **`dashboard/supervisor.blade.php`**: نمای اختصاصی ناظر برای دیدن تسک‌های در انتظار تایید.

### 4. Tests
- اضافه کردن گروه جدیدی از تست‌ها در مسیر `tests/Feature/Web/`.
- **`WebAuthControllerTest`**: بررسی لاگین.
- **`WebTaskControllerTest`**: بررسی خطاهای `403 Forbidden` برای جلوگیری از دسترسی پیمانکار به تسک دیگران و بررسی ثبت صحیح تسک‌ها.

## Verification Plan

- اجرای `php artisan test --filter Web` پس از پیاده‌سازی هر کنترلر.
- بررسی دستی رندر صحیح `RTL` و دیزاین Tailwind در مرورگر.
- اطمینان از صحت تبدیل Request به DTOها و فراخوانی بی‌نقص `TaskService` و `ApprovalService`.
