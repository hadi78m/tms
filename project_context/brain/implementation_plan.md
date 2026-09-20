# TMS V1.7 — Stabilization Implementation Plan

## اهداف و محدوده (Scope)
این فاز صرفاً برای تثبیت عملکردهای تأییدشده لایه وب، رفع خطاهای ران‌تایم شناسایی‌شده در ممیزی اخیر و هماهنگ‌سازی متدهای کنترلر و درخواست با لایه دامین است.
**قلمرو وزن (Weight) کاملاً دست‌نخورده (FROZEN) باقی می‌ماند.**

## اقدامات اجرایی

### ۱. اصلاح تخصیص و ارجاع وظایف (Task Assignment)
- اصلاح `AssignTaskRequest::toDto()` برای تطابق ۱۰۰٪ با پارامترهای نام‌دار `AssignTaskData` (`task_id`, `user_id`, `assigned_by`, `reason`).
- افزودن اعتبارسنجی دامنه پیمانکار به `AssignTaskRequest::authorize()`.
- اصلاح متد فراخوانی‌شده در `TaskAssignmentController::store` از `assignTask` به متد واقعی سرویس: `TaskAssignmentService::assign()`.

### ۲. اصلاح ثبت ارائه کار تسک (Task Submission)
- اصلاح خط ۱۱۴ در `TaskController::submit`: جایگزینی متد ناموجود `updateStatus` و وضعیت نامعتبر `completed` با متد دامین:
  `TaskService::submitForReview($task, $user)`
- حفظ وضعیت‌های رسمی ماشین وضعیت (`in_progress -> submitted_for_review`).
- حفظ عملکرد خودکار توقف Resolution SLA و ثبت وقایع حسابرسی.

### ۳. اصلاح نمایش وضعیت SLA در صفحه جزئیات تسک
- اصلاح `resources/views/tasks/show.blade.php` جهت خواندن مستقیم داده‌های SLA از رابطه موجود `slaRecords` به جای متغیرهای ناموجود `$task->sla_started_at` و `$task->sla_stopped_at`.
- نمایش وضعیت و تایمرهای واقعی Resolution SLA و Response SLA.
- اصلاح شرط نمایش دکمه «اعلام اتمام وظیفه» بر اساس وضعیت `in_progress` و پیمانکار مجری.

### ۴. تغییر روت حذف وابستگی به متد امن POST
- تغییر روت `tasks/{task}/dependencies/{dependency}` در `routes/web.php` به متد `POST` با مسیر `tasks/{task}/dependencies/{dependency}/remove` جهت انطباق با قوانین OWASP و AGENTS.md.
- حذف دستور `@method('DELETE')` از فرم حذف پیش‌نیاز در `resources/views/tasks/show.blade.php`.
- افزودن اعتبارسنجی مالکیت وابستگی در کنترلر.

### ۵. آزمون‌های ویژگی (Feature Tests)
- ایجاد آزمون‌های جامع در `tests/Feature/Web/WebTaskStabilizationTest.php` برای پوشش کامل سناریوهای ارجاع، ارسال کار، حذف وابستگی و نمایش SLA.
- اجرای کامل آزمون‌ها در دیتابیس PostgreSQL و تایید سبز بودن ۱۰۰٪ تست‌ها.
