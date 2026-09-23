# DATABASE SAFETY — Operational Gate

**Created:** 2026-09-22 (V1.8 Remaining Hardening — T-6)
**Scope:** جلوگیری از اجرای تصادفی عملیات destructive روی دیتابیس اشتباه (`tms` به‌جای `tms_testing`).

---

## ۱. مدل دیتابیس این پروژه

| دیتابیس | نقش | قابل بازسازی؟ |
|---|---|---|
| `tms` | **Production** — دادهٔ واقعی (پروژه/تسک/کاربر) | ❌ **هرگز** — هر عملیات مخرب روی آن ممنوع |
| `tms_testing` | Test — disposable | ✅ بله — هر اجرای Suite آن را بازسازی می‌کند |

هر دو روی PostgreSQL 18.6 · `127.0.0.1:5432` · encoding `UTF8 / C`.

---

## ۲. سطح‌های محافظت

### ۲.۱ ENFORCED — درون Test Suite

`tests/TestCase.php` شامل **T-6 Safety Gate** است: قبل از هر تست، نام واقعی دیتابیسِ
connection پیش‌فرض خوانده می‌شود (`DB::connection()->getDatabaseName()`) و اگر به
`testing` ختم نشود، Suite با پیام صریح **fail** می‌شود — قبل از اینکه
`RefreshDatabase` فرصت بازسازی داشته باشد.

```text
پوشش داده‌شده:  php artisan test  (و هر اجرای PHPUnit)
مکانیزم:        بررسی نام دیتابیسِ واقعیِ متصل‌شده — نه یک flag دستی
نام معتبر:      هر نامی که به «testing» ختم شود (پروژه: tms_testing)
```

این محافظ واقعی است، زیرا مسیر reset فریمورک (RefreshDatabase درون تست‌ها) از
همین TestCase عبور می‌کند.

### ۲.۲ DOCUMENTED — دستورات artisan دستی

دستورات artisan **بیرون از Suite** از این گارد عبور نمی‌کنند. برای آن‌ها این سند
گیت عملیاتی است:

#### ✅ Safe

```bash
php artisan test                 # Suite کامل — توسط ENFORCED gate محافظت می‌شود
php artisan test --compact tests/Feature/V18/
php artisan migrate:status       # read-only
php artisan db:show              # read-only
```

#### ⚠️ Potentially destructive — فقط با تأیید صریح و بررسی `migrate:status`

```bash
php artisan migrate:fresh        # DROP تمام جدول‌ها + اجرای مجدد — روی دیتابیسِ .env فعلی!
php artisan migrate:refresh      # rollback + migrate دوباره
php artisan migrate:reset        # rollback همه
php artisan db:wipe              # حذف همهٔ جدول‌ها
php artisan db:seed              # درج داده
```

**قاعدهٔ عملیاتی:** قبل از اجرای هر یک از دستورات بالا، `php artisan migrate:status`
را اجرا کنید و **قطعی تأیید کنید** که `DB_DATABASE` در `.env` (یا با
`--env=testing`) به `tms_testing` اشاره می‌کند — نه `tms`. روی `tms` این دستورات
**در هیچ شرایطی مجاز نیستند.**

> نکتهٔ تاریخی: بازسازی `tms_testing` (فاز `ENV-1`) فقط با **مجوز صریح مالک
> پروژه** انجام شد. همین الگو برای هر reset آیندهٔ دیتابیس تست الزامی است.

---

## ۳. محدودیت‌ها (صادقانه)

| محدودیت | توضیح |
|---|---|
| دستورات artisan دستی | از گارد TestCase عبور نمی‌کنند ⇒ فقط DOCUMENTED SAFETY |
| تغییر دستی phpunit.xml | اگر توسعه‌دهنده `DB_DATABASE` را به چیز دیگری تغییر دهد که به `testing` ختم **نشود**، گارد Suite را متوقف می‌کند (رفتار صحیح)؛ اگر به نام دیگری با پسوند testing بدهد، گارد آن را می‌پذیرد (رفتار عمدی — هر دیتابیس تستی مجاز است) |
| CI | هنوز وجود ندارد (`T-7` — فاز بعدی). وقتی ساخته شد، باید `DB_DATABASE=tms_testing` را صریح ست کند و همین گارد آن را enforce می‌کند |

---

## ۴. مرجع

- گارد Enforcement: `tests/TestCase.php` (T-6 Safety Gate)
- تحلیل Reconnaissance: `docs/V1.8_REMAINING_HARDENING_TECHNICAL_REVIEW.md` (PART 6)
- سابقهٔ مجوز بازسازی: `docs/V1.8_PRODUCTION_MIGRATION_REPORT.md` (ENV-1)
