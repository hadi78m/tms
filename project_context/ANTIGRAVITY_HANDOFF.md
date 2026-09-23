# Project Handoff

> **آخرین به‌روزرسانی:** ۱۴۰۵/۰۶/۳۱ (2026-09-22) — **V1.8 Final Hardening (T-3/T-4/T-5/T-7)**
> **حکم جاری:** `OWNER DECISION REQUIRED` (T-3 · T-5 · T-7 · بازتأیید T-1)

## وضعیت فعلی

**تمام موارد فنی V1.8 بسته یا صریحاً به مالک ارجاع شد.** آخرین وضعیت:

```text
Tests .................. 203 passed · 2 deprecated · 591 assertions · 0 failed
Migrations ............. 32/32 Ran · صفر تغییر در فایل‌های Migration
tms / tms_testing ...... دست‌نخورده در آخرین فاز

Bسته‌شده فنی ........... T-4 (صفر مغایرت رفتاری) · R-3 · T-6 · R-1 · R-2 · T-1b
نیازمند مالک ........... T-3 (رویداد task_rejected: A/B) · T-5 (قاعدهٔ حذف نرم Project) · T-7 (ساخت CI؟) · T-1 (M-10؟)
```

### آخرین فاز — Final Hardening (2026-09-22)

- **صفر تغییر کد** — هیچ‌کدام از چهار item مجوز پیاده‌سازی مستقل نداشت؛ همه یا بسته شدند یا صریحاً به مالک ارجاع شدند.
- **T-3:** بازرسی ۴ مسیر رد ⇒ صفر audit bypass؛ گزینه‌های A/B بی‌طرف ثبت شد (هر دو بدون Migration).
- **T-4:** تطبیق سه‌گانهٔ migrations ↔ docs ↔ catalog — بسته.
- **T-5:** DB protection موجود؛ قاعدهٔ soft-delete مفقود و هیچ مسیر حذف Project هم نیست.
- **T-7:** توصیهٔ implementation-ready CI آماده شد (شامل الزام `tms_testing` و سد TestCase).
- مرجع: `docs/V1.8_FINAL_HARDENING_T3_T4_T5_T7.md`

## وضعیت فعلی

**V1.8 کاملاً بسته شد** (Production Migration + Code Hardening + Remaining Hardening). آخرین وضعیت:

```text
Tests .................. 203 passed · 2 deprecated · 591 assertions · 0 failed
Migrations ............. 32/32 Ran · صفر تغییر در فایل‌های Migration
tms / tms_testing ...... دست‌نخورده در آخرین فاز
```

### آخرین فاز — R-3 + T-6 (2026-09-22)

- **R-3 بسته شد:** smallest safe refactor — `ModuleStage::approvedQuery()` و `StageProgressApprovalService::isSuperseded()` به تعریف واحد روی مدل واگذار شدند (کپی حرفِی قبلی). رفتار با Suite برابر Baseline اثبات شد. `Module::scopeActive()` عمداً جدا ماند (naming collision، مفهوم مستقل).
- **T-6 بسته شد:** ENFORCED Safety Gate در `tests/TestCase.php` (نام واقعی دیتابیس متصل باید به `testing` ختم شود، وگرنه Suite پیش از RefreshDatabase fail می‌شود) + DOCUMENTED gate در `docs/DATABASE_SAFETY.md`.
- **باز برای مالک:** `T-3` (رویداد `task_rejected` — OWNER DECISION REQUIRED) · `T-1` (UNIQUE در DB / M-10 — فعلاً ACCEPTED AS-IS) · `T-4` · `T-5` · `T-7` (deferred).
- مراجع: `docs/V1.8_REMAINING_HARDENING_TECHNICAL_REVIEW.md` · `docs/V1.8_REMAINING_HARDENING_IMPLEMENTATION.md`

## وضعیت فعلی

**V1.8 به‌طور کامل بسته شد:** Production Migration اجرا شده (۳۲ مهاجرت · ۳۵ جدول) و Hardening لایهٔ Application انجام شد. آخرین وضعیت:

```text
Tests .................. 203 passed · 2 deprecated · 591 assertions · 0 failed
Migrations ............. 32/32 Ran · Batch {1:23, 2:1, 3:8} · صفر تغییر در فایل‌های Migration
tms .................... دست‌نخورده در این فاز (فقط migrate:status و db:show)
```

### آخرین فاز — V1.8 Post-Migration Code Hardening (2026-09-22)

- **۴ تصمیم مالک** گرفته و ثبت شد: `DEC-036` (`R-1`: `R1-D` NULL وزن در مسیر عادی ممنوع · `R1-F1` weight اجباری در Create Task — گزارش‌ها دست‌نخوردند) و `DEC-037` (`R-2`: `T-2-A` task_type **اجباری** در FormRequest با Enum validation · `T-2-UI-A` فیلد «نوع تسک» در فرم).
- **پیاده‌سازی:** `StoreTaskRequest` · `CreateTaskData` · `TaskService::create` · `tasks/create.blade.php` — مسیر کامل Create Task بدون silent fallback؛ `DEFAULT 'development'` دیتابیس دیگر هرگز جایگزین Business Logic نیست (`BD-06` عملیاتی شد).
- **R-3:** فقط Audit — ۵ تعریف «Active» همه هم‌ارز رفتاری (`STRUCTURALLY DUPLICATED BUT BEHAVIORALLY CONSISTENT`)؛ Refactor عمداً انجام نشد و همچنان `RECOMMENDATION — NOT APPROVED` است.
- **T-1:** رگرسیون تأیید شد — گاردها و وضعیت UNIQUE دست‌نخورده.
- مرجع کامل: `docs/V1.8_POST_MIGRATION_CODE_HARDENING_REPORT.md`

## وضعیت فعلی

هفت فاز متوالی **فقط مستندسازی / بازبینی خواندنی** به پایان رسید:

1. **V1.8 Design Freeze** — ممیزی و طراحی مدل Weight / Module / WBS.
2. **V1.8 Final Reconciliation** — تطبیق تصمیمات با سابقهٔ Repository.
3. **V1.8 Detailed Schema Design** — طراحی تفصیلی Schema نهایی.
4. **V1.8 Migration Review Inputs Closed** — بستن سه پرسش 🔴 + ساخت چک‌لیست Review.
5. **V1.8 Migration Review** — اجرای چک‌لیست (۳۸ بند در ۵ محور) + بازبینی مستقیم Repository، محیط و بیس تست → `BLOCKED — REVIEW ISSUE`.
6. **V1.8 OQ & Review Issue Resolution** — تحلیل خواندنی پنج پرسش ساختاری + `H-1`..`H-7` + کشف `N-1`..`N-5` → `BLOCKED — OWNER DECISION REQUIRED`.
7. **V1.8 Owner Decision Closure & Design Reconciliation** 🆕 — پاسخ مالک پروژه به **تمام** پرسش‌های باز، ثبت ۱۶ تصمیم (`DEC-020`..`DEC-035`) و **اعمال اصلاحات طراحی**.

- 🚫 **صفر تغییر کد** — هیچ Migration، Model، Service، Controller، Request، Route، Blade، Test یا Seeder ایجاد یا تغییر نکرد.
- 🚫 **صفر Migration نوشته/اجرا · صفر SQL mutation · صفر تغییر Schema · صفر تغییر داده.**
- ✅ **هیچ پرسش کسب‌وکاری بازی باقی نمانده است** — `BLOCKER B-1` (۵ تصمیم ساختاری) رفع شد.
- ✅ محیط تأییدشده: PostgreSQL 18.6 · `tms` و `tms_testing` موجود · اتصال Laravel تأییدشده · ۲۳/۲۳ Migration.
- ✅ محورهای Schema · Domain rules · Legacy · Audit → **APPROVED (Design)** · Environment → **۴ بند PASS**.

```text
وضعیت دروازه:
READY FOR MIGRATION IMPLEMENTATION REVIEW
```

> ⚠️ `READY TO EXECUTE MIGRATIONS` **اعلام نشده** — Migration Design و Migration Implementation هر دو فازهای جداگانهٔ بعدی‌اند و مجوز جداگانه می‌خواهند.

## آخرین مرحله تکمیل‌شده — V1.8 Owner Decision Closure

**گزارش:** `docs/V1.8_FINAL_DESIGN_RECONCILIATION.md` (۱۲ بخش) · **تصمیمات:** `DEC-020`..`DEC-035`.

### تصمیمات کلیدی مالک پروژه

| مورد | تصمیم | اثر | DEC |
|---|---|---|---|
| `OQ-30` — Trigger | **گزینهٔ D** | ۳ Trigger **تک‌ردیفی** ساخته می‌شود؛ ۳ Trigger تجمعی **DEFERRED** | `DEC-020` |
| `OQ-24` — `tasks.module_id` | **گزینهٔ A** | باقی می‌ماند؛ هم‌خوانی در **Service**؛ بدون Trigger | `DEC-021` |
| `H-3` — اتمیک‌بودن Module | یک عملیات = یک تراکنش | صفر ماژول معتبر؛ وضعیت میانی غیرقابل Commit | `DEC-022` |
| `H-4` — Write Skew | **دکترین قفل والد** | `projects.id` / `modules.id` / `module_stages.id` | `DEC-023` |
| `H-5` — صفر ماژول | **معتبر** | invariant روی مجموعهٔ Active | `DEC-024` |
| `N-4` — Service vs DB | Service مالک Business Rule؛ DB مالک Integrity تک‌ردیفی | جدول دکترین در §۶.۸ سند Schema | `DEC-025` |
| `H-1` — سقف تجمعی | حذف `SUM(...) FOR UPDATE` | قفل والد Stage + تجمیع بدون `FOR UPDATE` | `DEC-026` |
| `N-1` — `supersede` | **`superseded` وضعیت مشتق است** | دامنهٔ ذخیره‌شده: `pending/approved/rejected` · سقف روی Active Approval | `DEC-027` |
| `N-2` — Trigger Function | شاخه‌بندی `TG_OP` | فقط مستندسازی — Trigger تجمعی ساخته نمی‌شود | `DEC-028` |
| `OQ-31` — حفاظت از History | ✅ RESOLVED | Trigger تک‌ردیفی روی `stage_progress_approvals` + `activity_logs` | `DEC-029` |
| `OQ-25` — `$metadata` | ✅ RESOLVED | `reason` → ستون `reason`؛ باقی → `new_values['metadata']` | `DEC-030` |
| `OQ-02` — `modules.code` | ✅ RESOLVED | Unique مرکب حفظ؛ Partial Index اضافه نمی‌شود | `DEC-031` |
| `H-6` — `document_uploaded` | باید ساخته شود | Audit صریح در Service؛ بدون Observer | `DEC-032` |
| Legacy | طبقه‌بندی حفظ می‌شود | هیچ حذفی در V1.8 | `DEC-033` |
| Business Truth | مدل نهایی تثبیت | Checklist بدون Weight · Task بدون Progress | `DEC-034` |
| دروازه | `READY FOR MIGRATION IMPLEMENTATION REVIEW` | **مجوز اجرا نیست** | `DEC-035` |

### اصلاحات اعمال‌شده در سند Schema

`§۲.۵` (جدول تصمیمات) · `§۴.۱` (`OQ-02`) · `§۴.۳` (Active Approval) · `§۵.۲` (Soft Delete) · `§۶.۱`–`§۶.۸` (بازنویسی کامل راهبرد تجمعی + دکترین) · `§۸.۲` (`metadata`) · `§۸.۳` (`document_uploaded`) · `§۹.۱`/`§۹.۲` (`M-07` Batch · `M-08` محدود) · `§۹.۴` (محیط ✅) · `§۱۲` (`H-2`) · `§۱۴` (همه بسته) · `§۱۵` (دروازه) · `پیوست د`.

### 🆕 Triggerهای `M-08` (همه تک‌ردیفی)

```text
trg_module_stages_weight_locked   BEFORE UPDATE  · module_stages
trg_spa_immutable                 BEFORE UPDATE  · stage_progress_approvals
trg_spa_no_delete                 BEFORE DELETE  · stage_progress_approvals
trg_activity_logs_no_delete       BEFORE DELETE  · activity_logs
```

### ⏸️ سه Trigger تجمعی که **DEFERRED** شدند

```text
trg_modules_weight_sum · trg_module_stages_weight_sum · trg_spa_capacity
```
طراحی اصلاح‌شده (با شاخه‌بندی `TG_OP`) در §۶.۱ و §۶.۲ سند Schema **مستند** شده است — **تحلیل ایستا، با اجرا تأیید نشده**. قواعد تجمعی فعلاً فقط با Service + Transaction + قفل والد تضمین می‌شوند.

### 📦 تفکیک Batch (حل `H-2`)

```text
Batch مستقل و قدیمی‌تر:  M-07  (چون down() آگاهانه throw می‌کند)
Batch اصلی V1.8:          M-01 · M-02 · M-03 · M-04 · M-05 · M-06 · M-08 · M-09
```

## مرحله در حال انجام

**V1.8 Migration Implementation Review** — بازبینی طرح پیاده‌سازی ۹ فایل Migration.

### ✅ پرسش‌های باز: صفر

| دسته | وضعیت |
|---|---|
| تصمیمات کسب‌وکاری مسدودکننده | **صفر** |
| `OWNER DECISION REQUIRED` | **صفر** (هر ۶ مورد پاسخ گرفت) |
| `RECOMMENDATION READY` | **صفر** (هر ۹ مورد تأیید و اعمال شد) |
| `BLOCKER` | **صفر** (`B-1` رفع شد) |
| پرسش‌های 🟢 موکول | `OQ-19` · `OQ-20` · `OQ-21` · `OQ-23` · `OQ-26` · `OQ-32` — غیرمسدودکننده |

### 📌 یادداشت‌های فنی برای Migration Implementation Review (بدون تصمیم کسب‌وکار)

| # | موضوع | پیشنهاد |
|---|---|---|
| `T-1` | امکان supersede دوبارهٔ یک رکورد (دو «برگ» → دوباره‌شماری) | افزودن `UNIQUE` روی `supersedes_approval_id` — **اعمال نشد** |
| `T-2` | `task_type NOT NULL DEFAULT 'development'` ماندگار → خطر طبقه‌بندی خاموش | حفظ Default برای Backfill، سپس `DROP DEFAULT` |
| `T-3` | نبود رویداد اختصاصی `task_rejected` | تصمیم در V1.8a |
| `T-4` | ناهم‌خوانی سبک `CHECK IN` بین `stage_code` و `task_type` | یکسان‌سازی الگو |
| `T-5` | Soft Delete یک Project، Invariant ماژول‌ها را تعلیق می‌کند | در V1.8 مسیر کدی ندارد |
| `T-6` | بازتأیید بیس تست کامل | نیازمند **مجوز صریح** — اقدام مخرب روی `tms_testing` |
| `T-7` | `phpunit.xml` فاقد `DB_USERNAME`/`DB_PASSWORD` | تأمین در محیط CI |

## آخرین تغییرات

**فقط فایل‌های `*.md`:**

```text
docs/V1.8_FINAL_DESIGN_RECONCILIATION.md      🆕 (گزارش ۱۲ بخشی)
docs/V1.8_DETAILED_SCHEMA_DESIGN.md            🔧 (۱۶ نقطهٔ اصلاحی + پیوست د)
docs/V1.8_OQ_AND_REVIEW_ISSUE_RESOLUTION.md    🔧 (بنر بسته‌شدن + جدول ۱۷ مورد)
docs/V1.8_MIGRATION_REVIEW_CHECKLIST.md        🔧 (همهٔ بندها APPROVED/RESOLVED)
docs/10-database-design.md                     🔧 (دکترین · supersede · ریسک‌های جدید · دروازه)
project_context/ANTIGRAVITY_DECISIONS.md       🔧 (DEC-020..DEC-035)
project_context/ANTIGRAVITY_HANDOFF.md         🔧 (بازنویسی کامل)
project_context/tasks.md                       🔧
project_context/ANTIGRAVITY_CHANGELOG.md       🔧
project_context/ANTIGRAVITY_SESSION_LOG.md     🔧
MEMORY.md · ACTION_TRACKER.md                  🔧
```

> ℹ️ `TMS_PROJECT_TRACKER.md` طبق قاعدهٔ تفکیک نقش‌ها (Architect/Manager در برابر Developer) در این مرحله تغییر **نکرد**.

## فایل‌های مهم برای مطالعه

- **`docs/V1.8_FINAL_DESIGN_RECONCILIATION.md`** ← **نقطهٔ شروع قطعی مرحلهٔ بعد**
- `docs/V1.8_DETAILED_SCHEMA_DESIGN.md` ← طراحی کامل (بخش ۶ راهبرد Constraints و دکترین · ۹ Migrationها · ۱۵ دروازه)
- `docs/V1.8_MIGRATION_REVIEW_CHECKLIST.md` · `docs/V1.8_MIGRATION_REVIEW_REPORT.md` · `docs/V1.8_OQ_AND_REVIEW_ISSUE_RESOLUTION.md`
- `project_context/ANTIGRAVITY_DECISIONS.md` — `DEC-020`..`DEC-035` تازه‌ترین‌اند
- `docs/10-database-design.md` — نمای Schema + دکترین
- `AGENTS.md` · `MEMORY.md` · `ERROR_LOG.md`

## مشکلات باز

### 🔧 مسدودکنندهٔ پیاده‌سازی (نه طراحی)

1. **Audit Trail مرده است:** `AppServiceProvider:19-22` رابط `AuditServiceInterface` را به
   `NullAuditService` بایند می‌کند و `log()` آن بدون `save()` برمی‌گردد. **`DatabaseAuditService` وجود ندارد.**
   **۵ سرویس دامنه** این رابط را تزریق می‌کنند و فراخوانی‌هایشان دور ریخته می‌شود.
   **گام صفر پیاده‌سازی** (`DEC-011`) + نگاشت `metadata` (`DEC-030`) + افزودن `document_uploaded` (`DEC-032`).
2. **بازتأیید بیس تست کامل انجام نشده:** `Feature` نیازمند `RefreshDatabase` است که روی PostgreSQL معادل
   `migrate:fresh` روی `tms_testing` می‌شود → **اقدام مخربِ مستقل با مجوز جداگانه**.
   بیس «۱۱۴ تست / ۳۳۴ assertion» **UNVERIFIED** است — نه PASS و نه FAIL.
   ✅ اجرای واقعی `--testsuite=Unit` → **۱۶ passed · ۱ deprecated · ۵۱ assertions**.

> ✅ **تصمیمات کسب‌وکاری مسدودکننده: صفر.**
> ⚠️ **ریسک باقی‌ماندهٔ آگاهانه (SR-08):** قواعد تجمعی محافظت دیتابیسی در برابر نوشتن خام ندارند —
> پذیرفته‌شده چون Triggerهای تجمعی امن‌تر نیستند (`H-3`/`H-4`/`H-5`) و سه لایهٔ کنترلی دیگر برقرارند.

### 🟠 غیرمسدودکننده

- `tasks.weight` — DEPRECATED؛ ۴ Blade + ۲ KPI + ۲ CSV + ۱۳ تست وابسته؛ حذف فقط در V1.9 با تأیید کتبی.
- `wbs_phases.weight` — LEGACY؛ صفر خواننده، صفر تست.
- `lock_weight` — DEPRECATED؛ **یک مصرف‌کنندهٔ UI دارد** (`settings/index.blade.php`) → علامت «منسوخ» کار V1.8b است.
- WBS امروز **صفر CRUD/Route/View** دارد.
- باگ موجود: وضعیت `'completed'` نامعتبر در `DashboardController:22` و `ReportController:18`.
- `SettingsService` صفر مصرف‌کنندهٔ واقعی دارد.
- ناسازگاری `duration_unit`: Seeder مقدار `'day'` می‌دهد، Enum مقدار `'days'` (`OQ-15`).

## اقدام بعدی دقیق (تاریخی — همهٔ گام‌ها انجام شدند)

**گام ۱ — Migration Implementation Review (Human):** ✅ انجام شد — هر ۱۵ گیت ایمنی PASS شد.

**گام ۲ — Migration Design:** ✅ انجام شد — ۹ فایل Migration نوشته شد (`M-07` جدا از هشت مهاجرت دیگر).

**گام ۳ — مجوز صریح Migration Implementation:** ✅ انجام شد — `M-07` در Batch 2، سپس هشت مهاجرت در Batch 3 روی `tms`.

**گام ۴ — `DatabaseAuditService`** ✅ ساخته شد و در `AppServiceProvider` بایند شد (`DEC-011` · `DEC-030` · `DEC-032`).

**⚠️ ممنوعیت‌های باقی‌مانده:**
هیچ Migration پیش از پایان Migration Implementation Review نوشته نشود.
هیچ Migrationی بدون مجوز صریح اجرا نشود.
هیچ `DROP TABLE` / `TRUNCATE` / `migrate:fresh` / `DROP COLUMN` / `RENAME COLUMN` اجرا نشود.
`Weight` به Task بازنگردد · `WBS Phase` و `Module Stage` ادغام نشوند.
CHECK جعلی برای قواعد چند-ردیفی ساخته نشود · Source of Truth دوم ساخته نشود.
وضعیت محیطی/تست با حدس یا fallback به SQLite به PASS تبدیل نشود.

## دستور پیشنهادی برای عامل بعدی

> «سلام! مرحلهٔ **Owner Decision Closure & Design Reconciliation** فاز V1.8 به پایان رسیده است.
> **هیچ کدی تغییر نکرده، هیچ Migrationی نوشته یا اجرا نشده، و هیچ SQL/داده‌ای تغییر نکرده.**
> دروازه: `PRODUCTION MIGRATION SUCCESSFUL` — `tms` اکنون روی **۳۲ مهاجرت** است.
>
> **اول این را بخوانید:** `docs/V1.8_FINAL_DESIGN_RECONCILIATION.md` — و سپس بخش ۶ سند Schema
> (راهبرد تجمعی + دکترین `§۶.۸`) · بخش ۹ (ترتیب و Batchبندی) · بخش ۱۲ (Rollback).
>
> **خلاصه:** هر ۶ پرسش `OWNER DECISION REQUIRED` پاسخ گرفت و ۹ توصیهٔ فنی اعمال شد.
> مهم‌ترین تغییرات: `superseded` از وضعیت ذخیره‌شده به **وضعیت مشتق** تبدیل شد (`DEC-027`) ·
> سقف تجمعی حالا با **قفل والد Stage** تضمین می‌شود (`H-1`) · Triggerهای تجمعی **DEFERRED** شدند (`OQ-30 = D`) ·
> `M-07` در **Batch مستقل** اجرا می‌شود (`H-2`).
>
> **دو نکتهٔ محیطی:**
> ۱. `DatabaseAuditService` وجود ندارد و `AuditServiceInterface` به `NullAuditService` بایند شده — گام صفر پیاده‌سازی.
> ۲. بیس «۱۱۴ تست / ۳۳۴ assertion» **UNVERIFIED** است. برای بازتأیید، `RefreshDatabase` روی `tms_testing`
>    معادل `migrate:fresh` است → **مجوز صریح جداگانه لازم است**. هیچ نتیجه‌ای جعل نکنید.
>
> **مرجع نهایی:** `docs/V1.8_PRODUCTION_MIGRATION_REPORT.md` — مهاجرت انجام شده است؛ برای هر مهاجرت جدید، «قدیمی‌ها را دست نزن، لایهٔ افزایشی بساز» را حفظ کنید.»

---

# 🔴 وضعیت جدید — ۱۴۰۵/۰۶/۳۱ (2026-09-22): فاز Migration Implementation

مرحلهٔ **Migration Implementation (Incremental)** انجام شد. **کد نوشته شد، ۹ مهاجرت جدید ساخته شد و ۸ تا از آن‌ها واقعاً روی `tms_testing` اجرا شد.**

```text
دروازه: READY FOR PRODUCTION MIGRATION REVIEW
```

> ✅ **`ENV-1` رفع شد** (با مجوز مالک پروژه: `tms_testing` با UTF8 بازسازی شد) · کل زنجیرهٔ **۳۲/۳۲** مهاجرت اجرا شد · **کل Test Suite سبز شد: `175 passed · 2 deprecated · 504 assertions · 0 failed`** (۶۳ سناریو آن V1.8 است) · **`tms` دست‌نخورده**.

### 🔴 دو نقص واقعی که تست‌ها کشف کردند (و رفع شدند)

| ID | نقص |
|---|---|
| `BUG-1` | `ModuleStageService::rebalance()` مجموع **map** را با ۱۰۰ مقایسه می‌کرد نه مجموع **نهایی** ماژول ⇒ جابه‌جایی وزن بین دو مرحلهٔ نُه‌گانه **غیرممکن** بود |
| `BUG-2` | ردیف‌های **superseded** از «یک pending» و از مسیر تصمیم‌گیری استثنا نمی‌شدند ⇒ (الف) تصحیح یک پیشنهاد، Stage را برای همیشه قفل می‌کرد (ب) ردیف superseded می‌توانست بعداً تأیید شود |

> 📌 هر دو از **اجرای واقعی** پیدا شدند، نه بازبینی ایستا. ریشهٔ مشترک: ناهمگونی تعریف «Active».

## چه چیزی تمام شد

| مورد | وضعیت |
|---|---|
| ۲۳ مهاجرت موجود | ✅ **دست‌نخورده** (صفر تغییر) |
| ۹ مهاجرت جدید | ✅ نوشته شد · `migrate --pretend` → PASS |
| اجرا روی `tms_testing` | ✅ **۸ از ۹ DONE** · ❌ `M-09` مسدود توسط `ENV-1` |
| اجرا روی `tms` | 🚫 **انجام نشد** — `tms` دست‌نخورده |
| Triggerها | ✅ ۴ Trigger + ۳ Function — **رفتارشان با اجرای واقعی اثبات شد** |
| CHECKها | ✅ ۲۲ مورد — ۳۲ سنجهٔ رفتاری با SQL واقعی سبز شد |
| `DatabaseAuditService` | ✅ ساخته و Bind شد (`DEC-011`) |
| `document_uploaded` | ✅ اضافه شد (`DEC-032`) |
| تست‌های جدید | ✅ ۵۹ سناریو در `tests/Feature/V18/` (هنوز قابل اجرا نیستند — `ENV-1`) |
| بیس واقعی Unit | ✅ `16 passed · 1 deprecated · 51 assertions` |
| بیس Feature | ❌ **NOT RUN** — `ENV-1` |

## ✅ مانع `ENV-1` (تاریخی — رفع شد)

```text
tms          → encoding = UTF8     ✅
tms_testing  → encoding = WIN1252  ❌  (از template1 ارث برده)
```

`tms_testing` **قادر به ذخیرهٔ هیچ متن فارسی نیست**. `M-09` یک `description` فارسی می‌نویسد ⇒ هر `migrate:fresh` (که `RefreshDatabase` اجرا می‌کند) در همان نقطه می‌شکند ⇒ **کل Test Suite مسدود است**.

⚠️ `psql` روی این دیتابیس خطا **نمی‌دهد** و **mojibake** ذخیره می‌کند. **هرگز دادهٔ فارسی را با `psql` در `tms_testing` ننویسید.**

**رفع نیازمند مجوز صریح مالک پروژه است** (شامل `DROP DATABASE`):

```sql
DROP DATABASE tms_testing;
CREATE DATABASE tms_testing WITH TEMPLATE template0 ENCODING 'UTF8' LC_COLLATE 'C' LC_CTYPE 'C';
```

`tms_testing` صفر رکورد داده دارد و ماهیتاً دیتابیس تست است.

## ✅ انجام‌شده (به ترتیب)

```text
[x] ۱. رفع ENV-1 (مجوز مالک)  →  [x] ۲. DB_DATABASE=tms_testing php artisan migrate  (M-09 اجرا شد)
                                                ↓
[x] ۳. php artisan test  →  175 passed · 2 deprecated · 504 assertions · 0 failed  (۶۳ سناریو V1.8)
                                                ↓
[x] ۴. Production Migration روی tms  →  M-07 در Batch 2 · هشت مهاجرت در Batch 3  ✅
```

---

# 🟢 وضعیت نهایی — ۱۴۰۵/۰۶/۳۱ (2026-09-22): PRODUCTION MIGRATION SUCCESSFUL

```text
PRODUCTION MIGRATION SUCCESSFUL
```

`tms` با پشتیبان پیش از تغییر (`storage/app/backups/tms_pre_v18_20260922_113350.dump` · gitignored) به **۳۲ مهاجرت** مهاجرت کرد:

| سنجه | Before | After |
|---|---|---|
| migrations | 23 | **32** |
| tables | 31 | **35** |
| triggers | 0 | **4** |
| functions | 0 | **3** |
| system_settings | 4 | **5** |

**توپولوژی Batch روی `tms`:** `{1:23 (Baseline), 2:1 (M-07), 3:8}` — دقیقاً طبق `DEC-018`.

> ✅ **صفر ردیف دادهٔ موجود حذف یا بازنویسی شد.** `projects 1 · tasks 3 · wbs_phases 1 · users 8 · task_dependencies 1` همه دست‌نخورده. `tasks.weight` (20/10/10) و `wbs_phases.weight` (50) دست‌نخورده. `task_type` طبق `DEC-016` → `development` · `completion_status` طبق `DEC-017` → `pending`.
> ✅ **یکپارچگی متن فارسی با MD5 تأیید شد** (`85805816d962de31632e2103bf873447` — همان literal فایل مهاجرت).
> ✅ **صفر تغییر در ۲۳ مهاجرت قدیمی** (`git diff -- database/migrations` خالی) · صفر تغییر کد در این فاز.

### 🔍 یافتهٔ تجربی `H-2` (دقیق‌شده)

دو سناریو واقعاً روی `tms_testing` اجرا شد:

| سناریو | توپولوژی | نتیجهٔ `migrate:rollback` |
|---|---|---|
| ✅ درست | `{1:24, 2:8}` | هر ۸ مهاجرت برگشتند · `exit 0` · M-07 دست‌نخورده |
| ❌ غلط | `{1:32}` | ۸ مهاجرت برگشتند، سپس M-07 استثنا داد |

**دقت لازم:** چون M-07 کوچک‌ترین timestamp را دارد، Laravel آن را **آخر** پردازش می‌کند ⇒ ادعای «هیچ‌کدام برنمی‌گردند» نادرست است. خطر واقعی **اپراتوری** است: فرمان با Exception تمام می‌شود و اپراتور از نتیجهٔ واقعی مطمئن نمی‌شود. جداسازی Batch این ابهام را حذف می‌کند.

### 🟠 دو یادداشت باز (نیازمند تصمیم مالک)

| ID | وضعیت در این فاز | باقی‌مانده |
|---|---|---|
| `T-1` | **ACCEPTED AS-IS** (`RECOMMENDATION — NOT APPROVED`) | Master + `M-10` برای `UNIQUE(supersedes_approval_id)` |
| `T-2` | **RESOLVED BY DEC-016** (Default حذف نشد) | اجباری‌کردن `task_type` در لایهٔ Form/Service |

## اقدام بعدی (به ترتیب)

```text
۱. تصمیم `T-1` (Master + M-10 برای UNIQUE)                        ← نیازمند مجوز مالک
۲. تصمیم `T-2` (اجباری‌کردن task_type در FormRequest/Service)      ← نیازمند مجوز مالک
۳. آزمون همزمانی واقعی برای دکترین Parent-Lock (دو تراکنش موازی)   ← فنی، بدون مجوز
۴. بازتأیید ۱۷۵ تست روی کد جاری در برابر tms_testing              ← بدون مجوز
۵. فاز Feature: پرکردن Stageهای استاندارد Module                  ← نیازمند تصمیم داده‌ای
```

> ⚠️ `tms` **دیگر در baseline نیست**. هر مهاجرت جدید باید روی این وضعیت (۳۲ مهاجرت) طراحی شود.
> ⚠️ `M-07` همچنان عمداً برگشت‌ناپذیر است — `migrate:rollback` دوم روی `tms` استثنا می‌دهد و نیازمند مداخلهٔ دستی است.

**مرجع اصلی این فاز:** `docs/V1.8_PRODUCTION_MIGRATION_REPORT.md`
**مرجع زنجیرهٔ مهاجرت‌ها:** `docs/V1.8_MIGRATION_BASELINE_RECONCILIATION.md`

---

# 🟢 وضعیت نهایی‌تر — ۱۴۰۵/۰۶/۳۱ (2026-09-22): T-1 Resolution

```text
READY FOR V1.8 POST-MIGRATION CODE HARDENING
```

`T-1` با اجرای واقعی روی PostgreSQL 18.6 **اثبات و رفع** شد. بدون `UNIQUE(supersedes_approval_id)` (که مالک `ACCEPTED AS-IS` کرده) **هیچ لایه‌ای** invariant «حداکثر یک جانشین» را تضمین نمی‌کرد و `supersede()` می‌توانست به‌جای **جایگزینی**، مقدار را **اضافه** کند.

```text
A = 5 → supersede → B = 7   ⇒ 7  ✅
        → supersede دوبارهٔ A → C = 3  ⇒ 10  ❌  (باید 7 می‌ماند · سقف 15)
```

### اصلاح (یک فایل، دو خط)

```text
app/Domain/Services/StageProgressApprovalService.php
  supersede()  →  assertNotSuperseded($source)     (اصلاح T-1)
  adjust()     →  assertNotSuperseded($row)        (اصلاح T-1b — تاریخ بازنویسی می‌شد، نقض DEC-027)
```

**صفر تغییر Schema · صفر Migration جدید · صفر تصمیم کسب‌وکاری جدید.**

### تست

```text
Full suite .... 191 passed · 2 deprecated · 559 assertions · 0 failed   (از 175 → 191)
V1.8 suite .... 79 passed                                              (از 63 → 79)
جدید ......... tests/Feature/V18/StageProgressApprovalSupersedeChainTest.php (۱۶ تست)
              ← اول علیه کد اصلاح‌نشده اجرا شد و ۶ نقص واقعی را گرفت
```

### سه یافتهٔ گزارش‌شده (اصلاح نشده — منتظر تصمیم مالک)

| ID | یافته |
|---|---|
| `R-1` | `ReportController` هنوز `SUM(tasks.weight)` را گزارش و CSV می‌کند، در حالی که Schema آن را بازنشسته کرده |
| `R-2` | `task_type` هیچ نویسنده‌ای در کد ندارد ⇒ Support همیشه `development` ذخیره می‌شود (نیمهٔ `T-2`) |
| `R-3` | ۵ تعریف تکراری «Active/Superseded» — سازگار ولی شکننده · `RECOMMENDATION — NOT APPROVED` |

> ⚠️ **`migrate:rollback` دوم روی `tms` همچنان استثنا می‌دهد** (`M-07` عمداً برگشت‌ناپذیر · `DEC-018`).
> ⚠️ **`tms` روی ۳۲ مهاجرت است.** هر مهاجرت جدید باید روی همین وضعیت طراحی شود.

**مرجع اصلی:** `docs/V1.8_T1_POST_MIGRATION_RECONCILIATION.md`

