# ACTION_TRACKER.md - گزارش تغییرات و ریفکتورهای بزرگ

> **هدف:** ثبت دقیق فایل‌های تغییریافته و خلاصه‌ای از اصلاحات انجام‌شده در جلسات کاری سنگین.

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۶/۳۰ (2026-09-20) - فاز تثبیت وب (Web Stabilization Phase)

### 📝 خلاصه اقدامات
- اصلاح فراخوانی متد `TaskAssignmentService::assign` و نگاشت دقیق پارامترهای سازنده DTO در `AssignTaskRequest`.
- اصلاح فرآیند ارسال کار در `TaskController::submit` با جایگزینی `submitForReview` به جای متد ناموجود و اصلاح ماشین وضعیت.
- بازنویسی نمایش وضعیت SLA در ویوی `tasks/show.blade.php` با استفاده مستقیم از رابطه `slaRecords` و هلپر تاریخ شمسی.
- تغییر روت حذف وابستگی به متد امنیتی `POST` و تصحیح مدل `TaskDependency` جهت امکان‌پذیر شدن حذف وابستگی.
- نگارش ۱۲ تست Feature در `WebTaskStabilizationTest.php` و پاس شدن ۱۰۰٪ آزمون‌های سراسری در PostgreSQL (۱۱۴ تست، ۳۳۴ assertion).

### 🛠️ فایل‌های تغییر یافته

```text
app/Http/Controllers/Web/TaskAssignmentController.php
app/Http/Controllers/Web/TaskController.php
app/Http/Requests/Web/AssignTaskRequest.php
app/Models/TaskDependency.php
resources/views/tasks/show.blade.php
routes/web.php
tests/Feature/Web/WebTaskStabilizationTest.php
project_context/ANTIGRAVITY_CHANGELOG.md
project_context/ANTIGRAVITY_SESSION_LOG.md
TMS_PROJECT_TRACKER.md
MEMORY.md
ACTION_TRACKER.md
```

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۶/۳۰ (2026-09-20)

### 📝 خلاصه اقدامات
- پیاده‌سازی و یکپارچه‌سازی جامع تقویم و تاریخ هجری شمسی (جلالی) در تمام بخش‌های سامانه:
  - ایجاد هلپرها و کلاس‌های هسته `SafeJalali` و `JalaliDate` با جلوگیری از NPE و پشتیبانی از ارقام فارسی/عربی.
  - تعبیه استایل‌ها و کتابخانه تقویم شمسی در لی‌آوت اصلی و فعال‌سازی در اینپوت‌های فرم‌ها.
  - اصلاح فرم‌های ثبت تسک، فرم آپلود مدارک و نمایش ستون مهلت در جدول تسک‌ها به شمسی.
  - تبدیل خودکار تاریخ و زمان شمسی ورودی به میلادی در لایه Request قبل از اعتبارسنجی.
  - اصلاح نام و محتوای خروجی فایل‌های گزارش CSV به فرمت شمسی.
  - نگارش تست‌های کامل Unit و Feature برای اعتبارسنجی قابلیت‌های شمسی با پاس شدن ۱۰۰٪ تمام ۱۰۲ تست پروژه.

### 🛠️ فایل‌های تغییر یافته

```text
app/Support/JalaliDate.php
app/Support/helpers.php
app/Providers/AppServiceProvider.php
composer.json
resources/views/layouts/app.blade.php
resources/views/tasks/create.blade.php
resources/views/tasks/show.blade.php
resources/views/tasks/index.blade.php
app/Http/Controllers/Web/ReportController.php
app/Http/Requests/Web/StoreTaskRequest.php
app/Http/Requests/Web/StoreDocumentRequest.php
tests/Unit/JalaliDateTest.php
tests/Feature/Web/WebTaskJalaliDateTest.php
project_context/ANTIGRAVITY_CHANGELOG.md
project_context/ANTIGRAVITY_HANDOFF.md
project_context/ANTIGRAVITY_SESSION_LOG.md
project_context/walkthrough.md
TMS_PROJECT_TRACKER.md
MEMORY.md
ACTION_TRACKER.md
```