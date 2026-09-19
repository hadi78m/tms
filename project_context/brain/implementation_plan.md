# Phase V1.6 — Dynamic Settings & Export Reports

این برنامه جهت پیاده‌سازی فاز نهایی دامنه نسخه اول (V1.6) تنظیم شده است که شامل راه‌اندازی زیرساخت تنظیمات پویا، تولید گزارش‌های تفکیکی و قابلیت برون‌ریزی (CSV Export) می‌باشد.

## User Review Required

> [!IMPORTANT]
> لطفا این برنامه را بررسی کرده و در صورت تایید، اجازه شروع اجرای آن را بدهید. پس از اجرای این برنامه، تمام تست‌ها برای اطمینان از صحت عملکرد اجرا خواهند شد.

## Open Questions

> [!NOTE]
> آیا ساختار جدول `system_settings` نیاز به قابلیت‌های چندزبانه یا ذخیره‌سازی آرایه (JSON) برای هر کلید دارد، یا صرفا رشته و مقادیر پایه کافی است؟ (پیش‌فرض سیستم به صورت ذخیره‌سازی رشته‌ای با قابلیت کستینگ بر اساس ستون `type` طراحی می‌شود).

## Proposed Changes

---

### 1. Database & Models (System Settings)

#### [NEW] `database/migrations/xxxx_xx_xx_xxxxxx_create_system_settings_table.php`
- ایجاد جدول `system_settings` با ستون‌های `key` (منحصربه‌فرد)، `value` (متنی/JSON)، `type` (رشته، بولین، عدد و...) و `description`.

#### [NEW] `app/Models/SystemSetting.php`
- مدل متصل به جدول تنظیمات با قابلیت کستینگ مقادیر.

#### [NEW] `database/seeders/SystemSettingsSeeder.php`
- سیدر جهت مقداردهی پیش‌فرض تنظیمات (`approval_mode`, `allow_reopen`, `require_evidence_on_submit`, `lock_weight`).

---

### 2. Services & Utilities

#### [NEW] `app/Domain/Services/SettingsService.php`
- سرویسی برای دسترسی سریع به تنظیمات با قابلیت Cache و ارائه مقادیر جایگزین (Fallback).
- امکان ثبت هلپر `setting()` به صورت سراسری در صورت لزوم.

#### [NEW] `app/Services/ExportService.php` (یا متد در ReportController)
- ابزاری سبک برای ساخت خروجی‌های CSV به صورت استریم (StreamedResponse) بدون وابستگی به پکیج‌های سنگین خارجی.

---

### 3. Controllers & Routes

#### [NEW] `app/Http/Controllers/Web/SettingController.php`
- کنترلری برای صفحه مدیریت تنظیمات (`index`) و ذخیره/بروزرسانی (`update`) که صرفاً در دسترس نقش `admin` باشد.

#### [NEW] `app/Http/Controllers/Web/ReportController.php`
- کنترلری جهت ساخت گزارشات تفکیکی برای کاربران با نقش‌های مدیریتی/نظارتی و خروجی اکسل/CSV.

#### [MODIFY] `routes/web.php`
- افزودن مسیرهای `/admin/settings` با اعمال Middleware نقش `admin`.
- افزودن مسیرهای `/reports` و `/reports/export` برای ساختار گزارش‌دهی با دسترسی نقش‌های مجاز.

---

### 4. Views (UI)

#### [NEW] `resources/views/settings/index.blade.php`
- فرم مدیریت سیستم در سایدبار ادمین با استایل تیلویند (RTL).

#### [NEW] `resources/views/reports/index.blade.php`
- نمای تجمیعی برای نمایش جدول‌ها و نمودارهای گزارش‌گیری شامل:
  - گزارش پیشرفت ادعایی و تایید شده.
  - گزارش تاخیرات SLA و موارد بحرانی.
  - آمادگی پرداخت و تسک‌های Approved شده براساس پروژه و وزن.

#### [MODIFY] `resources/views/layouts/app.blade.php`
- افزودن لینک‌های «تنظیمات سیستم» و «گزارش‌ها» در منوی سایدبار.

---

### 5. Testing

#### [NEW] `tests/Feature/SystemSettingTest.php`
- تست دسترسی و بروزرسانی تنظیمات و سرویس `SettingsService`.

#### [NEW] `tests/Feature/ReportExportTest.php`
- تست بارگذاری گزارشات و دریافت فایل خروجی CSV با دیتای معتبر.

## Verification Plan

### Automated Tests
- اجرای `php artisan test --compact` در محیط تست PostgreSQL.
- بررسی عدم ایجاد Regression در فازهای پیشین.

### Manual Verification
- ورود با نقش Admin، تغییر تنظیمات در پنل و مشاهده تاثیر آن.
- تولید داده‌های تستی (تسک‌ها، تاییدات و SLA) و بررسی خروجی CSV گزارشات از طریق مرورگر.
- بررسی محدودیت‌های دسترسی به گزارشات برای کاربران عادی.
