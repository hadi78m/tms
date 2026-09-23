# Dashboard Data Requirements — V1.8

**وضعیت:** ANALYSIS ONLY — تحلیل نیازهای داده (BD-23: هیچ UI ساخته نمی‌شود)
**دامنه:** چهار Dashboard مصوب و منابع دادهٔ آن‌ها
**وابستگی:** `docs/weight-module-design-v1.8.md`، `docs/module-stage-approval-design-v1.8.md`، `docs/wbs-phase-completion-design-v1.8.md`

---

## ۱. اصل حاکم

> Dashboardها **نباید** بر اساس User Type ثابت طراحی شوند. کنترل باید بر اساس:
> ```text
> Role + Permission + Settings
> ```

### ۱.۱ وضعیت فعلی کد — انحراف از اصل

```php
// app/Http/Controllers/Web/DashboardController.php

$user = Auth::user();
$myTasksQuery = Task::query();

if ($user->contractor_id) {                              // ← بر اساس کلید داده، نه Role
    $myTasksQuery->where('contractor_id', $user->contractor_id);
    $myTasksCount = $myTasksQuery->count();
} else {
    $myTasksCount = Task::where('status', 'completed')->count();   // ← باگ + تناقض
}
```

**سه انحراف شناسایی‌شده:**

| # | انحراف | شاهد |
|---|---|---|
| ۱ | شاخه‌بندی بر اساس `user->contractor_id` (کلید داده) به‌جای `Role`/`Permission` | خط ۱۶ |
| ۲ | `'completed'` یک مقدار **نامعتبر** در `TaskStatus` است — کارت «وظایف نیازمند بررسی» برای کاربران غیرپیمانکار **همیشه صفر** است | خط ۲۲ |
| ۳ | `SettingsService` **صفر** مصرف‌کننده در داشبورد دارد. هر ۴ کلید تنظیمات (`approval_mode`، `allow_reopen`، `require_evidence_on_submit`، `lock_weight`) نوشته می‌شوند اما خوانده نمی‌شوند | کل فایل |

**همین باگ #۲ در `ReportController:18` هم تکرار شده:** `->where('status', '!=', 'completed')` که فیلتر تأخیر را بی‌اثر می‌کند.

### ۱.۲ برچسب‌های منقضی در View

`resources/views/dashboard.blade.php` خطوط ۵۸-۷۳ یک نقشهٔ ترجمه دارد که کلیدهایش با Enum هم‌خوان نیست:

| کلید در View | در `TaskStatus` وجود دارد؟ |
|---|---|
| `pending` | 🚫 خیر |
| `in_progress` | ✅ بله |
| `completed` | 🚫 خیر |
| `approved` | ✅ بله |
| `rejected` | 🚫 خیر |

و ۷ وضعیت واقعی (`draft`, `assigned`, `submitted_for_review`, `under_review`, `supervisor_approved`, `needs_rework`, `cancelled`) **هیچ ترجمه‌ای ندارند** و مقدار خام Enum نمایش داده می‌شود.

---

## ۲. الزامات دادهٔ چهار Dashboard

### ۲.۱ Employer / Beneficiary Dashboard

**نمایش (طبق §۱۶ پرامپت):** Moduleها، Stageها، درصدهای تأییدشده، خروجی‌های ناقص، Taskهای نیازمند بررسی، وضعیت Support، SLA

| # | دادهٔ لازم | منبع | وضعیت منبع |
|---|---|---|---|
| ۱ | فهرست Moduleهای پروژه + وزن هر یک | `modules` | 🆕 جدید |
| ۲ | فهرست Stageهای هر Module + وزن استاندارد | `module_stages` | 🆕 جدید |
| ۳ | درصد تأییدشدهٔ هر Stage | `SUM(stage_progress_approvals.approved_amount) WHERE status='approved'` | 🆕 جدید |
| ۴ | درصد باقیماندهٔ هر Stage | `stage.weight − (۳)` | محاسباتی |
| ۵ | **خروجی‌های ناقص** | `wbs_phase_outputs WHERE is_completed = false` | 🆕 جدید |
| ۶ | Taskهای نیازمند بررسی | `tasks WHERE status IN ('submitted_for_review','under_review')` | ✅ موجود |
| ۷ | وضعیت Support | `module_stages WHERE stage_code='support'` + تأییدهای آن | 🆕 جدید |
| ۸ | SLA | `sla_records` + `is_breached` | ✅ موجود |
| ۹ | درصدهای پیشنهادی در انتظار تصمیم | `stage_progress_approvals WHERE status='pending'` | 🆕 جدید |

### ۲.۲ Contractor Dashboard

**نمایش (طبق §۱۶):** Taskهای تخصیص‌یافته، Deadline، SLA، Submission، Rework، Evidence، وضعیت تأیید

| # | دادهٔ لازم | منبع | وضعیت |
|---|---|---|---|
| ۱ | Taskهای تخصیص‌یافته | `task_assignments WHERE user_id = ? AND ended_at IS NULL` | ✅ موجود |
| ۲ | Deadline | `tasks.planned_due_at` | ✅ موجود |
| ۳ | SLA | `sla_records` (Response: `started_at`/`stopped_at`; Resolution) | ✅ موجود |
| ۴ | Submission | `tasks.submitted_at` | ✅ موجود |
| ۵ | Rework | `tasks.status = 'needs_rework'` + شمارش `needs_rework` در `performance_records` | ✅ موجود |
| ۶ | Evidence | `documents WHERE attachable_type = Task` | ✅ موجود |
| ۷ | وضعیت تأیید تسک | `approvals` (technical / final) | ✅ موجود |

**⛔ نکتهٔ حیاتی برای این Dashboard:**

> **Contractor **نباید** درصد پیشرفت ماژول را ببیند.**

دلیل: BD-01 و BD-11 صریحاً می‌گویند Task درصد مستقل ندارد و درصد Stage یک ارزیابی سازمانی است، نه معیار عملکرد مستقیم پیمانکار. نمایش آن، انتظار نادرستی ایجاد می‌کند (ریسک `R-07` در ممیزی).

اگر مالک پروژه مایل به نمایش باشد → `OQ-16` (جدید).

### ۲.۳ Supervisor Dashboard

**نمایش (طبق §۱۶):** Moduleها، Stageها، موارد Pending Approval، درصدهای پیشنهادی، درصدهای تأییدشده، Phaseهای در انتظار تأیید، Taskهای نیازمند Review، SLA Breach

| # | دادهٔ لازم | منبع | وضعیت |
|---|---|---|---|
| ۱ | Moduleها + Stageها | `modules` + `module_stages` | 🆕 جدید |
| ۲ | **موارد Pending Approval** | `stage_progress_approvals WHERE status='pending'` | 🆕 جدید |
| ۳ | **درصدهای پیشنهادی** | `proposed_amount` + `proposed_by` | 🆕 جدید |
| ۴ | **درصدهای تأییدشده** | `SUM(approved_amount) WHERE status='approved'` | 🆕 جدید |
| ۵ | **Phaseهای در انتظار تأیید نهایی** | `wbs_phases WHERE completion_status='pending'` + وضعیت Checklist آن‌ها | 🆕 جدید |
| ۶ | **خروجی‌های تیک‌نخورده** | `wbs_phase_outputs WHERE is_completed=false` | 🆕 جدید |
| ۷ | Taskهای نیازمند Review | `tasks WHERE status='under_review'` | ✅ موجود |
| ۸ | SLA Breach | `sla_records WHERE is_breached=true` | ✅ موجود |

**این داشبورد، کارتابل اصلی ناظر است** — چون BD-14 تمام تأییدهای نهایی (هم درصدی، هم تکمیل فاز) را به ناظر سپرده. پس این Dashboard بیشترین وابستگی به جداول جدید را دارد.

### ۲.۴ Management Dashboard

**نمایش (طبق §۱۶):** Project Status، Module Progress، Phase Status، Delays، SLA Breaches، Contractor Performance، Approved vs Pending Work

| # | دادهٔ لازم | منبع | وضعیت |
|---|---|---|---|
| ۱ | Project Status | `projects.status` | ✅ موجود |
| ۲ | Module Progress | Aggregate از `stage_progress_approvals` گروه‌بندی بر `module_id` | 🆕 جدید |
| ۳ | Phase Status | `wbs_phases.status` + `completion_status` | 🔧 نیمه‌موجود |
| ۴ | Delays | `tasks WHERE planned_due_at < NOW() AND status NOT IN (...)` | ✅ موجود (⚠️ باگ فیلتر) |
| ۵ | SLA Breaches | `sla_records WHERE is_breached = true` | ✅ موجود |
| ۶ | Contractor Performance | `performance_records` | ⚠️ موجود اما بدون Caller |
| ۷ | **Approved vs Pending Work** | نسبت `SUM(approved_amount)` به `SUM(proposed_amount)` | 🆕 جدید |

---

## ۳. ماتریس دسترسی پیشنهادی (Role + Permission + Settings)

```text
┌──────────────────────┬──────────┬────────────┬───────────┬────────────┐
│ Widget / Data        │ Employer │ Contractor │ Supervisor│ Management │
├──────────────────────┼──────────┼────────────┼───────────┼────────────┤
│ فهرست Moduleها        │    ✅    │     🚫     │    ✅     │     ✅     │
│ وزن Moduleها          │    ✅    │     🚫     │    ✅     │     ✅     │
│ Stageها + وزن         │    ✅    │     🚫     │    ✅     │     ✅     │
│ درصد تأییدشده Stage   │    ✅    │  ⛔ OQ-16  │    ✅     │     ✅     │
│ درصد پیشنهادی Pending │    ✅    │     🚫     │    ✅     │     ✅     │
│ Checklist خروجی‌ها     │    ✅    │   محدود    │    ✅     │     ✅     │
│ تأیید تکمیل Phase     │  🚫 فقط‌خوان │    🚫     │  ✅ عمل   │     ✅     │
│ Taskهای خودش          │     —    │     ✅     │     —     │     —      │
│ Taskهای نیازمند Review│    ✅    │     🚫     │    ✅     │     ✅     │
│ SLA / Breach          │    ✅    │  تسک خودش  │    ✅     │     ✅     │
│ Contractor Performance│    ✅    │  🚫 (خودش)  │    ✅     │     ✅     │
│ Approved vs Pending   │    ✅    │     🚫     │    ✅     │     ✅     │
└──────────────────────┴──────────┴────────────┴───────────┴────────────┘
```

### ۳.۱ نقش Settings در کنترل

نمونهٔ کلیدهای تنظیمات مؤثر بر نمایش:

| کلید تنظیمات | اثر بر Dashboard |
|---|---|
| `progress_approval_mode` | در `supervisor_only`، کارت «درصدهای پیشنهادی» در Employer Dashboard بی‌معنا می‌شود؛ در `employer_then_supervisor` پر می‌شود |
| `dashboard.visible_widgets` (پیشنهادی) | فهرست Widgetهای فعال per Role |
| `dashboard.default_module_view` (پیشنهادی) | نمایش پیش‌فرض: تفکیک‌شده بر Module یا تجمیعی |

**وضعیت فعلی:** هیچ‌کدام از این کلیدها وجود ندارند. `settings/index.blade.php` فقط ۴ سوییچ + `approval_mode` دارد.

---

## ۴. شکاف‌های داده‌ای — جمع‌بندی

| شکاف | Dashboardهای متأثر | جدول لازم |
|---|---|---|
| Module / Stage وجود ندارد | هر چهار | `modules`, `module_stages` |
| تأیید درصدی وجود ندارد | Employer، Supervisor، Management | `stage_progress_approvals` |
| Checklist خروجی وجود ندارد | Employer، Supervisor، Management | `wbs_phase_outputs` |
| تأیید تکمیل Phase وجود ندارد | Supervisor، Management | ستون‌های `wbs_phases` |
| Audit فعال نیست | Supervisor (سابقهٔ تغییرات)، Management | `DatabaseAuditService` |
| Settings اعمال نمی‌شوند | هر چهار | مصرف‌کنندهٔ `SettingsService` |
| Permission-based visibility وجود ندارد | هر چهار | Permissionهای جدید |
| برچسب‌های وضعیت منقضی | هر چهار | اصلاح View |
| `'completed'` نامعتبر | Employer، Management | اصلاح کوئری |

---

## ۵. پرسش‌های باز

| ID | پرسش |
|---|---|
| `OQ-16` | 🆕 آیا Contractor می‌تواند درصد تأییدشدهٔ Stage را ببیند؟ (فرض طراحی: خیر) |
| `OQ-17` | 🆕 ساختار Widget/Visibility: کلید تنظیمات `dashboard.visible_widgets` یا Permission جداگانه per widget؟ |
| `OQ-18` | 🆕 آیا داشبورد تجمیعی چند-پروژه‌ای لازم است (کاربری که به چند Project دسترسی دارد)؟ |
| `OQ-11` | مرجع مجوز: Permission جدید یا نقش‌های فعلی؟ |
| `OQ-07` | سرنوشت گزارش «آماده پرداخت» — بر Management Dashboard اثر دارد |

---

## ۶. توصیه‌های معماری

| # | توصیه | دلیل |
|---|---|---|
| ۱ | **یک `ModuleProgressService` بسازید و چهار Visibility روی آن بگذارید** | هر چهار Dashboard از یک منبع داده تغذیه می‌شوند؛ تفاوت فقط در فیلتر و سطح جزئیات است |
| ۲ | **`DashboardController` را از `contractor_id` به Permission منتقل کنید** | اصل حاکم §۱۶ و هم‌خوانی با Spatie که امروز ۷ نقش و ۱۱ Permission دارد |
| ۳ | **باگ `'completed'` را در دو جا اصلاح کنید** | `DashboardController:22` و `ReportController:18` — یک باگ موجود مستقل از V1.8 |
| ۴ | **`SettingsService` را به یک `DashboardConfigService` تبدیل کنید** | تاکنون صفر مصرف‌کننده داشته؛ این فرصت طبیعی اولین استفاده است |
| ۵ | **در Contractor Dashboard، درصد پیشرفت Stage را نشان ندهید** | BD-01، BD-11 و ریسک `R-07` |
| ۶ | برچسب‌های فارسی دو مفهوم Approval را در UI تفکیک کنید | «تأیید فنی/نهایی تسک» ≠ «تأیید پیشرفت مرحله» |

---

**END OF DOCUMENT**
