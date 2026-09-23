# WBS Phase Completion Design — V1.8

**وضعیت:** DESIGN FREEZE (تأیید مالک پروژه در انتظار)
**دامنه:** مدل تکمیل WBS Phase بر مبنای Checklist و تأیید نهایی ناظر
**وابستگی:** `docs/weight-module-design-v1.8.md` (تفکیک Stage از Phase) و BD-05 تا BD-09
**⚠️ این سند طراحی است. هیچ Migration، Model یا Service ساخته نشده است.**

---

## ۱. مسئله

قاعدهٔ مردود (§۶ پرامپت):

```text
❌ All Tasks Done → Phase Completed
```

قاعدهٔ معتبر:

```text
WBS Phase
   ↓
Expected Outputs / Checklist
   ↓
Supervisor Review
   ↓
Supervisor Final Decision
   ↓
Phase Completed / Not Completed
```

**دلیل رد منطق خودکار:** یک تسک می‌تواند `approved` شود در حالی که خروجی واقعی فاز (مثلاً SRS یا ERD) تولید نشده باشد. تأیید تسک، کیفیت اجرا را می‌سنجد؛ تکمیل فاز، تحقق محدوده را.

---

## ۲. وضعیت فعلی جدول `wbs_phases`

```sql
-- database/migrations/2026_09_17_140146_create_wbs_phases_table.php
wbs_phases
─────────────────────────────────────────────────────────────────────────────
id                 BIGINT IDENTITY PK
project_id         BIGINT NOT NULL  FK → projects  ON DELETE RESTRICT
name               VARCHAR(255) NOT NULL
expected_output    TEXT NOT NULL                      ← ⚠️ یک فیلد واحد
weight             NUMERIC(5,2) NOT NULL               ← ⚠️ منتقل می‌شود (Stage)
planned_duration   INTEGER NOT NULL
duration_unit      VARCHAR(50) NOT NULL                ← ⚠️ بدون CHECK
start_date         DATE NULL
end_date           DATE NULL
sort_order         INTEGER NOT NULL DEFAULT 0
status             VARCHAR(50) NOT NULL                ← ⚠️ بدون Enum/CHECK/مصرف‌کننده
created_at         TIMESTAMPTZ NOT NULL
updated_at         TIMESTAMPTZ NOT NULL
deleted_at         TIMESTAMPTZ NULL

CONSTRAINT chk_wbs_weight            CHECK (weight >= 0 AND weight <= 100)
CONSTRAINT chk_wbs_planned_duration  CHECK (planned_duration > 0)
CONSTRAINT chk_wbs_dates             CHECK (end_date IS NULL OR start_date IS NULL OR end_date >= start_date)
INDEX (project_id)
```

### ۲.۱ تطبیق با الزامات §۵ و §۶

| الزام پرامپت | وضعیت امروز | اقدام |
|---|---|---|
| عنوان فاز | ✅ `name` | **KEEP** |
| تاریخ شروع | ✅ `start_date DATE NULL` | **KEEP** |
| تاریخ پایان | ✅ `end_date DATE NULL` | **KEEP** |
| مدت تخمینی | ✅ `planned_duration INTEGER` + `duration_unit` | **KEEP** (⚠️ `duration_unit` بدون CHECK) |
| **خروجی‌های مورد انتظار (چندگانه)** | ⚠️ `expected_output TEXT` — یک فیلد | **CHANGE REQUIRED** |
| **Checklist با وضعیت هر مورد** | 🚫 هیچ | **CHANGE REQUIRED** |
| وضعیت تکمیل | ⚠️ `status VARCHAR(50)` — رشتهٔ آزاد، صفر مصرف‌کننده | **CHANGE REQUIRED** |
| وضعیت تأیید | 🚫 هیچ فیلدی | **CHANGE REQUIRED** |
| نظر ناظر | 🚫 هیچ فیلدی | **CHANGE REQUIRED** |
| تأیید نهایی ناظر (کاربر + تاریخ) | 🚫 هیچ فیلدی | **CHANGE REQUIRED** |
| سابقه تغییر | ⚠️ `activity_logs` وجود دارد اما نویسنده‌اش مرده | **CHANGE REQUIRED** |
| وزن فاز | ⚠️ `weight` — در مدل جدید به Stage منتقل می‌شود | **DEPRECATE** |

### ۲.۲ شکاف کلیدی: یک فیلد TEXT برای یک Checklist

مثال §۵ پرامپت:

```text
Phase 1
عنوان: تحلیل، طراحی، معماری
Expected Outputs:
  □ SRS                □ ERD               □ DFD
  □ UI/UX Prototype    □ Technical Stack   □ Schedule
  □ Documentation      □ Git Delivery
```

هشت مورد، هر یک با وضعیت مستقل `Completed / Incomplete`. یک ستون `TEXT` نمی‌تواند این را حمل کند — نه وضعیت هر مورد، نه «چه کسی و چه زمانی آن را کامل کرد».

**تنها ارجاع به `expected_output` در کل کد:** `database/seeders/InitialTmsSeeder.php:121` با مقدار `'مستندات معماری'`. یعنی این ستون امروز **هیچ مصرف‌کننده‌ای** ندارد (نه Controller، نه View، نه Service، نه تست).

---

## ۳. طراحی پیشنهادی

### ۳.۱ موجودیت Checklist

```sql
wbs_phase_outputs
─────────────────────────────────────────────────────────────────────────────
id                BIGINT IDENTITY PK
wbs_phase_id      BIGINT NOT NULL  FK → wbs_phases  ON DELETE RESTRICT
title             VARCHAR(255) NOT NULL        -- "SRS", "ERD", "DFD", ...
description       TEXT NULL
is_completed      BOOLEAN NOT NULL DEFAULT false
completed_at      TIMESTAMPTZ NULL
completed_by      BIGINT NULL  FK → users   ON DELETE RESTRICT
sort_order        INTEGER NOT NULL DEFAULT 0
created_at        TIMESTAMPTZ NOT NULL
updated_at        TIMESTAMPTZ NOT NULL

INDEX (wbs_phase_id)
INDEX (wbs_phase_id, is_completed)

CONSTRAINT chk_wpo_completion_consistency
  CHECK (
    (is_completed = false AND completed_at IS NULL AND completed_by IS NULL)
    OR
    (is_completed = true  AND completed_at IS NOT NULL AND completed_by IS NOT NULL)
  )
```

**الگوی نام‌گذاری:** مطابق `wbs_phases`، `wbs_phase_outputs`. مطابق سبک Repository (`chk_wbs_*` → `chk_wpo_*`).

**چرا `completed_by` و `completed_at` لازم‌اند:** الزام BD-09 («تأیید ناظر باید تاریخ، کاربر و سابقه تغییر داشته باشد»). حتی اگر ناظر خودش موارد را تیک بزند، باید مشخص باشد که چه کسی و چه زمانی.

### ۳.۲ فیلدهای تأیید نهایی روی `wbs_phases`

```sql
ALTER TABLE wbs_phases ADD COLUMN
  completion_status       VARCHAR(50) NOT NULL DEFAULT 'pending',
  -- pending | confirmed | rejected

  supervisor_comment      TEXT NULL,
  supervisor_confirmed_at TIMESTAMPTZ NULL,
  supervisor_confirmed_by BIGINT NULL  FK → users ON DELETE RESTRICT;

ALTER TABLE wbs_phases ADD CONSTRAINT chk_wbs_completion_consistency
  CHECK (
    (completion_status = 'pending' AND supervisor_confirmed_at IS NULL AND supervisor_confirmed_by IS NULL)
    OR
    (completion_status <> 'pending' AND supervisor_confirmed_at IS NOT NULL AND supervisor_confirmed_by IS NOT NULL)
  );
```

**الگوی موجود Repository:** این دقیقاً همان الگویی است که فاز V1.3 برای `tasks` به کار برد:

```php
// database/migrations/2026_09_19_055251_add_supervisor_approved_status_to_tasks.php
Schema::table('tasks', function (Blueprint $table) {
    $table->timestampTz('supervisor_approved_at')->nullable()->after('submitted_at');
});
```

پس سبک `*_at` + `*_by` **از قبل در Repository تثبیت شده** و نیازی به طراحی تازه ندارد.

### ۳.۳ Enum پیشنهادی

```php
App\Domain\Enums\WbsPhaseCompletionStatus: string
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case Rejected  = 'rejected';
```

الگو: هم‌خوان با ۸ Enum موجود در `app/Domain/Enums/`. مقادیر در ستون به‌صورت `VARCHAR(50)` ذخیره شوند (مثل `tasks.status`) و اعتبارسنجی در لایه PHP انجام شود — نه `CHECK IN (...)`, تا افزودن وضعیت جدید نیازمند Migration نباشد.

---

## ۴. جریان تکمیل

```text
        ┌──────────────────────────────────────┐
        │  wbs_phases (completion_status =     │
        │              'pending')              │
        └────────────────┬─────────────────────┘
                         │
        ┌────────────────▼─────────────────────┐
        │  ناظر / مجری موارد Checklist را      │
        │  بررسی و تیک می‌زند                   │
        │  → wbs_phase_outputs.is_completed    │
        └────────────────┬─────────────────────┘
                         │
        ┌────────────────▼─────────────────────┐
        │  SUPERVISOR REVIEW                   │
        │  (تأیید نهایی انحصاری ناظر — BD-08)  │
        └────────────────┬─────────────────────┘
                         │
            ┌────────────┴────────────┐
            │                         │
            ▼                         ▼
   completion_status          completion_status
      = 'confirmed'              = 'rejected'
   supervisor_confirmed_at    + supervisor_comment
   supervisor_confirmed_by        (دلیل رد)
            │
            ▼
      activity_logs:
      wbs_phase_completion_confirmed
```

### ۴.۱ نکتهٔ حیاتی — استقلال از Task

> **هیچ‌کدام از وضعیت‌های `tasks` یا `approvals` نباید در تعیین تکمیل فاز دخالت کند.**

یک فاز می‌تواند با ۱۰ تسک approved، **تأیید نشود** — اگر خروجی‌های اعلامی تحویل نشده باشد.
یک فاز می‌تواند با صفر تسک، **تأیید شود** — اگر Work خارج از سیستم انجام و مستند شده باشد.

این استقلال باید در UI صریح نوشته شود و با یک تست رگرسیون محافظت شود (تست #۱۰ در بخش ۶).

### ۴.۲ رابطه با Progress

**⚠️ وابسته به `OQ-10`.** طراحی فعلی بر این فرض است که تأیید تکمیل WBS Phase **هیچ اثری بر Progress ندارد** — چون Progress فقط از `stage_progress_approvals` می‌آید (BD-01، BD-11). این فرض باید توسط مالک پروژه تأیید شود.

---

## ۵. تأثیر روی کد موجود

### ۵.۱ `wbs_phases.weight` — DEPRECATE

وزن فاز به `module_stages.weight` منتقل می‌شود. دو گزینه:

| گزینه | توضیح | ریسک |
|---|---|---|
| **(الف) حفظ ستون به‌عنوان Legacy** | `weight` باقی می‌ماند، اما هیچ سرویسی آن را نمی‌خواند و هیچ View نمایش نمی‌دهد | 🟢 صفر |
| **(ب) حذف ستون** | نیازمند `DROP COLUMN` | 🔴 نیازمند تأیید صریح (`AGENTS.md` Rule 3) |

**توصیه:** گزینهٔ (الف) در V1.8، گزینهٔ (ب) در فاز بعد پس از اثبات نبود خواننده.

**نکتهٔ تسهیل‌کننده:** امروز `weight` **صفر مصرف‌کننده** دارد (grep تأیید کرد: تنها ارجاع، `WbsPhase::$casts['weight']` است). پس انتقال آن **بدون شکستن هیچ چیز** ممکن است.

### ۵.۲ `wbs_phases.expected_output` — CHANGE REQUIRED

| گام | اقدام |
|---|---|
| ۱ | جدول `wbs_phase_outputs` ساخته شود |
| ۲ | مقدار `expected_output` موجود برای هر فاز، به **یک** ردیف در جدول جدید تبدیل شود |
| ۳ | ستون `expected_output` حفظ شود (Legacy) تا داده تاریخی از دست نرود |
| ۴ | در فاز بعد، در صورت نیاز، حذف شود (نیازمند تأیید) |

`expected_output` امروز **NOT NULL** است. پس هر فاز جدید باید همچنان این مقدار را داشته باشد تا زمانی که ستون حذف شود — یا مقدار خالی (`''`) داده شود. این یک نکتهٔ عملیاتی مهم است.

### ۵.۳ `wbs_phases.status` — CHANGE REQUIRED

امروز `VARCHAR(50)` آزاد، بدون Enum، بدون CHECK، **صفر مصرف‌کننده** در کد. تعارض مفهومی با `completion_status` جدید:

| ستون | معنا |
|---|---|
| `status` (موجود) | وضعیت **اجرای** فاز (active / inactive / ...) |
| `completion_status` (جدید) | وضعیت **تکمیل و تأیید** فاز |

**توصیه:** دو ستون حفظ شوند با معنای متفاوت، و `status` به یک Enum تبدیل شود (`WbsPhaseStatus`). مقدار امروز در Seeder: `'active'`.

### ۵.۴ `wbs_phases.duration_unit` — CHANGE REQUIRED

`VARCHAR(50)` آزاد. Enum `DurationUnit` (`days`/`weeks`/`months`) **وجود دارد اما هیچ‌جا استفاده نمی‌شود** (`app/Domain/Enums/DurationUnit.php`). Seeder مقدار `'day'` می‌دهد که **با هیچ‌کدام از مقادیر Enum مطابق نیست** (`days` با `s`).

**این یک ناسازگاری دادهٔ فعلی است** و باید در فاز پیاده‌سازی تصمیم‌گیری شود: یا Enum به `day`/`week`/`month` تغییر کند، یا دادهٔ Seeder اصلاح شود.

### ۵.۵ لایه وب — کاملاً غایب

| مورد | وضعیت |
|---|---|
| `ProjectController` | 🚫 وجود ندارد |
| `WbsPhaseController` | 🚫 وجود ندارد |
| Route برای Project/WBS | 🚫 وجود ندارد |
| View برای Project/WBS | 🚫 وجود ندارد |
| تنها دسترسی کاربر | یک `<select>` برای `wbs_phase_id` در `tasks/create.blade.php:48-53` |

**نتیجه:** مدیریت WBS Phase امروز **صفر UI** دارد. کل چرخهٔ «تعریف فاز → تیک Checklist → تأیید ناظر» از صفر باید ساخته شود.

---

## ۶. تست‌های لازم

| # | سناریو | مرجع |
|---|---|---|
| ۱ | تأیید نهایی تکمیل فاز توسط ناظر → `completion_status = 'confirmed'` | BD-08 |
| ۲ | رد شدن → `completion_status = 'rejected'` + `supervisor_comment` | BD-08 |
| ۳ | **منطق مردود:** همهٔ تسک‌های فاز `approved` شوند اما Phase **تأیید نشود** | BD-07 |
| ۴ | **عکس ۳:** فاز با صفر تسک، توسط ناظر تأیید شود | BD-07 |
| ۵ | Checklist: تغییر وضعیت هر Output مستقل + ثبت `completed_by`/`completed_at` | BD-06 |
| ۶ | `chk_wpo_completion_consistency`: Output با `is_completed = true` و `completed_at = NULL` رد شود | بخش ۳.۱ |
| ۷ | `chk_wbs_completion_consistency`: `completion_status = 'confirmed'` بدون `supervisor_confirmed_by` رد شود | بخش ۳.۲ |
| ۸ | Audit: `wbs_phase_completion_confirmed` در `activity_logs` ثبت شود | BD-09 |
| ۹ | کارخانه/Seeder: تولید فاز با ۸ Output پیش‌فرض (SRS, ERD, DFD, …) | §۵ |
| ۱۰ | **رگرسیون استقلال:** تأیید فاز بر `stage_progress_approvals` اثری ندارد (اگر `OQ-10` گزینهٔ الف) | بخش ۴.۲ |

---

## ۷. پرسش‌های باز

| ID | پرسش |
|---|---|
| `OQ-03` | رابطهٔ WBS Phase ↔ Module — آیا فاز به ماژول وصل می‌شود؟ |
| `OQ-10` | آیا تکمیل WBS Phase بر Progress اثری دارد؟ (فرض طراحی: خیر) |
| `OQ-12` | Morph Map — پیش از اتصال Document به `wbs_phase_outputs` |
| `OQ-13` | 🆕 مصوب نشده: آیا Checklist قابل تعریف آزاد توسط کاربر است یا از یک کاتالوگ ثابت می‌آید؟ |
| `OQ-14` | 🆕 مصوب نشده: آیا Completion یک فاز می‌تواند بعداً **بازگشایی** شود؟ |
| `OQ-15` | 🆕 مصوب نشده: `duration_unit` — اصلاح Enum یا اصلاح دادهٔ موجود؟ |

---

## ۸. خلاصهٔ اقدامات لازم (پس از تأیید)

```text
🆕 CREATE TABLE  wbs_phase_outputs
🆕 Enum         WbsPhaseCompletionStatus
🆕 Enum         WbsPhaseStatus                       ← برای status موجود
🔧 ALTER        wbs_phases + completion_status / supervisor_comment
                             + supervisor_confirmed_at / supervisor_confirmed_by
🔧 ALTER        wbs_phases CONSTRAINT chk_wbs_completion_consistency
🟡 DEPRECATE    wbs_phases.weight                    ← منتقل به module_stages
🟡 DEPRECATE    wbs_phases.expected_output            ← منتقل به wbs_phase_outputs
🔧 CHANGE       wbs_phases.status → Enum
🔧 CHANGE       wbs_phases.duration_unit → Enum
🆕 Service      WbsPhaseCompletionService
🆕 Service      DatabaseAuditService                  ← پیش‌نیاز BD-09
🆕 Web          ProjectController + WbsPhaseController + WbsPhaseOutputController
                + Route + View (امروز کاملاً غایب)
```

---

**END OF DOCUMENT**
