# Weight & Module Design — V1.8

**وضعیت:** DESIGN FREEZE (تأیید مالک پروژه در انتظار)
**وابستگی:** تصمیمات BD-01 تا BD-23 در `V1.8_WEIGHT_MODULE_ARCHITECTURE_AUDIT.md`
**دامنه:** مالکیت Weight، موجودیت Module، موجودیت Stage، تفکیک از WBS Phase
**⚠️ این سند طراحی است. هیچ Migration، Model یا Service ساخته نشده است.**

---

## ۱. مسیر تصمیم

از فاز V1.7 به بعد، قاعدهٔ «Weight متعلق به Task است» حاکم بود. تصمیمات قطعی V1.8 این قاعده را باطل می‌کند:

| قبل از V1.8 | بعد از V1.8 |
|---|---|
| `tasks.weight` = سهم پیشرفت تسک | Task هیچ وزن و هیچ درصد مستقلی ندارد |
| `wbs_phases.weight` = وزن فاز در Project | وزن در `modules` و `module_stages` |
| Progress از روی تسک‌های approved | Progress فقط از تأییدهای درصدی Stage |
| Support = تسکی با `wbs_phase_id = null` | Support = یک Stage استاندارد با ۱۵٪ |

---

## ۲. سلسله‌مراتب مالکیت

```text
SyncedContract                         ← Source of Truth مالی/قراردادی (بدون تغییر)
      │
      │ ۱:۱  (projects.contract_id UNIQUE — حفظ)
      ▼
   Project                             ← کانتینر مدیریتی. بدون وزن.
      │
      ├── Module / Deliverable   ← 🆕 مالک سهم توسعهٔ ماژول از پروژه
      │      │                     مثال: Module A = ۴۰٪، Module B = ۳۵٪، Module C = ۲۵٪
      │      │
      │      └── Module Stage    ← 🆕 مالک سهم استاندارد مرحله از ماژول
      │             (۹ ردیف)        Analysis=15, Design=5, Coding=35,
      │                             Functional Test=3, PenTest=5, Training=7,
      │                             Pilot=10, Production=5, Support=15
      │                             مجموع = ۱۰۰ از Module
      │
      ├── WbsPhase               ← 🚫 وزن ندارد
      │      │                     نقش: محدوده، تاریخ، مدت، خروجی‌ها، Checklist
      │      │
      │      └── WbsPhaseOutput  ← 🆕 Checklist خروجی‌ها
      │
      └── Task                   ← 🚫 وزن ندارد، 🚫 درصد مستقل ندارد
             │                     نقش: اجرای واقعی کار + Evidence
             ├── (اختیاری) Module Stage
             └── (اختیاری) WbsPhase
```

---

## ۳. موجودیت Module

### ۳.۱ تعریف

> Module (یا Deliverable) واحدی است که سهم توسعهٔ آن در پروژه، وزن‌دار است. هر Project می‌تواند یک یا چند Module داشته باشد.

### ۳.۲ طرح جدول پیشنهادی

```sql
modules
─────────────────────────────────────────────────────────────────
id                    BIGINT IDENTITY PK
project_id            BIGINT NOT NULL  FK → projects      ON DELETE RESTRICT
name                  VARCHAR(255) NOT NULL
code                  VARCHAR(100) NULL
description           TEXT NULL
weight                NUMERIC(5,2) NOT NULL      -- سهم این ماژول از پروژه
kind                  VARCHAR(50) NOT NULL       -- ⚠️ وابسته به OQ-01
status                VARCHAR(50) NOT NULL
start_date            DATE NULL
end_date              DATE NULL
sort_order            INTEGER NOT NULL DEFAULT 0
created_at            TIMESTAMPTZ NOT NULL
updated_at            TIMESTAMPTZ NOT NULL
deleted_at            TIMESTAMPTZ NULL

UNIQUE (project_id, code)
INDEX  (project_id)

CONSTRAINT chk_module_weight  CHECK (weight > 0 AND weight <= 100)
CONSTRAINT chk_module_dates   CHECK (end_date IS NULL OR start_date IS NULL OR end_date >= start_date)
```

### ۳.۳ نکات طراحی

**الف) `weight` بدون Constraint مجموعی.**
مطابق دستور صریح §۳ پرامپت: «فقط نتیجه تحلیل را گزارش کن و بدون تأیید Constraint جدید ایجاد نکن.» پس فقط CHECK دامنه (0..100) — که خودش هم در `wbs_phases` سابقه دارد — پیشنهاد می‌شود. کنترل مجموع در لایه Service قرار می‌گیرد **مشروط به تصمیم `OQ-01`**.

**ب) `kind` — وابسته به OQ-01.**
اگر گزینهٔ (الف) «مجموع دقیقاً ۱۰۰» انتخاب شود، `kind` لازم نیست. اگر گزینهٔ (ب) «فقط توسعه‌ای‌ها کنترل شوند» انتخاب شود، `kind` الزامی است. **این ستون نباید بدون تأیید ساخته شود.**

**ج) `UNIQUE (project_id, code)` و رفتار NULL.**
PostgreSQL مقادیر NULL را در UNIQUE متمایز می‌شمارد، پس چند ماژول بدون `code` با هم تضاد ندارند. اگر رفتار دیگری مطلوب است → `OQ-02`.

**د) `softDeletesTz()` — هم‌خوان با سبک موجود.**
`projects`، `wbs_phases` و `tasks` همه Soft Delete دارند. Module باید همان الگو را داشته باشد. توجه: `ON DELETE RESTRICT` روی FK فرزندان یعنی Soft Delete برای جلوگیری از شکست FK ضروری است.

**ه) الگوی نام‌گذاری Constraint.**
مطابق `chk_wbs_weight`، `chk_task_weight`، `chk_project_dates` موجود → `chk_module_*`.

### ۳.۴ رابطه‌های Eloquent پیشنهادی

```php
Project::modules()          hasMany(Module::class)
Module::project()           belongsTo(Project::class)
Module::stages()            hasMany(ModuleStage::class)
Module::progressApprovals() hasMany(StageProgressApproval::class)
```

---

## ۴. موجودیت Module Stage

### ۴.۱ کاتالوگ استاندارد (BD-04)

| # | `stage_code` | نام پیش‌فرض | وزن | منبع تصمیم |
|---|---|---|---|---|
| ۱ | `analysis` | Analysis | 15.00 | §۴ |
| ۲ | `design` | Design | 5.00 | §۴ |
| ۳ | `coding` | Coding | 35.00 | §۴ |
| ۴ | `functional_test` | Functional Test | 3.00 | §۴ |
| ۵ | `pentest` | PenTest | 5.00 | §۴ |
| ۶ | `training` | Training | 7.00 | §۴ |
| ۷ | `pilot` | Pilot | 10.00 | §۴ |
| ۸ | `production` | Production | 5.00 | §۴ |
| ۹ | `support` | Support | 15.00 | §۴، §۱۰ |
| | | **مجموع** | **100.00** | |

### ۴.۲ طرح جدول پیشنهادی

```sql
module_stages
─────────────────────────────────────────────────────────────────
id                    BIGINT IDENTITY PK
module_id             BIGINT NOT NULL  FK → modules  ON DELETE RESTRICT
stage_code            VARCHAR(50) NOT NULL      -- کلید کاتالوگ ۹گانه
name                  VARCHAR(255) NOT NULL     -- قابل بازنویسی توسط کاربر
weight                NUMERIC(5,2) NOT NULL     -- سهم مرحله از ۱۰۰ ماژول
sort_order            INTEGER NOT NULL DEFAULT 0
created_at            TIMESTAMPTZ NOT NULL
updated_at            TIMESTAMPTZ NOT NULL

UNIQUE (module_id, stage_code)
INDEX  (module_id)

CONSTRAINT chk_stage_weight  CHECK (weight > 0 AND weight <= 100)
```

### ۴.۳ چرا ردیف فیزیکی و نه Enum تنها

| نیاز | نتیجه |
|---|---|
| تأییدهای درصدی باید به Stage اشاره کنند | نیازمند **FK** → ردیف فیزیکی |
| درصدها باید در UI نمایش داده شوند | نیازمند **کوئری** → ردیف فیزیکی |
| وزن هر Stage باید قابل مشاهده در گزارش باشد | نیازمند **join** → ردیف فیزیکی |
| «Analysis» به‌عنوان یک شناسهٔ پایدار در کد | نیازمند **Enum** |

**نتیجه: Enum + ردیف فیزیکی.** `StageCode` به‌عنوان Enum در `app/Domain/Enums/` (هم‌خوان با ۸ Enum موجود) ساخته می‌شود و مقدار `->value` آن در `module_stages.stage_code` می‌نشیند. این الگو در Repository موجود است: `TaskStatus` Enum + ستون `tasks.status` به‌صورت VARCHAR.

**نکته:** امروز `tasks.status` یک `VARCHAR(50)` بدون CHECK است و Enum فقط در لایه PHP اعمال می‌شود. برای `module_stages.stage_code` توصیه می‌شود همین الگو حفظ شود (نه `CHECK IN (...)`) تا افزودن Stage جدید نیازمند Migration نباشد.

### ۴.۴ تولید خودکار

هنگام ساخت یک Module جدید، ۹ ردیف Stage با وزن‌های پیش‌فرض **به‌صورت خودکار** تولید شوند. این کار در `ModuleService::create()` داخل تراکنش انجام می‌شود — همان الگوی `TaskService::create()` که چند موجودیت را در یک `DB::transaction` می‌سازد.

---

## ۵. محاسبهٔ سهم (تحلیل، بدون پیاده‌سازی)

مطابق BD-22 و دستور §۴، **هیچ فرمول Progress در این فاز پیاده‌سازی نمی‌شود.** این بخش فقط برای اثبات کفایت نوع داده است.

```text
سهم یک Stage از Project:

  stage_share_of_project = module.weight × stage.weight / 100

  مثال ۱:  Module A = 40%، Stage = Analysis(15)  →  40 × 15 / 100 = 6.00%
  مثال ۲:  Module A = 40%، Stage = Coding(35)    →  40 × 35 / 100 = 14.00%
  مثال ۳:  Module C = 25%، Stage = Support(15)   →  25 × 15 / 100 = 3.75%
```

**نتیجهٔ طراحی:** `NUMERIC(5,2)` برای `modules.weight` و `module_stages.weight` کافی است. حاصل‌ضرب دو مقدار 2-decimal حداکثر 4 رقم اعشار دارد، اما مقدار ذخیره‌شده همیشه 2-decimal می‌ماند چون خودِ ضرایب 2-decimal هستند.

---

## ۶. تفکیک Module Stage از WBS Phase

این تفکیک، مهم‌ترین تصمیم معماری V1.8 است (BD-05).

| بُعد | Module Stage | WBS Phase |
|---|---|---|
| **ماهیت** | سهم استاندارد وزنی | محدوده و برنامهٔ اجرایی |
| **مالک وزن** | ✅ بله | 🚫 خیر |
| **تعداد** | ثابت: ۹ × تعداد Module | متغیر: آزاد |
| **قابل حذف** | خیر (کاتالوگ) | بله |
| **تاریخ شروع/پایان** | خیر | ✅ بله |
| **مدت تخمینی** | خیر | ✅ بله |
| **Expected Outputs / Checklist** | خیر | ✅ بله |
| **وضعیت** | درصد تأییدشده (تجمعی) | Completed / Not Completed (تأیید ناظر) |
| **تأیید** | Employer/Supervisor — **کمی** | Supervisor — **کیفی/دودویی** |
| **مرجع تصمیم** | §۴، §۷، §۹ | §۵، §۶ |

### ۶.۱ رابطهٔ بین آن‌ها

مثال §۵ پرامپت:

```text
WBS Phase 1
  عنوان: تحلیل، طراحی، معماری
  Start / End / Duration
  Expected Outputs:
    □ SRS           □ ERD          □ DFD
    □ UI/UX Prototype  □ Technical Stack
    □ Schedule      □ Documentation  □ Git Delivery
```

این **یک** WBS Phase است که **دو** Stage (`analysis` + `design`) را پوشش می‌دهد. یعنی رابطه، چند‌به‌چند است — یا اگر کار ساده‌تر مطلوب باشد، «یک Phase به یک Module» و Stageها از طریق Module قابل استنتاج‌اند.

**⚠️ این رابطه هنوز مصوب نشده → `OQ-03`. بدون آن، ساختار FK قابل طراحی نیست.**

### ۶.۲ تناقض با طراحی قبلی

در ممیزی مرحلهٔ اول V1.8 پیشنهاد شده بود Support به‌عنوان یک `wbs_phase` با `kind='support'` مدل شود. **این پیشنهاد با §۱۰ پرامپت باطل شد.** مدل صحیح: Support یک Stage درون Module است.

---

## ۷. تحلیلی که باید در برابر سه گزینه سنجیده شود

جدول زیر مبنای تصمیم `OQ-01` (سیاست مجموع وزن) و `OQ-03` (رابطه WBS↔Module) است.

| مفهوم | موجودیت مالک | علت |
|---|---|---|
| سهم ماژول از پروژه | `modules` | با BD-02 و BD-03 |
| سهم مرحله از ماژول | `module_stages` | با BD-04 و BD-10 (تأیید تجمعی) |
| محدوده، زمان و خروجی اجرایی | `wbs_phases` + `wbs_phase_outputs` | با BD-05، BD-06 |
| تأیید درصدی | `stage_progress_approvals` | با BD-10، BD-12..BD-17 |
| تأیید تکمیل محدوده | ستون‌های تأیید روی `wbs_phases` | با BD-08 |
| اجرای کار و شواهد | `tasks` + `documents` | با BD-01 |
| تاریخچهٔ تغییرات | `activity_logs` | با BD-21 (**مشروط به رفع NullAuditService**) |

---

## ۸. پرسش‌های باز مؤثر بر این سند

| ID | پرسش |
|---|---|
| `OQ-01` | مجموع وزن Moduleها: دقیقاً ۱۰۰ / فقط توسعه‌ای‌ها / وابسته به Project |
| `OQ-02` | `modules.code` می‌تواند NULL و تکراری باشد؟ |
| `OQ-03` | رابطهٔ WBS Phase ↔ Module: یک‌به‌یک / چند‌به‌چند / بدون رابطه |
| `OQ-05` | معنای Weight Lock در مدل جدید |
| `OQ-10` | آیا تکمیل WBS Phase بر Progress اثری دارد؟ |
| `OQ-12` | Morph Map — پیش از اتصال Document به Module/Stage |

---

**END OF DOCUMENT**
