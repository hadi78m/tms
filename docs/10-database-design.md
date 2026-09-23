# Database Design

**نسخه:** V1.8 (Design Freeze — تأیید مالک پروژه در انتظار)
**تاریخ:** ۱۴۰۵/۰۶/۳۰ (2026-09-21)
**وضعیت پیاده‌سازی:** 🚫 **هیچ‌کدام از تغییرات این سند اجرا نشده است.**

> **مرجع تفصیلی:** این سند نمای کلی Schema است. طراحی تفصیلی هر حوزه در اسناد اختصاصی:
> - `docs/database_field_level_design_v1.6.md` — طراحی فیلد-به-فیلد V1.6 (پیاده‌شده)
> - `docs/database_physical_design_v1.5.md` — طراحی فیزیکی V1.5 (پیاده‌شده)
> - `docs/weight-module-design-v1.8.md` — موجودیت Module و Stage
> - `docs/module-stage-approval-design-v1.8.md` — تأیید درصدی
> - `docs/wbs-phase-completion-design-v1.8.md` — تکمیل WBS Phase
> - `docs/V1.8_WEIGHT_MODULE_ARCHITECTURE_AUDIT.md` — ممیزی و تصمیمات

> ⚠️ **بازبینی کهنگی (۱۴۰۵/۰۶/۳۱ / 2026-09-22):** بخش‌های زیرین این سند پس از «Design Freeze» و پس از
> اجرای Migration Review بازبینی شدند. مرجع **قطعی** Schema، `docs/V1.8_DETAILED_SCHEMA_DESIGN.md` است و
> مرجع وضعیت دروازه، `docs/V1.8_MIGRATION_REVIEW_REPORT.md` و `docs/V1.8_OQ_AND_REVIEW_ISSUE_RESOLUTION.md`.
> در همین بازبینی، ۱۵ بند کهنهٔ مستنداتی (شامل `kind`، `wbs_phase_checklist_items`، `supervisor_confirmed_*`،
> وضعیت‌های قدیمی ستون‌های Weight و اشاره‌گر وضعیت دروازه) اصلاح شد. هیچ قاعدهٔ کسب‌وکاری جدیدی ثبت نشد.

---

## ۱. اصول پایگاه داده (تثبیت‌شده)

| اصل | مقدار |
|---|---|
| پایگاه داده | PostgreSQL |
| کلید اصلی | `BIGINT` با `IDENTITY` |
| زمان‌های عملیاتی | `TIMESTAMPTZ` |
| دوره‌های تقویمی | `DATE` |
| FK | `ON UPDATE NO ACTION` + `ON DELETE RESTRICT` (پیش‌فرض) |
| استثنا FK | `parent_task_id` → `SET NULL` |
| رکوردهای تاریخی | Cascade-delete نمی‌شوند |
| Soft Delete | برای موجودیت‌های عملیاتی (`softDeletesTz()`) |
| پول | جداول `synced_*` روی `DECIMAL(15,2)` |
| JSON | `JSONB` |
| قواعد کسب‌وکاری | در لایه Application/Service — **نه** در Database |
| تفکیک دقیق (V1.8 — `N-4` · `DEC-025`) | **Service** مالک Business Rule · **Database** مالک Data Integrity: فقط قواعد **تک‌ردیفی و deterministic** که مستقل از UI و بدون Business Workflow باشند. قواعد **تجمعی** → Service + قفل والد · قواعد **cross-table** → Service. جدول کامل: `docs/V1.8_DETAILED_SCHEMA_DESIGN.md §۶.۸` |

### ۱.۱ ⚠️ قاعدهٔ حیاتی مستندشده اما اجرانشده

`docs/database_physical_design_v1.5.md:113` و `TMS_PROJECT_TRACKER.md §6` این قاعده را ثبت کرده‌اند:

> «مجموع Weight فازهای یک Project باید `100` باشد. این قاعده صرفاً در لایه Application / Service Layer Validation هندل می‌شود.»

**گزارش ممیزی V1.8:** grep روی کل `app/` هیچ Validation، Rule، Service یا تستی برای این قاعده پیدا نمی‌کند. این قاعده **هرگز پیاده‌سازی نشده** (تناقض `C-06`/`C-07`).

**درس برای V1.8:** هر قاعدهٔ مجموع وزنی جدید (مثل مجموع وزن Moduleها) باید در Service **با تست واقعی** پیاده شود — نه فقط در مستندات. تکرار الگوی فعلی منجر به همان نتیجه می‌شود.

---

## ۲. Schema فعلی — پیاده‌شده

### ۲.۱ جداول همگام‌سازی (Synced — Source of Truth خارجی)

```text
synced_systems          سامانه‌های سازمان
synced_contractors      پیمانکاران
synced_contracts        قراردادها        ← ریشهٔ سلسله‌مراتب
```
ویژگی مشترک: `source_system` + `external_id` (UNIQUE مرکب)، `sync_status`، `last_synced_at`، حفظ تاریخچه هنگام حذف در مبدأ.

### ۲.۲ جداول عملیاتی

```text
users                   کاربران (کدملی ۱۰ رقمی = username، contractor_id اختیاری)
projects                پروژه عملیاتی — contract_id UNIQUE (۱:۱ با قرارداد)
wbs_phases              فازهای WBS — project_id، weight، expected_output، planned_duration
tasks                   تسک‌ها — project_id، wbs_phase_id (NULLABLE)، weight
weight_change_requests  درخواست تغییر وزن تسک — task_id
task_assignments        ارجاع — partial unique برای «یک فعال»
task_dependencies       وابستگی — FS، با کنترل Circular
documents               اسناد — attachable_type/id (چندریختی)، checksum، claimed_at
comments                نظرات — task_id، parent_comment_id
approvals               تأییدات تسک — task_id، approval_type، sequence، status
sla_records             رکوردهای SLA — task_id، type، is_breached
sla_events              رویدادهای SLA
performance_records     عملکرد پیمانکار — contract+contractor+period
activity_logs           لاگ فعالیت — entity_type/id (چندریختی)، old/new_values JSONB
system_settings         تنظیمات سیستم — key/value/type
```

### ۲.۳ جداول Spatie Permission

```text
roles, permissions, model_has_roles, model_has_permissions, role_has_permissions
```
وضعیت: ۷ نقش (`admin`, `project_manager`, `supervisor`, `employer`, `contractor`, `management`, `viewer`) و ۱۱ Permission — ساخته‌شده توسط `InitialTmsSeeder`.

### ۲.۴ ستون‌های حامل Weight — وضعیت کامل

| جدول | ستون | نوع | NOT NULL | CHECK | مصرف‌کننده | وضعیت V1.8 |
|---|---|---|---|---|---|---|
| `tasks` | `weight` | `NUMERIC(5,2)` | ✅ بله | `chk_task_weight` 0..100 | ۴ View + ۲ KPI + ۲ CSV + ۱۳ تست | 🟠 **DEPRECATED** — فقط `DROP NOT NULL` (`M-07`)؛ ستون حذف نمی‌شود |
| `wbs_phases` | `weight` | `NUMERIC(5,2)` | ✅ بله | `chk_wbs_weight` 0..100 | **صفر** | 🟡 **LEGACY** — دست‌نخورده؛ حذف فقط در V1.9 با تأیید صریح |
| `weight_change_requests` | `old_weight`, `new_weight` | `NUMERIC(5,2)` | ✅ بله | ۳ CHECK | Service بدون UI | 🟡 **LEGACY** — دست‌نخورده |
| `performance_records` | `total_weight_completed` | `NUMERIC(8,2)` | ✅ بله | `chk_perf_weight_completed` 0..100 | Service بدون Caller | ✅ **KEEP** — بدون تغییر ساختاری؛ فقط معنایش اصلاح می‌شود (Snapshot دوره‌ای) |

**ستون `progress` یا `percent`: 🚫 در هیچ جدولی وجود ندارد.**

---

## ۳. Schema پیشنهادی V1.8 — اجرا نشده

### ۳.۱ جداول جدید

```text
modules                     🆕 مالک سهم توسعهٔ ماژول از پروژه
                              project_id، name، code، weight، status، dates  (🚫 بدون kind)

module_stages               🆕 ۹ مرحلهٔ استاندارد با وزن (مجموع ۱۰۰ از ماژول)
                              module_id، stage_code، name، weight، sort_order

stage_progress_approvals    🆕 تأیید درصدی تجمعی
                              module_id، module_stage_id، proposed_amount، approved_amount،
                              status، proposed_by، final_approved_by، decided_at، reason،
                              supersedes_approval_id (self-FK — اصلاح بدون بازنویسی)
                              ⚠️ status ذخیره‌شده: pending | approved | rejected
                                 («superseded» یک وضعیت **مشتق** است — DEC-027)

wbs_phase_checklist_items   🆕 Checklist خروجی‌های فاز
                              wbs_phase_id، title، is_completed، completed_at، completed_by
```

### ۳.۲ جداول تغییر‌یابنده

```text
tasks
  + task_type        VARCHAR(50) NOT NULL DEFAULT 'development'  ← development | support
  + module_stage_id  BIGINT NULLABLE FK → module_stages
  + module_id        BIGINT NULLABLE FK → modules   ✅ OQ-24 = A — می‌ماند؛ هم‌خوانی در Service، بدون Trigger (DEC-021)
  ~ weight           DEPRECATED — فقط DROP NOT NULL (گام ۱ از ۲)؛ وزن تسک منتقل نمی‌شود
  - weight           DROP COLUMN       ← گام ۲ (V1.9+، نیازمند تأیید صریح)

wbs_phases
  + actual_completion        TIMESTAMPTZ NULL
  + completion_status        VARCHAR(50) NOT NULL DEFAULT 'pending'
  + supervisor_comment       TEXT NULL
  + supervisor_approved_at   TIMESTAMPTZ NULL
  + supervisor_approved_by   BIGINT NULL FK → users
  + CONSTRAINT chk_wbs_completion_consistency
  ~ weight                   LEGACY — بدون خواننده، بدون تست (وزن فاز به هیچ منبعی منتقل نمی‌شود)
  ~ expected_output          LEGACY — ستون حفظ و NOT NULL می‌ماند؛ مقدار ثابت اعلام‌شده در DEC-017
  ~ status                   → Enum WbsPhaseStatus
  ~ duration_unit            → Enum DurationUnit (ناسازگاری دادهٔ فعلی: 'day' vs 'days')
```

### ۳.۳ نمودار روابط V1.8

```text
synced_contracts ─1:1─ projects
                          │
                          ├──── modules ──── module_stages
                          │        │                │
                          │        │                └── stage_progress_approvals
                          │        └── stage_progress_approvals (Denormalized)
                          │
                          ├──── wbs_phases ──── wbs_phase_checklist_items
                          │
                          └──── tasks ─┬─ task_assignments
                                       ├─ approvals
                                       ├─ sla_records
                                       ├─ documents (چندریختی)
                                       └─ activity_logs (چندریختی)
```

---

## ۴. قواعد کسب‌وکاری در لایه Application

| قاعده | وضعیت پیاده‌سازی | محل پیشنهادی |
|---|---|---|
| مجموع وزن فازهای یک Project = 100 | 🚫 **اجرانشده** (C-06/C-07) | — (مفهوم منقضی شد؛ با Stage جایگزین می‌شود) |
| مجموع وزن ۹ Stage یک Module = 100 | ✅ **الزامی** — ساختاری برقرار (۹ ردیف در یک تراکنش) | `ModuleService::create()` + قفل والد `modules.id` |
| `SUM(active approved_amount)` هر Stage ≤ `stage.weight` | ✅ **الزامی** — سقف روی **Active Approvalها** | `StageProgressApprovalService` — ابتدا قفل ردیف والد `module_stages` (`lockForUpdate()`)، سپس تجمیع **بدون** `FOR UPDATE` (`H-1` · `DEC-026` · `DEC-027`) |
| مجموع وزن Moduleهای یک Project | ✅ **قطعی: دقیقاً 100** (`OQ-01a = 1A`) | `ModuleService` + قفل ردیف والد `projects.id` — و **صفر ماژول معتبر است** (`DEC-024`) |
| اتمیک‌بودن عملیات Module (ایجاد/حذف/Restore/Rebalance) | ✅ **الزامی** | یک عملیات = یک تراکنش؛ وضعیت میانی قابل Commit نیست (`H-3` · `DEC-022`) |
| قفل والد پیش از هر تغییر محدودهٔ تجمیعی | ✅ **الزامی (دکترین)** | `projects.id` / `modules.id` / `module_stages.id` (`H-4` · `DEC-023`) |
| قفل وزن Stage پس از اولین تأیید | ✅ **الزامی** | Service + **Trigger تک‌ردیفی** در `M-08` (`DEC-020`) |
| `tasks.weight` بین 0..100 | ✅ پیاده‌شده (DB + FormRequest) | — (منقضی می‌شود) |
| `total_weight_completed` بین 0..100 | ✅ پیاده‌شده (DB + Service) | حفظ |

---

## ۵. الگوهای تثبیت‌شدهٔ Repository که باید رعایت شوند

| # | الگو | نمونهٔ موجود |
|---|---|---|
| ۱ | Constraint نام‌دار با `DB::statement('ALTER TABLE … ADD CONSTRAINT chk_…')` | `chk_wbs_weight`, `chk_task_weight`, `chk_wcr_weight_diff`, `chk_perf_*` |
| ۲ | INDEXهای نام‌دار در `Schema::create` | `idx_perf_records_unique` |
| ۳ | Enum در `app/Domain/Enums/` + مقادیر در ستون `VARCHAR(50)` | `TaskStatus`, `ApprovalType`, `SlaType` |
| ۴ | Columnهای تأیید به‌صورت `*_at` + `*_by` | `tasks.supervisor_approved_at` (فاز V1.3) |
| ۵ | Row Lock با `lockForUpdate()` داخل `DB::transaction` | `WeightChangeRequestService:57,64` |
| ۶ | Denormalization کنترل‌شده | `tasks.contract_id`, `tasks.contractor_id` |
| ۷ | Immutability با `booted()` → `updating`/`deleting` = false | `Approval`, `ActivityLog` |
| ۸ | الگوی نام‌گذاری: `chk_<table-abbr>_<rule>` | `chk_wcr_*`, `chk_perf_*` |

---

## ۶. ریسک‌های Schema

| ID | ریسک | کاهش |
|---|---|---|
| SR-01 | `tasks.weight` NOT NULL + پرخواننده → Relax به ۱۳ فایل تست سرایت می‌کند | مسیر دو مرحله‌ای (`DROP NOT NULL` سپس `DROP COLUMN`) |
| SR-02 | ~~`modules.kind` بدون تصمیم `OQ-01` ساخته شود~~ ✅ **بسته شد** — ستون `kind` ساخته **نمی‌شود** (`OQ-01a = 1A`) | بدون اقدام |
| SR-03 | `wbs_phases.expected_output` NOT NULL است → هر فاز جدید باید مقدار داشته باشد | مقدار ثابت اعلام‌شده در `DEC-017` («مشاهدهٔ Checklist») — Checklist مرجع واقعی خروجی است |
| SR-04 | `duration_unit` ناسازگاری موجود: Seeder مقدار `'day'` می‌دهد، Enum مقدار `'days'` دارد | تصمیم `OQ-15` |
| SR-05 | `stage_progress_approvals.module_id` Denormalized → خطر واگرایی | ✅ **حل شد** — حفظ `module_id` با تضمین در Service (`OQ-06-a` · `DEC-010`) |
| SR-06 | `activity_logs` بدون نویسنده فعال → تاریخچه خالی | `DatabaseAuditService` به‌عنوان گام صفر |
| SR-07 | تعداد FK های RESTRICT روی زنجیرهٔ Contract→Project→Module→Stage→Task → حذف آبشاری غیرممکن می‌شود | مطابق دکترین موجود است؛ اما Soft Delete برای همهٔ سطوح الزامی است |
| SR-08 🆕 | **Triggerهای تجمعی ساخته نمی‌شوند** (`OQ-30 = D` · `DEC-020`) → قواعد تجمعی در برابر نوشتن خام (`DB::table()->update()`) که از Service عبور نکند، محافظت دیتابیسی **ندارند** | پذیرفتهٔ آگاهانه: Repository هیچ نوشتن خام روی `modules`/`module_stages` ندارد · سه لایهٔ کنترلی دیگر (Service + قفل والد + تست) برقرارند · Triggerهای تجمعی در صورت نیاز آینده فقط **Backstop** خواهند بود |
| SR-09 🆕 | **`superseded` دیگر وضعیت ذخیره‌شده نیست** (`DEC-027`) → هر کد/گزارشی که `WHERE status = 'superseded'` بنویسد، هیچ ردیفی برنمی‌گرداند | وضعیت مشتق با `EXISTS/NOT EXISTS` روی `supersedes_approval_id` محاسبه می‌شود · صفر پیاده‌سازی فعلی (جدول وجود ندارد) |

---

## ۷. عملیات ممنوعه (`AGENTS.md` Rules 3 و 4)

```text
🚫 DROP TABLE
🚫 TRUNCATE
🚫 migrate:fresh / migrate:refresh / migrate:reset
🚫 DB drop
🚫 حذف tasks.weight در همان فاز پیاده‌سازی اولیه
🚫 هر Constraint مجموع وزن Module بدون تصمیم صریح مالک پروژه — ✅ **تصمیم داده شد (`OQ-30` = گزینهٔ D):** تنها **Triggerهای تک‌ردیفی** مجازند؛ سه Trigger تجمعی **DEFERRED** هستند و ساخته نمی‌شوند. قواعد تجمعی فقط در Service + قفل والد اعمال می‌شوند.
🚫 هر تغییری که رکوردهای تاریخی را cascade-delete کند
```

---

## ۸. پیوست — وضعیت اجرا

```text
Migration ایجاد‌شده:        ۹  (لایهٔ افزایشی V1.8)
Migration اجرا‌شده:          ۹  روی tms · ۹ روی tms_testing
جدول ایجاد‌شده:             +۴ (modules · module_stages ·
                                stage_progress_approvals · wbs_phase_checklist_items)
ستون اضافه‌شده:             tasks (۳ ستون) · wbs_phases (۵ ستون)
Constraint ایجاد‌شده:        +۱۷ CHECK · +۱۲ FK · UNIQUE مرکب ماژول
                            (مجموع فعلی: 40 CHECK · 49 FK · 106 Index)
Trigger ایجاد‌شده:           ۴  (روی ۳ Function)
Seed اجرا‌شده:              ۱  (system_settings.progress_approval_mode)

وضعیت: MIGRATED — PRODUCTION MIGRATION SUCCESSFUL

تاریخچهٔ دروازه:
  READY FOR MIGRATION REVIEW                 → اجرا شد (۱۴۰۵/۰۶/۳۱)
  BLOCKED — REVIEW ISSUE                     → گزارش: docs/V1.8_MIGRATION_REVIEW_REPORT.md
  BLOCKED — OWNER DECISION REQUIRED           → سند: docs/V1.8_OQ_AND_REVIEW_ISSUE_RESOLUTION.md
  READY FOR MIGRATION IMPLEMENTATION REVIEW  → تصمیمات مالک: DEC-020..DEC-035
  READY FOR PRODUCTION MIGRATION REVIEW      → هر ۱۵ گیت ایمنی PASS
  PRODUCTION MIGRATION SUCCESSFUL            ← اکنون اینجا هستیم

وضعیت جاری: tms = 32 migrations · 35 tables · 4 triggers · 3 functions · 5 settings
            (۲۳ مهاجرت Baseline دست‌نخورده + ۹ مهاجرت افزایشی V1.8)
            توپولوژی Batch: {1:23, 2:1 (M-07), 3:8}

مراجع جاری:
  گزارش Production: docs/V1.8_PRODUCTION_MIGRATION_REPORT.md
  طراحی قطعی:   docs/V1.8_DETAILED_SCHEMA_DESIGN.md
  Reconciliation: docs/V1.8_FINAL_DESIGN_RECONCILIATION.md
  چک‌لیست:       docs/V1.8_MIGRATION_REVIEW_CHECKLIST.md
  حل‌وفصل OQ:    docs/V1.8_OQ_AND_REVIEW_ISSUE_RESOLUTION.md
  تصمیمات:      project_context/ANTIGRAVITY_DECISIONS.md (DEC-020..DEC-035)
```

---

**END OF DOCUMENT**
