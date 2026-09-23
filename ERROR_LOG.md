# ERROR_LOG.md - دفترچه ثبت خطاهای پروژه و محیط اجرا

> **هدف:** ثبت خطاهای رخ‌داده، خطاهای محیطی (Linux/Server) و راهکارهای حل آن‌ها جهت جلوگیری از تکرار مجدد توسط ایجنت‌ها.
> **تاریخ آخرین بروزرسانی:** ۱۴۰۵/۰۶/۳۱ (2026-09-22)
> **وضعیت کلی:** در حال توسعه — فاز V1.8 Production Migration **با موفقیت کامل شد** (`PRODUCTION MIGRATION SUCCESSFUL`) · **`tms` با پشتیبان پیش از تغییر، به ۳۲ مهاجرت مهاجرت کرد** (`35 tables · 4 triggers · 3 functions`) · **صفر ردیف دادهٔ موجود حذف یا بازنویسی شد** · **همهٔ موانع رفع شدند:** `DatabaseAuditService` ساخته شد · **`ENV-1` (رمزگذاری WIN1252) با بازسازی `tms_testing` روی UTF8 رفع شد** · **کل Test Suite سبز: `175 passed · 2 deprecated · 504 assertions · 0 failed`**

---

## ✅ رفع‌شده — `R-2` (فاز Post-Migration Hardening): `task_type` هیچ نویسنده‌ای در کد نداشت

- **تاریخ کشف:** ۱۴۰۵/۰۶/۳۱ (Reconciliation) — **تاریخ رفع:** ۱۴۰۵/۰۶/۳۱ (Hardening)
- **شرح:** ستون `tasks.task_type` (`M-05`, `NOT NULL DEFAULT 'development'`) هیچ نویسنده‌ای در لایهٔ Application نداشت؛ هر تسک جدید از مسیر HTTP بی‌صدا `development` می‌گرفت و `BD-06` در عمل بی‌اثر بود.
- **رفع (طبق تصمیم مالک `DEC-037` = `T-2-A` + `T-2-UI-A`):** `task_type` ورودی اجباری Create Task شد — `StoreTaskRequest` (required + `new Enum(TaskType::class)`) · `CreateTaskData` (`TaskType` اجباری) · `TaskService::create` (نویسندهٔ صریح) · فیلد UI در `tasks/create.blade.php`. بدون silent fallback و بدون مقدار جدید در Vocabulary.
- **تست:** `tests/Feature/Web/TaskTypeAndWeightPolicyTest.php` (۱۲ تست) — شامل invalid/missing → validation failure و بدون ایجاد تسک.
- **دروازه:** بسته شد.

## ✅ بسته‌شده — `R-1` (فاز Post-Migration Hardening): `tasks.weight` معیار گزارش و NULL-پذیر

- **تاریخ کشف:** ۱۴۰۵/۰۶/۳۱ (Reconciliation) — **تاریخ بسته‌شدن:** ۱۴۰۵/۰۶/۳۱ (Hardening)
- **شرح:** `ReportController` از `SUM(tasks.weight)` استفاده می‌کرد در حالی که `M-07` ستون را NULL-پذیر کرده بود؛ `SUM` ردیف NULL را بی‌صدا نادیده می‌گرفت.
- **تصمیم مالک (`DEC-036` = `R1-D` + `R1-F1`):** NULL در مسیر عادی **ممنوع** (weight در Create Task اجباری می‌ماند)؛ گزینه‌های NULL=صفر / حذف / Incomplete رد شدند ⇒ رفتار گزارش عمداً دست‌نخورد و صفر تغییر کد گزارشی انجام شد.
- **تست نگهبان:** `weight` missing/empty → validation failure · مسیر HTTP هرگز NULL تولید نمی‌کند · Schema (M-07) دست‌نخورده.
- **دروازه:** بسته شد.

---

## ✅ رفع‌شده — `T-1`: `supersede()` می‌توانست مقدار را «اضافه» کند به‌جای «جایگزینی»

- **تاریخ کشف:** ۱۴۰۵/۰۶/۳۱ (2026-09-22) — فاز T-1 Resolution
- **نشانه:** یک Probe با اجرای واقعی روی PostgreSQL 18.6 نشان داد می‌توان برای یک ردیف تاریخی **دو جانشین** ساخت.
- **علت ریشه‌ای:**
```text
UNIQUE(supersedes_approval_id) عمداً اعمال نشده بود (ACCEPTED AS-IS مالک پروژه)
        ⇒ دیتابیس هیچ چیزی را نمی‌بندد

و در لایهٔ سرویس:
  supersede() هیچ گاردی روی خودِ $source نداشت
  ⇒ تنها چیزی که تصادفاً یک حالت را می‌بست، قاعدهٔ جانبی «فقط یک pending» بود
  ⇒ مادامی که جانشین اول pending بود مسدود می‌شد، ولی
    به‌محض تصمیم‌گرفتن جانشین اول، بن‌بست باز می‌شد
```
- **اثر واقعی (نمونهٔ عددی قطعی):**
```text
A = 5 (approved) → supersede → B = 7 (approved)   ⇒ approvedWeight = 7  ✅
                            → supersede دوبارهٔ A → C = 3
                            ⇒ approvedWeight = 10  ❌ (باید 7 می‌ماند)
سقف Stage = 15 بود، پس check ظرفیت این خطا را نگرفت (7 + 3 = 10 ≤ 15)
```
- **درس:** تکیه بر یک قاعدهٔ **جانبی** (تک‌pending) به‌جای گارد **صریح** روی خودِ invariant، فقط یک زیرمجموعهٔ حالت‌ها را می‌بندد. این دقیقاً همان الگویی است که `BUG-2` در فاز Migration Implementation نشان داده بود.
- **راه‌حل:** استفاده از متد **موجود** `assertNotSuperseded()` در `supersede()` — یک خط، بدون تغییر Schema.
- **نقص هم‌ردهٔ پیدا‌شده در همان Audit:** `adjust()` تاریخ یک ردیف superseded را بازنویسی می‌کرد (`proposed_amount` از `5` به `11`)، نقض `DEC-027`. با همان گارد بسته شد.
- **پوشش تست:** `tests/Feature/V18/StageProgressApprovalSupersedeChainTest.php` — ۱۶ تست.

---

## ⚠️ درس روش‌شناسی — مقایسهٔ متن فارسی با literal داخل خودِ دستور (نتیجهٔ منفی کاذب)

- **تاریخ مشاهده:** ۱۴۰۵/۰۶/۳۱ (2026-09-22) — تأیید پس از Production Migration
- **دستور(های) اجراشده:**
```bash
psql -d tms -tAc "select description = 'حالت تعیین و تأیید درصد پیشرفت مرحله' from system_settings where key='progress_approval_mode';"
```
- **خروجی گمراه‌کننده:** `f` (عدم تطابق) — در حالی که داده **کاملاً سالم** بود.
- **علت ریشه‌ای:** literal فارسی داخل خودِ command string قبل از رسیدن به `psql` توسط لایهٔ shell/pipe دستخوش تغییر می‌شود؛ پس **متن مبنا** خراب مقایسه می‌شود، نه داده.
- **روش تأیید قطعی (روش درست):**
```bash
# هیچ متن فارسی از shell عبور نمی‌کند
psql -d tms -tAc "select length(description), octet_length(description), position(chr(65533) in description) from system_settings where key='progress_approval_mode';"
# chars=36 · bytes=66 · replacement_char=0  → بدون mojibake
```
و سپس مقایسهٔ **MD5** مقدار Database با MD5 همان literal که از **فایل مهاجرت** خوانده می‌شود:
```text
desc_md5 (DB)            = 85805816d962de31632e2103bf873447
literal_md5 (migration)  = 85805816d962de31632e2103bf873447   ✅ یکسان
```
- **قاعدهٔ رفتاری برای ایجنت‌ها:** برای تأیید صحت متن غیر-ASCII، **هرگز** آن متن را داخل command string نگذارید. از `length` / `octet_length` / `position(chr(65533))` / MD5 استفاده کنید.

---

## ✅ رفع‌شده — `ENV-1`: رمزگذاری `tms_testing` روی WIN1252 بود (بلوکه‌کنندهٔ کل Test Suite)

- **تاریخ کشف:** ۱۴۰۵/۰۶/۳۱ (2026-09-22) — فاز V1.8 Migration Implementation
- **دستور(های) اجراشده:**
```bash
psql -h 127.0.0.1 -p 5432 -U root -d postgres -c "SELECT datname, pg_encoding_to_char(encoding) FROM pg_database;"
DB_DATABASE=tms_testing php artisan migrate --force
php artisan test tests/Feature/ExampleTest.php
```
- **پیام خطا (Error Output):**
```text
SQLSTATE[22P05]: Untranslatable character: 7 ERROR:  character with byte sequence 0xd8 0xad in encoding "UTF8"
has no equivalent in encoding "WIN1252"
CONTEXT:  unnamed portal parameter $4
SQL: insert into "system_settings" ("key", "value", "type", "description", "created_at", "updated_at")
     values (progress_approval_mode, supervisor_only, string, حالت تعیین و تأیید درصد پیشرفت مرحله, ...)
```
- **علت ریشه‌ای (اثبات‌شده):**
```text
tms          → encoding = UTF8     (ساخته‌شده با TEMPLATE template0)   ✅
tms_testing  → encoding = WIN1252  (ارث‌بری از template1 ویندوز)        ❌
```
- **دامنهٔ اثر:** `M-09` (تنها مهاجرت با متن فارسی) روی `tms_testing` اجرا نمی‌شود؛ `RefreshDatabase` → `migrate:fresh` در همان نقطه می‌شکند ⇒ **کل Test Suite غیرقابل اجرا است** (شامل ۵۹ سناریوی جدید V1.8).
- **⚠️ خطر خاموش:** `psql` مقدار `client_encoding` را از خود دیتابیس می‌گیرد؛ لذا درج فارسی از طریق `psql` **خطا نمی‌دهد** و **mojibake** ذخیره می‌کند. مسیر Laravel (که `client_encoding=UTF8` تنظیم می‌کند) به‌صورت سخت خطا می‌دهد. **هرگز دادهٔ فارسی را با `psql` در `tms_testing` ننویسید.**
- **اقدام رفع (انجام نشد — نیازمند مجوز صریح مالک پروژه، چون شامل `DROP DATABASE` است):**
```sql
DROP DATABASE tms_testing;
CREATE DATABASE tms_testing WITH TEMPLATE template0 ENCODING 'UTF8' LC_COLLATE 'C' LC_CTYPE 'C';
```
  `tms_testing` **صفر رکورد داده** دارد (همهٔ جداول عملیاتی خالی) و ماهیتاً دیتابیس تست است که `RefreshDatabase` هر بار بازسازی می‌کند.
- **درس آموخته:** هر مانع محیطی که «سبزشدن تست‌ها» را توضیح می‌دهد، باید با بررسی `pg_encoding_to_char(encoding)` رد یا تأیید شود. عدم حضور متن فارسی در ۳۰ فایل تست، همین نقص را تا امروز پنهان کرده بود.
- **تاریخ رفع:** ۱۴۰۵/۰۶/۳۱ (2026-09-22) — با **مجوز صریح مالک پروژه**.
- **اقدام رفع (اجرا شد):**
```bash
psql -U root -d postgres -c "DROP DATABASE IF EXISTS tms_testing;"
psql -U root -d postgres -c "CREATE DATABASE tms_testing WITH TEMPLATE template0 ENCODING 'UTF8' LC_COLLATE 'C' LC_CTYPE 'C';"
DB_DATABASE=tms_testing php artisan migrate --force     # 32/32 DONE
php artisan test                                          # 175 passed / 0 failed
```
- **تأیید رفع:** `tms_testing` → `encoding = UTF8` · `datcollate = C` (دقیقاً مثل `tms`) · `M-09` متن فارسی را با ۳۶ کاراکتر درست ذخیره کرد · `system_settings.progress_approval_mode = supervisor_only`.
- **درس دوم (مهم‌تر):** **دو نقص منطقی واقعی** (`BUG-1`: مجموع map در برابر مجموع نهایی در بازتوازن Stage — جابه‌جایی وزن بین دو مرحله غیرممکن بود؛ `BUG-2`: فیلترنشدن ردیف‌های superseded در «یک pending» و امکان تصمیم روی ردیف superseded) **فقط با اجرای واقعی ۶۳ سناریو** کشف شدند، نه با بازبینی ایستا. هر دو رفع و تست رگرسیون برایشان اضافه شد.

---

## 📊 خلاصه وضعیت خطاها

| وضعیت | تعداد |
| :--- | :--- |
| ✅ رفع شده (Resolved) | 0 |
| ⏳ در حال بررسی (In Progress) | 3 |
| ⚠️ وابسته به محیط/سرور (Environment Dependent) | 1 |

---

## ⏳ موارد در حال بررسی (Pending / In Progress)

### ⏳ ۱. PostgreSQL در حال اجرا نیست — اجرای تست‌ها غیرممکن است

- **تاریخ کشف:** ۱۴۰۵/۰۶/۳۰ (2026-09-21)
- **فاز کشف:** V1.8 Weight / Module / WBS Design Freeze (ممیزی READ-ONLY)
- **فایل(های) مرتبط:** `phpunit.xml` (خطوط `DB_CONNECTION=pgsql`, `DB_DATABASE=tms_testing`)
- **دستور اجراشده:**
```bash
php artisan test --compact
```
- **پیام خطا (Error Output):**
```text
SQLSTATE[08006] [7] connection to server at "127.0.0.1", port 5432 failed:
Connection refused (0x0000274D/10061)
	Is the server running on that host and accepting TCP/IP connections?

(Connection: pgsql, Host: 127.0.0.1, Port: 5432, Database: tms_testing,
 SQL: select exists (select 1 from pg_class c, pg_namespace n
                     where n.nspname = current_schema()
                       and c.relname = 'migrations'
                       and c.relkind in ('r', 'p') and n.oid = c.relnamespace))

at vendor\laravel\framework\src\Illuminate\Database\Connectors\Connector.php:67
```
- **نتیجه اجرا:**
```text
Tests:  1 deprecated, 97 failed, 16 passed (51 assertions)
Duration: 1594.48s
```
- **تأیید تشخیص:**
```bash
pg_isready -h 127.0.0.1 -p 5432
→ 127.0.0.1:5432 - no response

netstat -ano | grep 5432
→ (بدون خروجی — هیچ فرایندی روی پورت ۵۴۳۲ گوش نمی‌دهد)
```
- **علت:** سرویس PostgreSQL روی ماشین توسعه در حال اجرا نیست. **تمام ۹۷ شکست** از یک جنس‌اند: خطای اتصال به دیتابیس. **هیچ شکستی ناشی از رگرسیون کد نیست** — در آن فاز هیچ فایلی تغییر نکرد (`git status` فقط فایل‌های `*.md` را نشان می‌دهد).
- **اهمیت:** بیس تست «۱۱۴ تست / ۳۳۴ assertion (100% green on PostgreSQL)» که در `TMS_PROJECT_TRACKER.md §18` و `MEMORY.md` ثبت شده، در این فاز **تأییدنشده** باقی ماند (تناقض `C-10`).
- **اقدام بعدی لازم:**
  1. اجرای سرویس PostgreSQL روی `127.0.0.1:5432`.
  2. اطمینان از وجود دیتابیس `tms_testing` با کاربر `root` (طبق `.env` — `DB_USERNAME=root`).
  3. اجرای `php artisan test --compact` و ثبت نتیجهٔ واقعی.
  4. اگر بیس سبز شد، وضعیت این مورد به «رفع شده» منتقل شود.
  5. **⚠️ تا رفع این مورد، هیچ فاز پیاده‌سازی (V1.8a) شروع نشود** — رگرسیون‌ها قابل انتساب نخواهند بود.
- **قاعدهٔ مرتبط:** `AGENTS.md` → «Safe Test Execution» — استفاده از SQLite in-memory برای تست **ممنوع** است چون Migrationها از ویژگی‌های اختصاصی PostgreSQL استفاده می‌کنند (Identity PK، JSONB، Partial Unique Index، CHECK با Regex).

#### 🔄 به‌روزرسانی وضعیت — ۱۴۰۵/۰۶/۳۱ (2026-09-22)

**این مورد هنوز «رفع شده» نیست، اما علت اصلی آن تغییر کرده است:** سرور PostgreSQL اینک در حال اجرا **است**.

```bash
netstat -ano                          → 127.0.0.1:5432 و [::1]:5432 در حالت LISTENING
Get-Process -Id 22436                 → postgres
pg_isready -h 127.0.0.1 -p 5432       → accepting connections (exit=0)
```

**آنچه تأیید شد:** فرایند سرور PostgreSQL روی پورت ۵۴۳۲ فعال است.
**آنچه هنوز تأییدنشده است (UNVERIFIED):**
- وجود دیتابیس `tms` و `tms_testing` — تأیید نیازمند اجرای SQL است.
- اتصال Laravel به PostgreSQL با پیکربندی `phpunit.xml`.
- اجرای واقعی بیس تست: در فاز ۱۴۰۵/۰۶/۳۱ **هیچ تستی اجرا نشد** (اجرای SQL/تست در آن فاز ممنوع بود).

**⛔ بیس «۱۱۴ تست / ۳۳۴ assertion» همچنان UNVERIFIED است — نه PASS و نه FAIL.** عدم اجرا نباید به‌عنوان PASS تفسیر شود و هیچ fallback به SQLite پذیرفته نمی‌شود.

**اقدام بعدی (به‌روزشده):**
1. تأیید پایداری سرور PostgreSQL روی `127.0.0.1:5432`.
2. ایجاد/تأیید دیتابیس `tms` (توسعه) و `tms_testing` (تست) با کاربر `root`.
3. تأیید اتصال Laravel و اجرای **واقعی** `php artisan test --compact` روی PostgreSQL.
4. **ثبت نتیجه فقط در صورت اجرای واقعی** — سپس وضعیت این مورد به «رفع شده» منتقل شود.
5. ⚠️ تا آن لحظه، هیچ فاز پیاده‌سازی (V1.8a) شروع نشود — رگرسیون‌ها قابل انتساب نخواهند بود.

#### ✅ رفع جزئی نهایی — ۱۴۰۵/۰۶/۳۱ (2026-09-22) · فاز V1.8 Migration Review (همهٔ بررسی‌ها خواندنی)

**محیط دیتابیس تأیید شد — هیچ DDL/DML اجرا نشد:**

```text
pg_isready -h 127.0.0.1 -p 5432    → accepting connections (exit=0)
netstat -ano | grep 5432           → 127.0.0.1:5432 و [::1]:5432 در LISTENING · PID 22436
psql --version                     → PostgreSQL 18.6
php artisan db:show                → PostgreSQL 18.6 · Connection pgsql · Database tms · 31 tables · 1.33 MB
php artisan migrate:status         → 23/23 Ran (همه Batch 1) · صفر Migration V1.8
pg_database                        → postgres|root · tms|root · tms_testing|root
information_schema (tms)           → tasks.weight = numeric / is_nullable = NO
                                     task_type · module_id · module_stage_id → وجود ندارند
information_schema (tms + testing) → صفر جدول از modules / module_stages /
                                     stage_progress_approvals / wbs_phase_checklist_items
شمارش داده (tms)                    → projects=1 · wbs_phases=1 · tasks=3 · tasks.weight IS NULL = 0
```

**وضعیت جدید:** سرور ✅ · `tms` ✅ · `tms_testing` ✅ · اتصال Laravel ✅ · اجرای Migration ⛔ در این فاز عمداً انجام نشد.
**بندهای ۱، ۲ و ۳ فهرست «اقدام بعدی» بالا انجام شدند.** فقط بند ۴ (اجرای واقعی بیس) باز است:

```text
php artisan test --testsuite=Unit --compact
→ 1 deprecated, 16 passed (51 assertions) · Duration: 4.96s      ✅ اجرای واقعی — بدون دیتابیس

php artisan test --compact
→ NOT RUN — would require schema mutation
```

**علت فنی بازماندن بیس کامل:** `tests/Pest.php` برای تمام تست‌های `Feature` تریت `RefreshDatabase` را اعمال می‌کند؛ روی اتصال `pgsql` (غیر In-Memory) این تریت عملاً `migrate:fresh` روی `tms_testing` اجرا می‌کند و این فاز صریحاً `migrate:fresh` / `migrate:refresh` / `migrate:reset` را ممنوع کرده بود. پس **اجرای بیس کامل ساختاراً ناممکن بود، نه فقط ممنوع.**
**اقدام لازم:** بازتأیید بیس در یک گام مستقل با **مجوز صریح مالک پروژه** (پذیرش `migrate:fresh` روی `tms_testing`) و سپس ثبت نتیجهٔ واقعی. تا آن لحظه بیس «۱۱۴/۳۳۴» **UNVERIFIED** می‌ماند.

**مرجع:** `docs/V1.8_DETAILED_SCHEMA_DESIGN.md` §۹.۴ و §۱۵.۲ · `docs/V1.8_MIGRATION_REVIEW_CHECKLIST.md` محور Environment · `docs/V1.8_MIGRATION_REVIEW_REPORT.md` بخش ۱۱ و ۱۸ · `DEC-019`

#### 🔒 وضعیت پس از Owner Decision Closure — ۱۴۰۵/۰۶/۳۱

**این مورد دیگر مانعِ طراحی نیست و مانعِ محیطی هم ندارد.** مالک پروژه تمام پرسش‌های باز را بست (`DEC-020`..`DEC-035`) و محیط تأیید شد.

```text
باقی‌ماندهٔ باز (تنها):
  بازتأیید بیس تست کامل — نیازمند مجوز صریح (RefreshDatabase ⇒ migrate:fresh روی tms_testing)
  بیس «۱۱۴ تست / ۳۳۴ assertion» → همچنان UNVERIFIED (نه PASS و نه FAIL)
```

| مؤلفه | وضعیت تأییدشده |
|---|---|
| سرور PostgreSQL | ✅ 18.6 · PID 22436 · `pg_isready` → accepting connections |
| `tms` و `tms_testing` | ✅ موجود (owner `root`) · ۳۱ جدول هرکدام |
| اتصال Laravel | ✅ تأییدشده (`php artisan db:show`) · ۲۳/۲۳ Migration |
| جداول V1.8 | ✅ صفر (هیچ‌کدام ساخته نشده) |
| `tasks.weight` | ✅ هنوز `NOT NULL` (M-07 اجرا نشده — درست) |
| Unit tests | ✅ اجرای واقعی: `16 passed · 1 deprecated · 51 assertions` |
| Feature tests | ⏳ اجرا نشد — اقدام مخرب مستقل با مجوز جداگانه |

**مرجع به‌روز:** `docs/V1.8_FINAL_DESIGN_RECONCILIATION.md` · `DEC-035`

---

### ⏳ ۲. Audit Trail در محیط اجرا غیرفعال است (`NullAuditService`)

- **تاریخ کشف:** ۱۴۰۵/۰۶/۳۰ (2026-09-21)
- **فاز کشف:** V1.8 Weight / Module / WBS Design Freeze (ممیزی READ-ONLY)
- **فایل(های) مرتبط:**
  - `app/Providers/AppServiceProvider.php` (خطوط ۱۹-۲۲)
  - `app/Domain/Services/NullAuditService.php` (خطوط ۱۵-۱۸)
  - `app/Domain/Contracts/AuditServiceInterface.php`
- **کد عامل خطا:**
```php
// app/Providers/AppServiceProvider.php
$this->app->bind(
    AuditServiceInterface::class,
    NullAuditService::class        // ← در محیط اجرا
);
```
```php
// app/Domain/Services/NullAuditService.php
public function log(string $action, Model $entity, User $actor,
                    array $oldValues = [], array $newValues = [],
                    array $metadata = []): ActivityLog
{
    // Do nothing for testing
    return new ActivityLog;        // ← بدون save()
}
```
- **علت:** پیاده‌سازی پیش‌فرض `AuditServiceInterface` یک No-op است و در محیط اجرا (نه فقط تست) بایند شده. هیچ `save()` فراخوانی نمی‌شود.
- **پیام خطا:** ندارد — **شکست خاموش (Silent Failure)**. این خطرناک‌ترین نوع خطاست چون هیچ نشانه‌ای تولید نمی‌کند.
- **اثر:**
  - جدول `activity_logs` **هرگز توسط برنامه پر نمی‌شود**.
  - تمام ۱۳ رویداد Audit مورد نیاز در `TMS_PROJECT_TRACKER.md §15` (`task_created`، `task_assigned`، `task_weight_changed`، `task_final_approved`، `document_deleted`، …) در سیستم زنده **صفر رکورد** تولید می‌کنند.
  - الزامات قطعی V1.8 (`BD-17` و `BD-21` — «تمام تغییرات درصد باید Audit داشته باشند» و «مقدار قبلی نباید از بین برود») **غیرقابل تحقق** هستند.
  - تست‌های موجود این شکاف را نمی‌بینند چون `AuditServiceInterface` را **Mock** می‌کنند و روی فراخوانی متد تأیید می‌زنند، نه روی رکورد دیتابیس.
- **چرا در فازهای قبلی کشف نشد:** در `docs/database_physical_design_v1.5.md`، `NullAuditService` به‌عنوان «پیاده‌سازی موقت برای تست» طراحی شده بود و قرار بود بعداً با یک پیاده‌سازی واقعی جایگزین شود. این جایگزینی **هرگز انجام نشد**.
- **اقدام بعدی لازم:**
  1. ساخت `App\Domain\Services\DatabaseAuditService` که `ActivityLog::create(...)` را واقعاً فراخوانی کند.
  2. تغییر بایند در `AppServiceProvider` به `DatabaseAuditService`.
  3. **گام صفر فاز V1.8a** — پیش از هر تصمیم درصدی، این باید رفع شود.
  4. افزودن تست واقعی که وجود رکورد در `activity_logs` را تأیید کند (نه Mock).
  5. 🆕 **رویداد `document_uploaded` هیچ‌گاه فراخوانی نمی‌شود** — کل `app/Domain/Services/DocumentService.php` (۸۲ خط) بررسی شد: تنها فراخوانی Audit آن `document_deleted` (خط ۷۲) است. سند Schema §۸.۳ و `V1.8_RECONCILIATION:582` این رویداد را «موجود» می‌شمارند — **این ادعا با Repository مطابقت ندارد** و باید به فهرست «ساخته شود» منتقل شود. (یافتهٔ `H-6` گزارش `docs/V1.8_MIGRATION_REVIEW_REPORT.md`)
  6. ✅ **تصمیم قطعی مالک پروژه (`H-6` · `DEC-032`):** افزودن فراخوانی صریح `document_uploaded` در `DocumentService::uploadDocument()` داخل همان `DB::transaction` — **بدون Observer** (جلوگیری از Audit دوبل). کد مربوطه: یک موجودیت Schema جدید لازم نیست.
  7. ✅ **نگاشت `$metadata` (`OQ-25` · `DEC-030`):** `metadata['reason'] → activity_logs.reason` و سایر Metadata → `new_values['metadata']`. **هیچ ستون جدیدی ساخته نمی‌شود.**
  8. 📌 **یادداشت:** ستون‌های `ip_address` و `user_agent` در `activity_logs` امروز بی‌نویسنده‌اند؛ `DatabaseAuditService` می‌تواند آن‌ها را نیز پر کند (بدون تغییر Schema).
- **مرجع تصمیم:** `DEC-007` · `DEC-011` · `DEC-030` · `DEC-032` در `project_context/ANTIGRAVITY_DECISIONS.md` · `docs/V1.8_FINAL_DESIGN_RECONCILIATION.md` §۸

---

---

### ✅ ۳. دو نقص طراحی که پیش از نوشتن `M-03`/`M-08` باید رفع شوند (`N-1` · `N-2`) — **رفع شد**

- **تاریخ کشف:** ۱۴۰۵/۰۶/۳۱ (2026-09-22)
- **فاز کشف:** V1.8 OQ & Review Issue Resolution (بازبینی READ-ONLY)
- **فایل(های) مرتبط:** `docs/V1.8_DETAILED_SCHEMA_DESIGN.md` §۴.۳ · §۶.۱ · §۶.۲ · §۶.۴ · §۶.۵ · `docs/V1.8_OQ_AND_REVIEW_ISSUE_RESOLUTION.md`
- **نوع:** **نقص طراحی (Design Defect)** — نه خطای محیطی و نه خطای اجرا؛ اما اگر رفع نشود، در فاز Migration Implementation به خطای واقعی تبدیل می‌شود.

#### `N-1` — تناقض درونی مدل `supersede`

- **مشاهده:**
  - §۴.۳ `status ∈ {pending, approved, rejected, superseded}` و `chk_spa_decided_consistency` می‌گوید هر ردیف غیر-`pending` باید `approved_amount`/`final_approved_by`/`decided_at` داشته باشد.
  - §۶.۴ `trg_spa_immutable` هر `UPDATE` روی ردیف غیر-`pending` را رد می‌کند → گذار `approved → superseded` **غیرقابل اجرا** است.
  - §۶.۵ سقف ظرفیت روی `status = 'approved'` جمع می‌زند → اگر ردیف قدیمی `approved` بماند و ردیف جدید هم `approved` شود، **دو‌بار‌شماری** رخ می‌دهد و اصلاحِ موجه رد می‌شود.
- **اثر:** مسیر «اصلاح/ابطال تأیید» در `stage_progress_approvals` امروز منسجم نیست.
- **✅ تصمیم و اعمال (`DEC-027`):** **`superseded` از دامنهٔ وضعیت ذخیره‌شده حذف و به‌عنوان وضعیت مشتق تعریف شد**؛ سقف روی «تأییدهای فعال» شمرده می‌شود. جزئیات اعمال‌شده:
  `SUM(approved_amount)` روی ردیف‌های `status='approved'` که `NOT EXISTS (SELECT 1 FROM stage_progress_approvals s WHERE s.supersedes_approval_id = spa.id)`
  در همان تراکنش و همان قفل والد (`H-1`).

#### `N-2` — بدنهٔ تابع Trigger تجمعی برای `INSERT`/`DELETE` خطا می‌دهد

- **مشاهده:** `v_project_id := COALESCE(NEW.project_id, OLD.project_id);` در §۶.۱ (و الگوی مشابه `module_id` در §۶.۲).
  در Trigger **ردیفی** PL/pgSQL، برای `INSERT` مقدار `OLD` و برای `DELETE` مقدار `NEW` **تخصیص نیافته** است؛ ارجاع به ستون آن، خطای `record "new"/"old" is not assigned yet` می‌دهد. چون `COALESCE` هر دو استدلال را ارزیابی می‌کند، فرم فعلی برای `INSERT` و `DELETE` **خطا** می‌دهد.
- **اثر:** `M-08` (اگر تأیید شود) روی `INSERT`/`DELETE` ماژول خطا می‌دهد — نه فقط در حالت نامعتبر.
- **✅ تصمیم و اعمال (`DEC-028`):** شاخه‌بندی صریح بر `TG_OP` در بدنهٔ توابع تجمعی مستند شد (فقط طراحی — Trigger تجمعی طبق `DEC-020` ساخته نمی‌شود):
  `IF (TG_OP = 'DELETE') THEN v_project_id := OLD.project_id; ELSE v_project_id := NEW.project_id; END IF;`
- **⚠️ محدودیت اعتبارسنجی:** این یافته **تحلیل ایستا** روی متن تابع است و **با اجرا تأیید نشده** — تأیید اجرایی نیازمند `DDL` (ساخت Trigger) است که در فاز بازبینی ممنوع بود.
- **مرجع تصمیم:** `DEC-027` · `DEC-028` · `DEC-020` در `project_context/ANTIGRAVITY_DECISIONS.md` · `docs/V1.8_FINAL_DESIGN_RECONCILIATION.md` §۵ · `docs/V1.8_DETAILED_SCHEMA_DESIGN.md` §۴.۳ · §۶.۱ · §۶.۴ · §۶.۵

---

## ✅ موارد رفع شده (Resolved Issues)

### ✅ ۱. ابهام «کدام قاعده در Service و کدام در Database؟» (`N-4`) — رفع شد

- **تاریخ رفع:** ۱۴۰۵/۰۶/۳۱ (2026-09-22)
- **قاعدهٔ قطعی (`DEC-025`):**
  - **Service** مالک Business Rule: ایجاد Module · Rebalance · حذف/Restore · محاسبه و اعتبارسنجی · Approval · Authorization · Workflow · Audit orchestration.
  - **Database** مالک Data Integrity — فقط قواعد **deterministic** · **مستقل از UI** · **قابل اتکا با Constraint/Trigger** · **بدون ایجاد Business Workflow**.
- **محل ثبت:** `docs/V1.8_DETAILED_SCHEMA_DESIGN.md §۶.۸` (جدول تفکیک) · `docs/10-database-design.md §۱` (هم‌تراز شد)
- **چرا در ERROR_LOG ثبت شد:** این تنش، ریشهٔ تاریخی شکاف `C-06`/`C-07` بود — قاعده‌ای که در مستندات ثبت شد اما هرگز پیاده نشد، چون مرز «کدام لایه مسئول است» تعریف نشده بود.

### ✅ ۲. سه نقص طراحی که پیش از `M-03`/`M-08` باید رفع می‌شدند (`N-1` · `N-2`) — رفع شدند

- **تاریخ رفع:** ۱۴۰۵/۰۶/۳۱ (2026-09-22)
- `N-1` → `DEC-027` (`superseded` = وضعیت مشتق · سقف روی Active Approvalها)
- `N-2` → `DEC-028` (شاخه‌بندی `TG_OP` — فقط طراحی)
- `H-1` → `DEC-026` (حذف کوئری نامعتبر `SUM(...) FOR UPDATE`؛ قفل والد Stage)
- **مرجع:** `docs/V1.8_FINAL_DESIGN_RECONCILIATION.md`

---

## ⚠️ موارد وابسته به محیط/سرور (Environment Dependent)

### ⚠️ ۱. نبود دیتابیس PostgreSQL برای تست

- **فایل(های) مرتبط:** `phpunit.xml`, `.env`
- **وضعیت:** تست‌های این پروژه **الزاماً** باید روی PostgreSQL اجرا شوند و SQLite in-memory به دلیل استفادهٔ Migrationها از ویژگی‌های اختصاصی PostgreSQL **مجاز نیست**.
- **پیکربندی مورد نیاز:**
```text
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=tms_testing      ← در phpunit.xml
DB_USERNAME=root
```
- **نکته:** این مورد با مورد ⏳ ۱ هم‌پوشانی دارد اما به‌عنوان یک محدودیت دائمی معماری ثبت می‌شود: هر محیط توسعه یا CI باید PostgreSQL داشته باشد.

---
