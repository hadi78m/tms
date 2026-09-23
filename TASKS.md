# TASKS.md - مستندات فنی

> این فایل برای هر پروژه متفاوت است و اطلاعات فنی خاص آن پروژه را دارد.
> **آخرین به‌روزرسانی:** ۱۴۰۵/۰۶/۳۱ (2026-09-22) — Production Migration V1.8 روی `tms` اجرا شد + رفع `T-1`

## اطلاعات پروژه

- **نام**: سامانه مدیریت وظایف (TMS — Task Management System)
- **توضیحات**: سامانه عملیاتی مدیریت وظایف، فازهای WBS و قراردادهای پیمانکاری؛
  شامل گردش‌کار تاییدات دو مرحله‌ای، SLA، اسناد، گزارش‌گیری و داشبوردهای نقش‌محور.
  سامانه اصلی سازمان، Source of Truth مالی و قراردادی باقی می‌ماند.
- **فریم‌ورک**: Laravel 13 / PHP 8.5 / PostgreSQL / Tailwind CSS (RTL) / Pest
- **تاریخ شمسی**: تقویم جلالی سراسری (`jdate()`, `to_jalali()`, `JalaliDate`, `SafeJalali`)
- **وضعیت**: V1.8 **Migration روی Production اجرا شد** — `tms` روی `32 migrations · 35 tables · 4 triggers · 3 functions`. دروازه: `READY FOR V1.8 POST-MIGRATION CODE HARDENING`
  (مرجع: `docs/V1.8_PRODUCTION_MIGRATION_REPORT.md` · `docs/V1.8_T1_POST_MIGRATION_RECONCILIATION.md`)

## ساختار پروژه

```
tms/
├── app/
│   ├── Domain/                     ← لایه کسب‌وکار مستقل از فریم‌ورک (DEC-001)
│   │   ├── Contracts/              AuditServiceInterface
│   │   ├── DTOs/                   CreateTaskData, AssignTaskData
│   │   ├── Enums/                  ۸ Enum (TaskStatus, ApprovalType, SlaType, ...)
│   │   ├── Exceptions/             ۱۴ Domain Exception
│   │   ├── Rules/                  TaskStateTransition, TaskScopeService
│   │   └── Services/               ۹ سرویس دامنه
│   ├── Http/
│   │   ├── Controllers/Web/        ۱۰ کنترلر وب
│   │   └── Requests/Web/           FormRequest + تبدیل خودکار تاریخ شمسی
│   ├── Models/                     ۱۷ Model
│   ├── Support/                    JalaliDate, helpers.php
│   └── Providers/                  AppServiceProvider (بایند Audit)
├── database/
│   ├── migrations/                 ۲۱ Migration
│   ├── factories/                  TaskFactory, ...
│   └── seeders/                    InitialTmsSeeder, SystemSettingSeeder
├── docs/                           مستندات طراحی و ممیزی
├── project_context/                ADRها، Changelog، Session Log، Handoff
├── resources/views/                Blade (RTL) — tasks, users, reports, settings, dashboard
├── routes/web.php                  تمام روت‌ها
└── tests/                          Pest — Feature + Unit
```

## API / سرویس‌ها

> نکته: این پروژه **API عمومی ندارد**؛ تمام تعاملات از طریق روت‌های وب (Blade)
> انجام می‌شود. روت‌ها در `routes/web.php` ثبت شده‌اند.

| Endpoint | Method | توضیحات | مجوز |
|----------|--------|---------|------|
| `/login` | GET/POST | ورود با کدملی ۱۰ رقمی | guest |
| `/dashboard` | GET | داشبورد کاربری | auth |
| `/tasks` | GET | کارتابل وظایف (با Contractor Isolation) | auth |
| `/tasks/create` | GET | فرم ایجاد وظیفه | auth |
| `/tasks` | POST | ثبت وظیفه جدید | auth |
| `/tasks/{task}` | GET | جزئیات وظیفه | auth + scope |
| `/tasks/{task}/assign` | POST | ارجاع وظیفه | auth |
| `/tasks/{task}/start` | POST | شروع اجرا (با بررسی وابستگی) | auth + scope |
| `/tasks/{task}/submit` | POST | ثبت پایان کار (توقف SLA حل) | auth + scope |
| `/tasks/{task}/approvals` | POST | ثبت تایید فنی/نهایی | `technical_approval` / `final_approval` |
| `/tasks/{task}/documents` | POST | بارگذاری سند/شاهد | `upload evidence` |
| `/tasks/{task}/dependencies` | POST | افزودن پیش‌نیاز | auth + scope |
| `/tasks/{task}/dependencies/{dependency}/remove` | POST | حذف پیش‌نیاز (POST طبق OWASP) | auth + scope |
| `/reports` | GET | صفحه گزارش‌ها + KPI | `admin\|management\|employer\|project_manager` |
| `/reports/export` | GET | خروجی CSV (تاریخ شمسی) | همان بالا |
| `/settings` | GET/POST | تنظیمات سیستم | `admin` |
| `/users` | resource | مدیریت کاربران | `admin` |

## فیلدها / مدل‌ها

### `Task` — مرکزی‌ترین موجودیت

| فیلد | نوع | توضیحات |
|------|-----|---------|
| `id` | bigint | شناسه |
| `project_id` | fk → projects | پروژه عملیاتی |
| `contract_id` | fk → synced_contracts | قرارداد (Denormalized) |
| `contractor_id` | fk → synced_contractors | پیمانکار (Denormalized) |
| `wbs_phase_id` | fk nullable → wbs_phases | فاز مرتبط (برای Support خالی است) |
| `parent_task_id` | fk nullable → tasks | تسک والد (Subtask) |
| `title` | string(255) | عنوان |
| `description` | text | توضیحات |
| `weight` | numeric(5,2) NULL | 🟡 **DEPRECATED** — از `M-07` NULL-پذیر شد؛ معیار کسب‌وکاری نیست (`BD-07` · `DEC-033`). ⚠️ `ReportController` هنوز آن را گزارش می‌کند (`R-1`) |
| `priority` | string(50) | `TaskPriority` Enum |
| `status` | string(50) | `TaskStatus` Enum (۹ وضعیت) |
| `planned_start_at` / `planned_due_at` | timestamptz | برنامهٔ زمانی |
| `actual_started_at` / `submitted_at` | timestamptz | زمان‌های واقعی |
| `supervisor_approved_at` / `approved_at` / `cancelled_at` | timestamptz | مهرهای زمانی چرخه |
| `created_by` | fk → users | ایجادکننده |

### سایر مدل‌ها

| Model | جدول | نقش |
|-------|------|-----|
| `Project` | `projects` | کانتینر — ۱:۱ با قرارداد (`contract_id` UNIQUE) |
| `WbsPhase` | `wbs_phases` | فاز WBS — مالک وزن نیست (تصمیم V1.8) |
| `WeightChangeRequest` | `weight_change_requests` | ⚠️ بدون UI — Deprecate در V1.8 |
| `Approval` | `approvals` | تایید کیفی تسک (Immutable) |
| `TaskAssignment` | `task_assignments` | ارجاع (یک فعال با partial unique) |
| `TaskDependency` | `task_dependencies` | وابستگی FS با کنترل چرخه |
| `Document` | `documents` | سند (چندریختی) + SHA-256 |
| `SlaRecord` / `SlaEvent` | `sla_records` / `sla_events` | SLA Response/Resolution |
| `PerformanceRecord` | `performance_records` | عملکرد دوره‌ای (بدون Caller) |
| `ActivityLog` | `activity_logs` | Audit (Immutable) — ⚠️ نویسنده غیرفعال |
| `SystemSetting` | `system_settings` | تنظیمات key/value |
| `User` | `users` | کدملی ۱۰ رقمی = username + Spatie Roles |
| `SyncedSystem` / `SyncedContractor` / `SyncedContract` | `synced_*` | همگام‌سازی با سامانه اصلی |

## نقش‌ها و مجوزها

| نقش | مجوزها |
|-----|--------|
| `admin` | همه (۱۱ مجوز) |
| `project_manager` | manage projects, create tasks, assign tasks, view reports |
| `supervisor` | technical_approval, submit rework, view reports |
| `employer` | final_approval, submit rework, view reports, export reports |
| `contractor` | upload evidence |
| `management` | view reports, export reports |
| `viewer` | view reports |

## فازهای تکمیل‌شده

- [x] Phase S0 — Foundation (Enums, Rules, DTOs, Exceptions)
- [x] Phase S1 — Task Core
- [x] Phase S2 — Assignment
- [x] Phase S3 — Approval and Weight
- [x] Phase S4 — SLA
- [x] Phase S5 — Supporting Services (Performance, Document)
- [x] Phase S6 — Integration and Quality
- [x] **Phase V1.1** — UI & Presentation Layer (Blade & Tailwind CSS RTL)
- [x] **Phase V1.2** — User & Access Management
- [x] **Phase V1.3** — Two-Tier Approval & Employer Workflow
- [x] **Phase V1.4** — Evidence Hashing & Claim Tracking
- [x] **Phase V1.5** — Subtasks & Dependency Blocking
- [x] **Phase V1.6** — Dynamic Settings & Export Reports
- [x] **Phase V1.7** — Jalali/Persian Date Integration
- [x] **Phase V1.7 Stabilization** — Web Layer
- [x] **Phase V1.8 Design Freeze** — Weight / Module / WBS Architecture (Documentation Only، صفر تغییر کد)
- [x] **Phase V1.8 Detailed Schema Design** — طراحی قطعی جدول‌ها، Constraintها، Triggerها
- [x] **Phase V1.8 Migration Implementation** — ۹ مهاجرت افزایشی؛ **۲۳ مهاجرت Baseline صفر تغییر** · تست: `175 passed`
- [x] **Phase V1.8 Production Migration** — اجرای روی `tms` با توالی دو مرحله‌ای (`M-07` در Batch جدا) · `32/35/4/3`
- [x] **Phase V1.8 T-1 Resolution** — بستن شکاف fork در زنجیرهٔ supersession · تست: `191 passed`
- [x] **Phase V1.8 Post-Migration Code Hardening** — `DEC-036` (R1-D · R1-F1) + `DEC-037` (T-2-A · T-2-UI-A) · `task_type` اجباری در کل مسیر Create Task · تست: `203 passed`

## فازهای باز

- [ ] **Phase V1.8b** — Web UI (Module, Stage, Checklist, Progress Approval)  ⏳ آمادهٔ شروع
- [x] **تصمیم `R-1`** — `DEC-036`: `R1-D` (NULL در مسیر عادی ممنوع) + `R1-F1` (weight اجباری می‌ماند)
- [x] **تصمیم `R-2`** — `DEC-037`: `T-2-A` + `T-2-UI-A` — `task_type` اجباری با فیلد UI
- [ ] **تصمیم `R-3` + `T-3`..`T-7`** — کنسولیدیشن تعریف Active و یادداشت‌های فنی باز  ⏳ نیازمند مالک
- [ ] سخت‌سازی نهایی و آماده‌سازی استقرار

## تنظیمات

| تنظیم | مقدار پیش‌فرض | نوع | توضیحات | وضعیت |
|-------|---------------|-----|---------|-------|
| `approval_mode` | `two_tier` | string | نحوه تایید (`two_tier` یا `employer_only`) | ⚠️ در کد خوانده نمی‌شود |
| `allow_reopen` | `false` | boolean | بازگشایی وظایف تایید‌شده | ⚠️ در کد خوانده نمی‌شود |
| `require_evidence_on_submit` | `true` | boolean | الزام آپلود مستند در ثبت پایان | ⚠️ در کد خوانده نمی‌شود |
| `lock_weight` | `true` | boolean | قفل وزن وظیفه پس از ارجاع | ⚠️ در کد خوانده نمی‌شود + در V1.8 بازنشسته می‌شود |
| `progress_approval_mode` | `supervisor_only` | string | حالت تعیین/تأیید درصد Stage | ✅ **Seed شد** (`M-09` اجرا شد روی `tms` — 2026-09-22) |

> ⚠️ **یافتهٔ ممیزی V1.8:** `SettingsService` تنها در `SettingController` تزریق شده و
> **هیچ سرویس دیگری آن را نمی‌خواند.** یعنی هر ۴ کلید موجود، صرفاً ذخیره می‌شوند و
> هیچ رفتاری را تغییر نمی‌دهند.

## مراجع کلیدی

| سند | موضوع |
|-----|------|
| `docs/V1.8_WEIGHT_MODULE_ARCHITECTURE_AUDIT.md` | ممیزی و تصمیمات قطعی V1.8 + Migration Gate |
| `docs/10-database-design.md` | نمای کامل Schema |
| `docs/weight-module-design-v1.8.md` | Module و Stage |
| `docs/module-stage-approval-design-v1.8.md` | تأیید درصدی |
| `docs/wbs-phase-completion-design-v1.8.md` | Checklist و تأیید ناظر |
| `docs/dashboard-data-requirements-v1.8.md` | نیازهای دادهٔ داشبوردها |
| `project_context/ANTIGRAVITY_DECISIONS.md` | ADRها (DEC-001 تا DEC-008) |
| `TMS_PROJECT_TRACKER.md` | ترکر اصلی پروژه |
