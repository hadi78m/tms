# ACTION_TRACKER.md - گزارش تغییرات و ریفکتورهای بزرگ

> **هدف:** ثبت دقیق فایل‌های تغییریافته و خلاصه‌ای از اصلاحات انجام‌شده در جلسات کاری سنگین.

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۷/۰۷ (2026-09-28) - UI Redesign Phase 4: Task Drawer

### 📝 خلاصه اقدامات

- **معماری (Option B — fetch-on-open):** Drawer فقط‌خواندنی از مسیر **موجود** `GET /tasks/{task}?view=drawer` سرو می‌شود. صفر endpoint جدید، صفر Policy جدید، صفر Service/Permission جدید، صفر Controller جدید. `TaskPolicy::view` (ProjectScopeService + TaskScopeService) تنها مرجع احراز دسترسی باقی ماند.
- **رفع باگ فاتال (پیشین):** `components/tasks/task-drawer-content.blade.php` تگ بسته‌شدنِ خراب `<x-ui.badadge ?? '' }}` داشت که هر رندر drawer را با syntax error می‌کشت → اصلاح شد.
- **اصلاح کوچک کنترلر:** فقط افزودن `'documents'` به eager-load شاخهٔ drawer (مستقیماً توسط partial استفاده می‌شود)؛ مسیر full-detail دست‌نخورده.
- **یکپارچه‌سازی بُرد:** `tasks/board.blade.php` اکنون میزبان `<x-elements.drawer>` فاز ۱ است (سمت چپ، `sm:max-w-[430px]`، تمام‌صفحه در موبایل) با منطق fetch با Alpine، حالت loading (skeleton)، حالت error امن (بدون leak داخلی، retry فقط برای همان URL، بدون bypass احراز)، focus روی panel، و `role="dialog"`.
- **کارت → Drawer:** `components/tasks/task-card.blade.php` با کلیک چپ معمولی Drawer را باز می‌کند (`data-drawer-url` + `$dispatch('open-task-drawer', …)`)؛ anchor واقعی به‌عنوان fallback برای keyboard/no-JS حفظ شد (Progressive Enhancement)؛ کلیک‌های اصلاح‌شده (ctrl/cmd/middle) به مرورگر می‌رسند.
- **توافق با گیت فاز ۳:** متن‌های حالت Drawer از «بارگذاری» به «دریافت» تغییر کردند زیرا گیت §۱۸ فاز ۳ توکن `بارگذاری` را روی صفحهٔ بُرد ممنوع می‌کند.
- **قوانین سخت گیت §۱۸ رعایت شد:** Drawer صفر کنترل mutation دارد (بدون form/textarea/select/button/submit و بدون ارجاع به مسیرهای POST)؛ بخش‌ها فقط در صورت وجود دادهٔ واقعی رندر می‌شوند (WBS فقط نام فاز، SLA فقط `is_breached` موجود، documents فقط metadata بدون download، approvals/activity فقط تاریخچهٔ فقط‌خواندنی).

### 🧪 تست

- جدید: `tests/Feature/V111/TaskDrawerTest.php` — **۳۳ تست · ۱۳۲ assertion** (رندر، دادهٔ واقعی، ماتریس ۸ وضعیت، Review Tierهای تأییدشده، احراز دسترسی چندپروژه‌ای/پیمانکاری، ممیزی فقط‌خواندنی، لینک جزئیات کامل).
- رگرسیون فاز ۳ (`TaskBoardTest`): **۳۷ تست سبز**.
- Full Suite: **`647 passed · 2 deprecated · 2147 assertions · 0 failed`**.
- `npm run build`: **PASS** (۱۲.۴۰s؛ `430px`/`animate-pulse` در CSS نهایی تأیید شد).
- Pint (`--dirty --format agent`): PASS (فقط مرتب‌سازی import در فایل تست).

### 🛡️ ایمنی

- migrations: **صفر تغییر** (`git diff -- database/migrations` خالی) · production `tms`: ۳۲ migration و ردیف‌ها دست‌نخورده · `v19_out.txt`: git-clean · `template/`: untracked و دست‌نخورده · صفر stage · صفر commit · صفر push.

### 📁 فایل‌ها

- **تغییر یافت:**
  - `app/Http/Controllers/Web/TaskController.php` — افزودن `documents` به eager-load شاخهٔ drawer
  - `resources/views/tasks/board.blade.php` — میزبان Drawer + حالت‌های loading/error
  - `resources/views/components/tasks/task-card.blade.php` — باز کردن Drawer به‌جای ناوبری مستقیم
  - `resources/views/components/tasks/task-drawer-content.blade.php` — رفع syntax error + برچسب CTA + رزرو فضای دکمهٔ بستن
- **جدید:**
  - `tests/Feature/V111/TaskDrawerTest.php` — ۳۳ تست متمرکز فاز ۴
- **Migration/Schema/Domain/Policy:** صفر تغییر

---


## 📅 گزارش تغییرات - ۱۴۰۵/۰۷/۰۴ (2026-09-26) - فاز V1.9 CI Run Verification & Final Acceptance

### 📝 خلاصه اقدامات

- **Pre-Commit Gate:** `git diff -- database/migrations` = خالی · migrate:status 32/32 · Full Suite محلی `256/735/0/2` · V19 `53/144` · Pint PASS · npm ci/build PASS · manifest موجود · diff audit (صفر dd/dump/hard-coded ID/accidental mutation).
- **Commit:** `1c2b079` — 39 فایل (+3649/−8) — فقط فایل‌های V1.9؛ `v19_out.txt` (stale artifact) عمداً commit نشد؛ **صفر migration در commit**.
- **Push:** `2bed1d5..1c2b079 main -> main` (بدون force).
- **CI Run 36231957880** (workflow `ci.yml` · run #6 · event push · head_sha = `1c2b079f...`): **conclusion = success** — هر ۲۰ step شامل Gate 1 (tms_testing + PG 18) · Gate 2 (32/0) · manifest gate · Run test suite (T-6 فعال) همگی success.
- **حکم نهایی: `V1.9 = FINAL ACCEPTED`** — صفر Scope Expansion · صفر Production Mutation · صفر تغییر Migration. گزارش: `docs/V1.9_FINAL_ACCEPTANCE_REPORT.md`.

### 📁 فایل‌ها

- **جدید:** `docs/V1.9_FINAL_ACCEPTANCE_REPORT.md`
- **به‌روزرسانی:** `ACTION_TRACKER.md` · `MEMORY.md` · `TMS_PROJECT_TRACKER.md`
- **کد/Migration:** صفر تغییر

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۷/۰۴ (2026-09-26) - فاز V1.9 Verification & Hardening (Candidate A)

### 📝 خلاصه اقدامات

- **فاز Verification/Grace فقط اثبات — صفر قابلیت جدید · صفر تغییر Migration · صفر production mutation**:
  - **معماری:** کنترلرها فقط delegate می‌کنند (ModuleService/ModuleStageService/StageProgressApprovalService/WbsPhaseService) — صفر duplicate rule در HTTP/Blade؛ `ProgressReportService` از `scopeActive()` واحد استفاده می‌کند (بدون تعریف دوم Active).
  - **Route Audit:** ۱۸ مسیر V1.9 (GET read-only × ۵ · POST mutation × ۱۳) — **صفر مسیر حذف** برای Project/Module/Stage/Phase (DEC-038/039).
  - **Auth/IDOR:** role middleware + گارد سرویس؛ FormRequest `weight` = prohibited در update ماژول؛ ownership check آیتم↔فاز.
  - **DEC-040/041:** تجمیع stage-based از مسیر مراحل + شمارش علنی وظایف بی‌stage؛ برچسب «مبلغ تأییدشدهٔ مراحل» — صفر محاسبهٔ مالی (grep تأیید شد).
  - **تست:** V19 = 53/144 سبز · Full = **`256 passed · 735 assertions · 0 failed · 2 deprecated`** · Pint PASS · `npm ci`+`npm run build` PASS · manifest موجود.
  - **CI:** `ci.yml` inspected — صفر تغییر لازم؛ Gate 1/2 · 32/0 · manifest gate · tms_testing همه intact.
- **یافته‌ها:** 0 Blocker · 0 Defect · 2 Warning (W-1 TD-2 Policy class — pre-existing؛ W-2 فیلد اختیاری DEC-013 در فرم Task — خارج از scope DEC-039).
- **حکم: GREEN — READY FOR CI RUN VERIFICATION.** گزارش: `docs/V1.9_VERIFICATION_HARDENING_REPORT.md`.

### 📁 فایل‌ها

- **جدید:** `docs/V1.9_VERIFICATION_HARDENING_REPORT.md`
- **به‌روزرسانی:** `ACTION_TRACKER.md` · `MEMORY.md` · `TMS_PROJECT_TRACKER.md`
- **کد:** صفر تغییر (Verification فقط اثبات بود)

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۷/۰۴ (2026-09-26) - فاز V1.9 Implementation — Candidate A

### 📝 خلاصه اقدامات

- **پیاده‌سازی کامل Candidate A** طبق `DEC-039/040/041` — **صفر Migration · صفر تغییر migrations · production دست‌نخورده**:
  - **Module UI:** Controller + ۲ ویو + ۳ FormRequest — createModules/rebalance/update از طریق `ModuleService` (بدون delete UI — DEC-038/039).
  - **Stage + Weight UI:** صفحهٔ مرحله با وضعیت قفل (🔒) + بازتوازن جزئی از طریق `ModuleStageService::rebalance` (مجموع ۱۰۰ حفظ می‌شود).
  - **Stage Approval UI:** propose/adjust/approve/reject/supersede — همه از طریق `StageProgressApprovalService` (قواعد immutable/ceiling/chain در سرویس).
  - **WBS Checklist UI:** ایجاد فاز (default DEC-017)، آیتم‌های چک‌لیست (add/complete/reopen)، تصمیمات ناظر (complete/not_completed/reopen) از طریق `WbsPhaseService`.
  - **Progress Report (TD-1 بسته):** `ProgressReportService` — `SUM(approved_amount)` روی Active approvals؛ خواندن legacy `SUM(tasks.weight)` از KPI مرحله‌ای حذف شد؛ برچسب «آماده پرداخت» → **«مبلغ تأییدشدهٔ مراحل»** (DEC-041)؛ وظایف بی‌stage از گزارش مرحله‌ای حذف ولی شمرده‌شده (DEC-040).
- **تست:** ۵۳ تست جدید در `tests/Feature/V19/` — نتیجهٔ کل: **`256 passed · 735 assertions · 0 failed · 2 deprecated`** (بیس 203/591 دست‌نخورده).
- **Frontend:** `npm ci` + `npm run build` — manifest موجود.

### 📁 فایل‌ها

- **جدید:** `app/Domain/Services/ProgressReportService.php` · `app/Http/Controllers/Web/{ModuleController, ModuleStageController, StageProgressApprovalController, WbsPhaseController}.php` · ۸ FormRequest در `app/Http/Requests/Web/` · `resources/views/modules/{index,show}.blade.php` · `resources/views/modules/stages/show.blade.php` · `resources/views/wbs-phases/{index,show}.blade.php` · `tests/Feature/V19/*` (۵ فایل + Fixtures) · `docs/V1.9_IMPLEMENTATION_REPORT.md`
- **تغییریافته:** `routes/web.php` (+18 مسیر role-protected) · `app/Http/Controllers/Web/ReportController.php` · `resources/views/reports/index.blade.php` · `resources/views/layouts/app.blade.php` (ناوبری) · `MEMORY.md` · `TMS_PROJECT_TRACKER.md`
- **دست‌نخورده:** `database/migrations` (صفر) · CI · `tms` · `tests/Feature/V18` · `app/Domain/Services` موجود (بدون تغییر رفتاری)

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۷/۰۴ (2026-09-26) - فاز V1.9 Owner Decision Registration (Documentation-Only)

### 📝 خلاصه اقدامات

- **ثبت سه تصمیم قطعی مالک پروژه برای V1.9** — فاز صرفاً مستندسازی؛ **صفر تغییر کد/تست/Migration/دیتابیس**:
  - `DEC-039` — **DR-1**: دامنهٔ V1.9 = **Candidate A کامل** (Module CRUD · مدیریت Stage · وزن Stage · Stage Approval UI · WBS Checklist UI · گزارش پیشرفت بر پایهٔ `approved_amount`) — صفر Migration؛ Candidate B/C مجاز نیستند.
  - `DEC-040` — **DR-2**: تسک‌های با `module_stage_id IS NULL` در **گزارش‌های stage-based حذف می‌شوند** (`WHERE module_stage_id IS NOT NULL` در لایهٔ تجمیع) — قاعدهٔ نمایش، نه lifecycle؛ بدون bucket جدید و بدون قاعدهٔ وزن جدید.
  - `DEC-041` — **DR-4/OQ-07**: برچسب گزارش «آماده پرداخت» → **«مبلغ تأییدشدهٔ مراحل»** — بدون هیچ مجوز محاسبهٔ مالی؛ TMS عملیاتی می‌ماند.
- **وضعیت دروازه:** دروازهٔ تصمیم V1.9 (DR-1 · DR-2 · DR-4/OQ-07) **PASSED** — پیاده‌سازی V1.9 **هنوز آغاز نشده** و فاز اجرای مجزا لازم دارد.

### 📁 فایل‌ها

- **به‌روزرسانی:** `docs/V1.9_DECISION_REGISTER.md` (علامت‌گذاری RESOLVED + بخش «تصمیمات تأییدشده») · `project_context/ANTIGRAVITY_DECISIONS.md` (`DEC-039`/`DEC-040`/`DEC-041`) · `MEMORY.md` · `TMS_PROJECT_TRACKER.md`
- **دست‌نخورده:** کل `app/` · `routes/` · `resources/` · `tests/` · `database/migrations` · CI · `tms` · `tms_testing`

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۷/۰۱ (2026-09-23) - فاز V1.8 Owner Decision Gate + T-7 CI Cycle + Closure

### 📝 خلاصه اقدامات

- **Owner Decision Gate (T-3/T-5):** گزارش بی‌طرف گزینه‌ها ساخته شد (`docs/V1.8_OWNER_DECISION_GATE_T3_T5_REPORT.md`) — سپس مالک تصمیم گرفت: **T-3=A** (مدل فعلی) · **T-5=A** (بدون قابلیت حذف) → ثبت در `DEC-038`.
- **T-7 CI — ۴ فاز واقعی روی GitHub Actions:**
  1. Run 35825473271 (a0df128): FAIL — Composer/PHP 8.3 platform mismatch (۴۸ پکیج lock نیازمند 8.4)
  2. Run 35830322842 (31936a1، PHP 8.4): Composer ✅ Gate 1 ✅ Migrate ✅ — Gate 2 silent exit 1 (`grep -c` zero-match زیر `bash -e -o pipefail` + ANSI codes)
  3. Run 35832036047 (163ad93، Gate 2 fix): Gate 2 ✅ (32/0) — Tests: 15 fail همه `ViteManifestNotFoundException`
  4. **Run 35835190435 (22051ea، npm ci+vite build+manifest gate): کاملاً سبز — 203 passed · 591 assertions · 0 failed · 2 deprecated**
- **Closure:** T-1..T-7 + R-1..R-3 همگی بسته — V1.8 Hardening کامل.

### 📁 فایل‌ها
- **workflow:** `.github/workflows/ci.yml` (3 commits: 31936a1 · 163ad93 · 22051ea)
- **گزارش‌ها:** T-7 ×۴ · Owner Decision Gate ×۱
- **دست‌نخورده:** migrations · production · app code (بجز آنچه در commits مجاز ثبت شد) · tests

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۷/۰۱ (2026-09-23) - فاز V1.8 Next Phase Decision & Implementation Plan

### 📝 خلاصه اقدامات

- **فاز برنامه‌ریزی خالص:** صفر تغییر کد · صفر Migration · صفر CI · صفر mutation. فقط سند جدید `docs/V1.8_NEXT_PHASE_DECISION_AND_IMPLEMENTATION_PLAN.md`.
- **Reconnaissance:** `git diff -- database/migrations` خالی (IMMUTABLE) · بررسی مخفی-نبودن پیاده‌سازی: `task_rejected` در `app/` صفر · مسیر حذف Project صفر · `assertNotSuperseded` ×۵.
- **⚠️ وضعیت محیطی:** PostgreSQL unreachable (Connection refused :5432) — `migrate:status`/`db:show` اجرا نشد؛ Full Suite اجرای مجدد TIMEOUT — NOT RUN؛ آخرین وضعیت معتبر: 32/32 Ran · 35 tables · 203/591/0 (2026-09-22).
- **تحلیل‌ها:** T-3 (A/B) · T-5 (A/B/C/D) · T-7 (Spec CI کامل، بدون ساخت فایل) · T-1 (توصیه: KEEP AS-IS).
- **Dependency Matrix:** هر ۴ قلم مستقل؛ ترتیب پیشنهادی T-7 → T-3 → T-5 → T-1.
- **دروازه:** `OWNER DECISION REQUIRED` (۴ تصمیم در §۱۰ سند ثبت شد).

### 📁 فایل‌ها
- **جدید:** `docs/V1.8_NEXT_PHASE_DECISION_AND_IMPLEMENTATION_PLAN.md`
- **به‌روزرسانی:** `MEMORY.md` · `ACTION_TRACKER.md` · `project_context/tasks.md`
- **دست‌نخورده:** کل `app/` · `tests/` · `database/migrations` · `resources/` · `tms` · `tms_testing`

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۶/۳۱ (2026-09-22) - فاز V1.8 T-1 Resolution & Post-Migration Reconciliation

### 📝 خلاصه اقدامات

- **Reconnaissance:** یکپارچگی ۲۳ مهاجرت Baseline تأیید شد (`git diff -- database/migrations` خالی · صفر Deleted/Renamed) · `migrate:status` → **32/32 Ran** · Batch `{1:23, 2:1, 3:8}`.
- **Schema واقعی:** `supersedes_approval_id` روی **هر دو** دیتابیس بررسی شد — nullable، FK با `RESTRICT`، **صفر UNIQUE**، فقط index غیریکتا + CHECK ضد-خودارجاعی.
- **بیس تست واقعی:** `php artisan test` → **175 passed · 2 deprecated · 504 assertions · 0 failed**.
- **اثبات `T-1` با Probe موقت روی PostgreSQL واقعی:**
  - مسیر Database: دو جانشین برای یک ردیف **درج شدند** (بدون UNIQUE).
  - مسیر Service: supersede دوباره **مادامی که جانشین pending است مسدود**، اما **پس از تصمیم جانشین مجاز** ⇒ **fork واقعی**.
  - حالت عددی تعیین‌کننده: `A=5 → B=7 → C=3` → `approvedWeight=10` به‌جای `7` (سقف ۱۵ بود، پس check ظرفیت آن را نگرفت).
- **تست‌های رگرسیون (TDD واقعی):** فایل جدید با **۱۶ تست** **اول علیه کد اصلاح‌نشده** اجرا شد → `8 failed · 8 passed`؛ ۶ شکست نقص واقعی و ۲ شکست خطای خودِ تست بود (اصلاح شد).
- **اصلاح حداقلی (`app/Domain/Services/StageProgressApprovalService.php`):**
  - `supersede()` → `assertNotSuperseded($source)` (اصلاح `T-1`)
  - `adjust()` → `assertNotSuperseded($row)` (اصلاح `T-1b` — تاریخِ ردیف superseded بازنویسی می‌شد، نقض `DEC-027`)
  - پیام خطا عام شد؛ دو Docblock به‌روز شد
  - **صفر تغییر Schema · صفر Migration جدید · صفر تصمیم کسب‌وکاری جدید**
- **Active Definition Audit (مرحله ۸ — فقط گزارش، بدون Refactor):** تعریف «Active/Superseded» در **۵ نقطه** تکرار شده؛ همه **منطقاً سازگار**، ریسک **ساختاری** (`R-3`).
- **Reconciliation (مرحله ۹):** دو یافتهٔ واقعی — `R-1` (`ReportController` هنوز `SUM(tasks.weight)` را گزارش/CSV می‌کند در حالی که Schema آن را بازنشسته و NULL-پذیر کرده) · `R-2` (`task_type` هیچ نویسنده‌ای در کد ندارد ⇒ Support همیشه `development`). هر دو طبق تصمیم مالک **فقط گزارش شدند**.

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۶/۳۱ (2026-09-22) - فاز V1.8 Post-Migration Code Hardening

### 📝 خلاصه اقدامات

- **Reconnaissance (فقط-خواندنی):** `migrate:status` → ۳۲/۳۲ Ran · `{1:23, 2:1, 3:8}` · `db:show` → PG 18.6 · ۳۵ جدول — دقیقاً مطابق مستندات، صفر اختلاف. `git diff -- database/migrations` خالی.
- **R-1 Audit:** ۹ مصرف‌کنندهٔ `tasks.weight` شناسایی شد (ReportController ×۴ · Blade KPI ×۲ · WeightChangeRequestService ×۲ · StoreTaskRequest). ریسک فعال نبود چون مسیر HTTP وزن را `required` نگه می‌دارد.
- **R-2 Audit:** مسیر ایجاد Task واحد است (`TaskController → StoreTaskRequest → CreateTaskData → TaskService → Task::create`)؛ `task_type` هیچ نویسنده‌ای نداشت. Vocabulary قفل‌شده موجود: `TaskType` enum + `chk_tasks_task_type` + `DEC-016/BD-06/OQ-04=2B` ⇒ مسدودکنندهٔ «DOMAIN VOCABULARY REQUIRED» صدق نمی‌کند.
- **R-3 Audit (فقط inventory):** ۵ تعریف «Active/Superseded» — #۳ و #۵ کپی حرفِیِ #۱ و #۲؛ همه هم‌ارز رفتاری. `STRUCTURALLY DUPLICATED BUT BEHAVIORALLY CONSISTENT` · Semantic Drift صفر · **بدون Refactor** (طبق دستور).
- **تصمیمات مالک (۴ مورد، ثبت در `DEC-036` و `DEC-037`):**
  - `R-1` → **`R1-D`** (NULL وزن در مسیر عادی ممنوع) + **`R1-F1`** (weight اجباری می‌ماند) ⇒ صفر تغییر در `ReportController`/CSV؛ invariant با تست قفل شد.
  - `R-2` → **`T-2-A`** (`task_type` Required در FormRequest) + **`T-2-UI-A`** (فیلد «نوع تسک» در فرم).
- **پیاده‌سازی حداقلی (۴ فایل کد):** `StoreTaskRequest` (`required + new Enum(TaskType::class)` + toDto) · `CreateTaskData` (`TaskType $task_type` اجباری) · `TaskService::create` (نویسندهٔ صریح `task_type`) · `tasks/create.blade.php` (فیلد select «نوع تسک»). مسیر کامل بدون silent fallback؛ `DEFAULT 'development'` دیتابیس دست نخورد (نگرانی Backfill طبق `DEC-016`).
- **سازگارسازی تست‌های موجود:** ۴ فایل تست که تسک HTTP می‌ساختند، `task_type` معتبر گرفتند — هیچ assertion ضعیف نشد.

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۶/۳۱ (2026-09-22) - فاز V1.8 Remaining Hardening (R-3 + T-6)

### 📝 خلاصه اقدامات

- **R-3 بسته شد (refactor خالص):** `ModuleStage::approvedQuery()` → `approvals()->active()` و `StageProgressApprovalService::isSuperseded()` → `$approval->isSuperseded()` — هر دو کپی حرفِی بودند؛ رفتار با Suite برابر Baseline اثبات شد (`203 · 591 · 0`). `pendingQuery()` عمداً ادغام نشد (فیلتر status متفاوت) · Trait/Helper رد شد · `Module::scopeActive()` جدا ماند (naming collision).
- **T-6 بسته شد (دو لایه):** ENFORCED — Safety Gate در `tests/TestCase.php` (نام واقعی DB متصل باید `*testing` باشد؛ وگرنه Suite پیش از RefreshDatabase fail می‌شود) · DOCUMENTED — `docs/DATABASE_SAFETY.md` (Safe/destructive commands + قاعدهٔ مجوز مالک برای reset).
- **ایمنی:** صفر Migration · صفر Schema · `tms` فقط read-only · `tms_testing` بدون mutation (هیچ reset/fresh اجرا نشد).

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۶/۳۱ (2026-09-22) - فاز V1.8 Final Hardening (T-3/T-4/T-5/T-7)

### 📝 خلاصه اقدامات

- **صفر تغییر کد** — هیچ‌کدام از چهار item مجوز پیاده‌سازی مستقل نداشت.
- **T-4 CLOSED:** تطبیق migrations ↔ docs ↔ catalog (pg_constraint) — صفر مغایرت رفتاری؛ `>=0` (Baseline Legacy) در برابر `>0` (V1.8) عمدی و مستند (§۴۱۳ طراحی)؛ نام constraintها تطابق ۱:۱.
- **T-3 OWNER DECISION REQUIRED:** ۴ مسیر رد بازرسی شد — همه در همان تراکنش auditable، صفر bypass (DatabaseAuditService failure هرگز swallow نمی‌شود). گزینهٔ A (مدل فعلی) / B (رویداد مستقل، فقط کد+تست) بی‌طرف ثبت شد.
- **T-5 OWNER DECISION REQUIRED / DEFERRED:** DB protection موجود (RESTRICT روی هر ۳ فرزند)؛ قاعدهٔ soft-delete مفقود — و هیچ مسیر حذف Project در کد وجود ندارد (grep کامل).
- **T-7 INFRASTRUCTURE GAP:** CI وجود ندارد؛ توصیهٔ implementation-ready آماده شد (postgres:18 UTF8/C · tms_testing · متغیرها · دستورات · gate · retention)؛ سد TestCase از قبل فعال است.

### 🛠 فایل‌های جدید

**Doc:** `docs/V1.8_FINAL_HARDENING_T3_T4_T5_T7.md`

### ✏️ فایل‌های تغییریافته (کد)

**هیچ.**

### ✅ نتیجه

```text
203 passed · 591 assertions · 0 failed  (baseline بدون تغییر)
New Migration = 0 · Existing Migration Modified = 0
tms untouched · tms_testing untouched (بدون destructive دستی)

OWNER DECISION REQUIRED   (T-3 · T-5 · T-7 · بازتأیید T-1)
```

---

### 🛠 فایل‌های جدید (فاز Remaining Hardening)

**Doc:** `docs/DATABASE_SAFETY.md` · `docs/V1.8_REMAINING_HARDENING_IMPLEMENTATION.md`

### ✏️ فایل‌های تغییریافته

| فایل | تغییر |
|---|---|
| `app/Models/ModuleStage.php` | approvedQuery → delegation به scopeActive + use cleanup |
| `app/Domain/Services/StageProgressApprovalService.php` | isSuperseded → delegation به مدل |
| `tests/TestCase.php` | T-6 Safety Gate (ENFORCED) |

### ✅ نتیجه

```text
203 passed · 591 assertions · 0 failed  (identical to baseline)
New Migration = 0 · Existing Migration Modified = 0
tms untouched · tms_testing untouched

READY FOR NEXT IMPLEMENTATION PHASE
```

---

### 🛠 فایل‌های جدید (فاز Post-Migration Hardening)

**Test:** `tests/Feature/Web/TaskTypeAndWeightPolicyTest.php` (۱۲ تست · ۳۲ assertion)
**Doc:** `docs/V1.8_POST_MIGRATION_CODE_HARDENING_REPORT.md`

### ✏️ فایل‌های تغییریافته

| فایل | تغییر |
|---|---|
| `app/Http/Requests/Web/StoreTaskRequest.php` | `task_type` required + Enum validation + toDto |
| `app/Domain/DTOs/CreateTaskData.php` | `TaskType $task_type` (اجباری) |
| `app/Domain/Services/TaskService.php` | نویسندهٔ صریح `task_type` در create |
| `resources/views/tasks/create.blade.php` | فیلد «نوع تسک» (T-2-UI-A) |
| `tests/Feature/TaskDependencyTest.php` · `WebTaskJalaliDateTest.php` · `TaskServiceTest.php` · `CreateTaskDataTest.php` | +task_type معتبر در payload/DTO |
| `project_context/ANTIGRAVITY_DECISIONS.md` | ثبت `DEC-036` · `DEC-037` |

**تغییرنیافته:** `database/migrations` (صفر) · `ReportController` (عمداً — R1-D) · `StageProgressApprovalService` (T-1 و R-3) · `tms`.

### ✅ نتیجه

```text
203 passed · 2 deprecated · 591 assertions · 0 failed
(از 191 → 203 تست · از 559 → 591 assertion)

New Migration ...................... 0
Existing Migration Modified ........ 0
tms ................................ دست‌نخورده

READY FOR V1.8 POST-MIGRATION HARDENING COMPLETE
```

---

### 🛠 فایل‌های جدید (فاز قبل)

**Test:** `tests/Feature/V18/StageProgressApprovalSupersedeChainTest.php` (۱۶ تست · ۵۵ assertion)

**Docs:** `docs/V1.8_T1_POST_MIGRATION_RECONCILIATION.md`

### ✏️ فایل‌های تغییریافته (فقط ۱ فایل کد)

| فایل | تغییر |
|---|---|
| `app/Domain/Services/StageProgressApprovalService.php` | دو گارد `assertNotSuperseded` + عام‌سازی پیام + دو Docblock |

### ✅ نتیجه

```text
191 passed · 2 deprecated · 559 assertions · 0 failed
(از 175 → 191 تست · از 504 → 559 assertion)

New Migration ...................... 0
Existing Migration Modified ........ 0
tms ................................ دست‌نخورده

READY FOR V1.8 POST-MIGRATION CODE HARDENING
```

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۶/۳۱ (2026-09-22) - فاز V1.8 Production Migration (تأییدشده)

### 📝 خلاصه اقدامات

- **Phase 1 (Integrity):** ۲۳ مهاجرت Baseline تأیید شد `IMMUTABLE` (`git diff -- database/migrations` خالی · صفر Deleted · صفر Renamed).
- **Phase 2/3 (Baseline + SQL):** وضعیت `tms` ثبت شد (`23 migrations · 31 tables · 0 triggers · 0 functions · tasks.weight NOT NULL`) و SQL هر ۹ مهاجرت با `--pretend` بازبینی شد.
- **Phase 4/5 (`T-1`/`T-2`):** `T-1` = **ACCEPTED AS-IS** (تقویت فنی، نه تصمیم کسب‌وکاری؛ recommendation ثبت شد) · `T-2` = **RESOLVED BY DEC-016** (Default بخشی از تصمیم ثبت‌شده است).
- **Phase 6 (Safety Gate):** هر ۱۵ گیت PASS · **پشتیبان `pg_dump` ساخته شد** (`storage/app/backups/tms_pre_v18_20260922_113350.dump` · ۱۰۲KB · gitignored) · صفر اتصال فعال و صفر قفل روی `tms`.
- **آزمایش `H-2` روی `tms_testing`:** دو سناریو واقعاً اجرا شد — **درست** (Batch جدا: `{1:24, 2:8}` → rollback کامل ۸ مهاجرت، exit 0) در مقابل **غلط** (یک Batch: `{1:32}` → ۸ مهاجرت برگشتند سپس M-07 استثنا داد). یافتهٔ دقیق‌شده: خطر **سازگاری‌شکن نیست، اپراتوری است** (ابهام در نتیجهٔ rollback).
- **Phase 7 (Production Migration):** توالی دو مرحله‌ای روی `tms` — `M-07` با `--path` (Batch 2) سپس هشت مهاجرت (Batch 3). نتیجه: **`32 migrations · 35 tables · 4 triggers · 3 functions`** · توپولوژی `{1:23, 2:1, 3:8}`.
- **Phase 8/9/10 (Verification):** تأیید Schema · ۱۰ سنجه Smoke Test در لایهٔ Eloquent (صفر Exception) · مقایسهٔ کامل شمارش ردیف‌ها (صفر تغییر جز `migrations +9` و `system_settings +1`) · MD5 متن فارسی یکسان.
- **Phase 11/12:** گزارش `docs/V1.8_PRODUCTION_MIGRATION_REPORT.md` ساخته شد و اسناد ردیابی به‌روز شدند.

### 🛠 فایل‌های جدید

**Docs:** `docs/V1.8_PRODUCTION_MIGRATION_REPORT.md`

**Backup (gitignored):** `storage/app/backups/tms_pre_v18_20260922_113350.dump`

### ✏️ فایل‌های تغییریافته

**کد / Migration / Test / Config / Routes / Resources: صفر تغییر** (`git diff -- database/migrations` · `app/` · `tests/` خالی و بی‌تغییر در این فاز).

**Markdown:** `docs/V1.8_PRODUCTION_MIGRATION_REPORT.md` · `MEMORY.md` · `TASKS.md` · `TMS_PROJECT_TRACKER.md` · `ERROR_LOG.md` · `ACTION_TRACKER.md` · `project_context/{ANTIGRAVITY_DECISIONS,ANTIGRAVITY_HANDOFF,ANTIGRAVITY_CHANGELOG,ANTIGRAVITY_SESSION_LOG,tasks}.md`

### ✅ نتیجه

```text
PRODUCTION MIGRATION SUCCESSFUL
```

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۶/۳۱ (2026-09-22) - فاز V1.8 Migration Implementation (Incremental)

### 📝 خلاصه اقدامات

- **Baseline Inventory**: هر ۲۳ Migration موجود فهرست‌برداری و **Immutable** اعلام شد؛ **صفر** فایل قدیمی تغییر کرد.
- **۹ فایل Migration جدید** نوشته شد (M-07 در Batch مستقل · M-01..M-06 + M-08 + M-09 در Batch اصلی).
- `php artisan migrate --pretend` ✅ PASS و SQL کامل بازبینی شد.
- اجرای واقعی روی `tms_testing`: **۸ از ۹** مهاجرت DONE · `M-09` مسدود on `ENV-1`.
- **۴ Trigger + ۳ Function + ۲۲ CHECK** ساخته شد؛ **۳۲ سنجهٔ رفتاری با اجرای واقعی SQL اثبات شد**.
- کد دامنه: ۵ Enum · ۶ Exception · ۴ Model · ۴ سرویس + `DatabaseAuditService`.
- تست: **۵۹ سناریو** جدید در `tests/Feature/V18/`.
- `tms` (توسعه/Production-like) **بیت‌به‌بیت دست‌نخورده** ماند.

### 🛠 فایل‌های جدید

**Migrationها:** `2026_09_22_100001_relax_tasks_weight_nullable.php` · `100002_create_modules_table.php` · `100003_create_module_stages_table.php` · `100004_create_stage_progress_approvals_table.php` · `100005_create_wbs_phase_checklist_items_table.php` · `100006_add_v18_columns_to_tasks.php` · `100007_add_v18_columns_to_wbs_phases.php` · `100008_create_v18_single_row_integrity_triggers.php` · `100009_seed_v18_reference_data.php`

**Enums:** `TaskType` · `ModuleStatus` · `ModuleStageCode` · `StageProgressApprovalStatus` · `WbsPhaseCompletionStatus`

**Exceptions:** `ModuleWeightException` · `StageWeightException` · `StageWeightLockedException` · `StageProgressCapacityExceededException` · `StageApprovalAuthorizationException` · `WbsPhaseTransitionException`

**Models:** `Module` · `ModuleStage` · `StageProgressApproval` · `WbsPhaseChecklistItem`

**Services:** `DatabaseAuditService` · `ModuleService` · `ModuleStageService` · `StageProgressApprovalService` · `WbsPhaseService`

**Tests:** `tests/Feature/V18/{ModuleSchemaTest, ModuleDomainServiceTest, StageProgressApprovalTest, WbsPhaseAndAuditTest}.php` + `Concerns/V18Fixtures.php`

**Docs:** `docs/V1.8_MIGRATION_BASELINE_RECONCILIATION.md`

### ✏️ فایل‌های تغییریافته (حداقلی)

| فایل | تغییر |
|---|---|
| `app/Providers/AppServiceProvider.php` | Binding از `NullAuditService` → `DatabaseAuditService` (`DEC-011`) |
| `app/Domain/Services/DocumentService.php` | فراخوانی صریح `document_uploaded` در همان تراکنش (`DEC-032`) |
| `app/Models/Project.php` | `modules()` · `activeModules()` |
| `app/Models/Task.php` | casts + `moduleStage()` · `module()` |
| `app/Models/WbsPhase.php` | casts + `checklistItems()` · `supervisorApprover()` |

### ✅ یافتهٔ طراحی که در همین فاز اصلاح شد

| ID | یافته | اصلاح |
|---|---|---|
| `AP-1` | `archive`/`restore` بدون ورودی بازتوازن **هیچ مسیر قانونی برای کاهش تعداد ماژول نداشتند** (حذف ماژول ۲۵٪ از ۴۰/۳۵/۲۵ ⇒ ۷۵ و رد؛ `rebalance` هم فقط Active را می‌بیند و ۶۰+۴۰+۲۵=۱۲۵ را رد می‌کند) | 🔴 **اصلاح شد** — امضا به `archive(Module, User, array $remainingWeights = [])` و `restore(Module, User, array $activeWeights = [])` تغییر کرد تا بازتوازن **در همان تراکنش** رخ دهد (طبق `R-4` · `DEC-022`) + ۳ تست |

### 🚫 ممنوعیت‌های رعایت‌شده

- صفر تغییر در ۲۳ Migration موجود · صفر `DROP COLUMN` · صفر `DROP TABLE` · صفر `RENAME` · صفر `TRUNCATE`.
- صفر اجرای Migration روی `tms` · صفر تغییر در رمزگذاری/تنظیمات سرور PostgreSQL.
- صفر `DROP DATABASE` (به همین دلیل `ENV-1` رفع نشد).
- صفر Decision کسب‌وکاری جدید · صفر Registry موازی.

### ⛔ مانع محیطی و رفع آن

```text
ENV-1 — tms_testing با رمزگذاری WIN1252 ساخته شده بود
      → M-09 و کل Test Suite مسدود
```

* **کشف:** `SELECT pg_encoding_to_char(encoding)` نشان داد `tms_testing = WIN1252` در حالی که `tms = UTF8`.
* **گزارش:** با شواهد کامل (خطای `SQLSTATE[22P05]`، تکرار در ۳ مسیر، دامنهٔ اثر، خطر خاموش mojibake).
* **رفع (با مجوز صریح مالک پروژه):**

```sql
DROP DATABASE tms_testing;
CREATE DATABASE tms_testing WITH TEMPLATE template0 ENCODING 'UTF8' LC_COLLATE 'C' LC_CTYPE 'C';
```

* دیتابیس تست و خالی بود ⇒ صفر داده از دست رفت. `tms` **دست‌نخورده**.

### ✅ نتیجهٔ واقعی پس از رفع

| مورد | نتیجه |
|---|---|
| `DB_DATABASE=tms_testing php artisan migrate` | ✅ **۳۲/۳۲ DONE** |
| `php artisan test` | ✅ **`175 passed · 2 deprecated · 504 assertions · 0 failed`** (26.26s) |
| `php artisan test tests/Feature/V18` | ✅ **۶۳ passed (170 assertions)** |
| `tms` | ✅ بیت‌به‌بیت دست‌نخورده |

### 🔴 دو نقص که خود تست‌ها کشف کردند (و رفع شدند)

| ID | نقص | اصلاح |
|---|---|---|
| `BUG-1` | `ModuleStageService::rebalance()` مجموع **map** را با ۱۰۰ مقایسه می‌کرد نه مجموع **نهایی** ماژول ⇒ جابه‌جایی وزن بین دو مرحلهٔ نُه‌گانه غیرممکن بود | محاسبهٔ `resultingSum` از کل مجموعهٔ مرحله‌ها؛ map به مجموعهٔ **جزئی** تبدیل شد |
| `BUG-2` | ردیف‌های **superseded** از «یک pending» و از مسیر تصمیم استثنا نمی‌شدند | `NOT EXISTS` در `pendingQuery()` + `assertNotSuperseded()` در `approve`/`reject` |

> 📌 هر دو فقط با **اجرای واقعی** ۶۳ سناریو کشف شدند (نه بازبینی ایستا). ریشهٔ مشترک: ناهمگونی تعریف «Active» در لایه‌های مختلف.

### ✅ حکم نهایی

```text
READY FOR PRODUCTION MIGRATION REVIEW
```

> ⚠️ مجوز اجرا روی `tms` نیست (§۳۴) — نیازمند مجوز جداگانهٔ صریح.

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۶/۳۱ (2026-09-22) - فاز V1.8 Owner Decision Closure & Design Reconciliation

### 📝 خلاصه اقدامات

- ثبت **۱۶ تصمیم قطعی مالک پروژه** در Decision Registry واحد (`DEC-020`..`DEC-035`) و بستن **تمام** پرسش‌های باز.
- اعمال **۱۶ اصلاح طراحی** در `docs/V1.8_DETAILED_SCHEMA_DESIGN.md` (شامل بازنویسی کامل §۶ و افزودن دکترین §۶.۸).
- تولید گزارش تطبیق نهایی `docs/V1.8_FINAL_DESIGN_RECONCILIATION.md` (۱۲ بخش).
- 🚫 **صفر تغییر کد · صفر Migration (نوشته یا اجراشده) · صفر SQL mutation · صفر تغییر Schema · صفر تغییر داده.**

### ✅ تصمیمات کلیدی (خلاصه)

| مورد | تصمیم |
|---|---|
| `OQ-30` | گزینهٔ **D** — ۳ Trigger تک‌ردیفی ساخته می‌شود؛ ۳ Trigger تجمعی **DEFERRED** |
| `OQ-24` | گزینهٔ **A** — `tasks.module_id` می‌ماند؛ تضمین در Service؛ **بدون Trigger** |
| `H-1` | حذف کوئری نامعتبر `SUM(...) FOR UPDATE` → **قفل والد Stage** + تجمیع بدون `FOR UPDATE` |
| `H-2` | `M-07` در **Batch مستقل و قدیمی‌تر**؛ `DEC-018` دست‌نخورده |
| `H-3` | اتمیک‌بودن: یک عملیات = یک تراکنش؛ وضعیت میانی غیرقابل Commit |
| `H-4` | **دکترین قفل والد** (`projects.id` / `modules.id` / `module_stages.id`) |
| `H-5` | **صفر ماژول فعال معتبر**؛ invariant روی مجموعهٔ Active |
| `N-1` | **`superseded` وضعیت مشتق**؛ تعریف Active Approval با `NOT EXISTS` |
| `N-4` | دکترین: Service مالک Business Rule · DB مالک Integrity تک‌ردیفی |
| `OQ-31` · `OQ-25` · `OQ-02` | ✅ RESOLVED |

### 🛠️ فایل‌های ایجاد / تغییر یافته (فقط `*.md`)

```text
docs/V1.8_FINAL_DESIGN_RECONCILIATION.md        🆕 (گزارش ۱۲ بخشی)
docs/V1.8_DETAILED_SCHEMA_DESIGN.md             🔧 (§۲.۵ · §۴.۱ · §۴.۳ · §۵.۲ · §۶.۱–§۶.۸ · §۸.۲ · §۸.۳ ·
                                                    §۹.۱ · §۹.۲ · §۹.۴ · §۱۲ · §۱۴ · §۱۵ · پیوست د)
docs/V1.8_OQ_AND_REVIEW_ISSUE_RESOLUTION.md     🔧 (بنر بسته‌شدن + جدول ۱۷ موردی)
docs/V1.8_MIGRATION_REVIEW_CHECKLIST.md         🔧 (همهٔ بندها APPROVED/RESOLVED)
docs/10-database-design.md                      🔧 (دکترین · `supersedes_approval_id` · SR-08/SR-09 · دروازه)
project_context/ANTIGRAVITY_DECISIONS.md        🔧 (DEC-020..DEC-035)
project_context/ANTIGRAVITY_HANDOFF.md          🔧 (بازنویسی کامل)
project_context/tasks.md                        🔧 (فازهای ۱۸.۵ · ۱۸.۶ · ۱۹ · ۲۰)
project_context/ANTIGRAVITY_CHANGELOG.md        🔧
project_context/ANTIGRAVITY_SESSION_LOG.md      🔧
MEMORY.md                                        🔧
ACTION_TRACKER.md                                🔧
```

> ℹ️ `TMS_PROJECT_TRACKER.md` طبق قاعدهٔ تفکیک نقش‌ها (Architect/Manager در برابر Developer) در این مرحله تغییر **نکرد**.

### 🧪 تست‌ها

```text
هیچ تستی اجرا نشد و هیچ SQLی روی Database اجرا نشد.

علت: این مرحله صرفاً Decision Closure + Design Reconciliation (مستندسازی) بود.

وضعیت محیط (تأییدشده در مرحلهٔ Migration Review — نه در این مرحله):
  PostgreSQL 18.6 · PID 22436 · pg_isready → accepting connections
  tms و tms_testing موجود · اتصال Laravel تأییدشده · ۲۳/۲۳ Migration
  صفر جدول V1.8 · tasks.weight هنوز NOT NULL (M-07 اجرا نشده — درست)

اجرای واقعی Unit (مرحلهٔ پیشین): 16 passed · 1 deprecated · 51 assertions
بیس «۱۱۴ تست / ۳۳۴ assertion» → هنوز UNVERIFIED — نه PASS و نه FAIL
هیچ نتیجه‌ای ساخته، تخمین زده یا جعل نشد.
```

### وضعیت
- COMPLETE (Documentation Only) — دروازه: `READY FOR MIGRATION IMPLEMENTATION REVIEW`

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۶/۳۱ (2026-09-22) - فاز V1.8 OQ & Review Issue Resolution

### 📝 خلاصه اقدامات

- حل‌وفصل کامل **فقط خواندنی** پنج پرسش ساختاری باز (`OQ-30` · `OQ-31` · `OQ-24` · `OQ-25` · `OQ-02`) و هفت یافتهٔ 🟠 (`H-1`..`H-7`) — سند: `docs/V1.8_OQ_AND_REVIEW_ISSUE_RESOLUTION.md`.
- 🚫 **صفر Migration، صفر تغییر کد، صفر Schema Mutation، صفر تغییر داده، صفر SQL تغییردهنده.** همهٔ بررسی‌ها `SELECT` روی `information_schema`/`pg_tables` و فرمان‌های خواندنی Laravel بودند.
- ⛔ **هیچ پرسش بازی خودبه‌خود حل نشد** — هر مورد یا `RECOMMENDATION READY` است یا `OWNER DECISION REQUIRED`. هیچ قاعدهٔ کسب‌وکاری جدیدی اختراع نشد.
- ✅ **صفر بازگشت:** هیچ راه‌حلی از ۹ عنصر ممنوعه (Task Weight · WBS Phase Weight · جفت‌شدگی WBS↔Module · Support به‌عنوان Phase · درصد پیشرفت تسک · فرمول مالی · منبع وزن دوگانه · تأیید خودکار · فرمول پرداخت) را بازنگرداند.
- 🆕 **پنج یافتهٔ جدید:** `N-1` تناقض درونی مدل `supersede` با Immutability و سقف ظرفیت · `N-2` بدنهٔ تابع Trigger تجمعی برای `INSERT`/`DELETE` خطا می‌دهد (`TG_OP`) · `N-3` نبود تضمین هم‌پروژه‌بودن Stage برای تسک · `N-4` تنش دکترین Service-vs-DB · `N-5` هر ۲۳ Migration موجود در `Batch 1`.
- 🔧 **اصلاح ۱۵ بند کهنه** در `docs/10-database-design.md` (`kind` · `wbs_phase_checklist_items` · `supervisor_approved_*` · `actual_completion` · `task_type` · وضعیت‌های Weight · اشاره‌گر دروازه). سه بند مبهم **اصلاح نشد** و فقط گزارش شد.
- 🟠 **حکم این فاز: `BLOCKED — OWNER DECISION REQUIRED`** (نه `DESIGN ISSUE` و نه `ENVIRONMENT`).

### 📊 جمع‌بندی تصمیم‌ها

```text
OWNER DECISION REQUIRED .... 6   (OQ-30 · OQ-24 · H-3 · H-4 · H-5 · N-4)
RECOMMENDATION READY ....... 9   (OQ-31 · OQ-25 · OQ-02 · H-1 · H-2 · H-6 · N-1 · N-2 · N-3)
DEFERRED ................... 0
DOCUMENTATION ONLY ......... 2   (H-7 · N-5)
RESOLVED بدون تصمیم مالک ... 0   ← هیچ پرسشی خاموش حل نشد
Critical Blockers .......... 1   (B-1 — همان BLOCKER پیشین، اکنون به ۶ تصمیم + ۹ توصیه تفکیک شده)
V1.8 MIGRATION REVIEW  →  BLOCKED — OWNER DECISION REQUIRED
```

### 🛠️ فایل‌های تغییر یافته

```text
docs/V1.8_OQ_AND_REVIEW_ISSUE_RESOLUTION.md   ← 🆕 سند اصلی این فاز
docs/10-database-design.md                    ← اصلاح ۱۵ بند کهنه (H-7) + بنر بازبینی کهنگی
project_context/ANTIGRAVITY_DECISIONS.md      ← پیوست «در انتظار تصمیم مالک» (بدون DEC جدید)
project_context/ANTIGRAVITY_HANDOFF.md
project_context/ANTIGRAVITY_CHANGELOG.md
project_context/ANTIGRAVITY_SESSION_LOG.md
project_context/tasks.md
MEMORY.md
ACTION_TRACKER.md
ERROR_LOG.md
```

### ⛔ تأیید عدم تغییر (غیر‌Markdown)

```text
Migration ایجاد/تغییر‌یافته:  صفر
database/**، app/**، routes/**، resources/**، tests/**، config/** : صفر تغییر
Schema Mutation:              صفر
تغییر داده:                    صفر
```

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۶/۳۱ (2026-09-22) - فاز V1.8 Migration Review (اجرای چک‌لیست)

### 📝 خلاصه اقدامات

- اجرای چک‌لیست `docs/V1.8_MIGRATION_REVIEW_CHECKLIST.md` (۳۸ بند در ۵ محور) روی اسناد طراحی + **بازبینی مستقیم و مستقل Repository**.
- 🚫 **صفر Migration، صفر تغییر کد، صفر Schema Mutation، صفر تغییر داده، صفر SQL تغییردهنده.** همهٔ بررسی‌های دیتابیس `SELECT` روی `information_schema`/`pg_database` و فرمان‌های خواندنی Laravel بودند.
- ✅ تأیید شد که **هیچ‌یک از تصمیمات کسب‌وکاری قفل‌شده نقض نشده است** (صفر مغایرت).
- ✅ **محور Environment بسته شد:** `tms` و `tms_testing` **موجودند** · اتصال Laravel تأیید شد · `migrate:status` → **۲۳/۲۳ Ran** · صفر جدول V1.8 · `tasks.weight` هنوز `NOT NULL`.
- ✅ طبقه‌بندی Legacy با شمارش واقعی مصرف‌کننده‌ها **بازتولید و تأیید شد**.
- ✅ ترتیب ۹ Migration از نظر وابستگی FK **صحیح** است.
- 🧪 بیس تست: `--testsuite=Unit` → **۱۶ passed / ۱ deprecated / ۵۱ assertions** (اجرای واقعی) · Test Suite کامل → `NOT RUN — would require schema mutation` · «۱۱۴/۳۳۴» → **UNVERIFIED** (هیچ نتیجه‌ای جعل نشد).
- 🔴 **حکم: `BLOCKED — REVIEW ISSUE`** — نه `BLOCKED — ENVIRONMENT`.

### 🧾 یافته‌ها (۲۲ مورد + ۵ پرسش باز)

| شدت | تعداد | شناسه‌ها |
|---|---|---|
| 🔴 BLOCKER | **۱** | `B-1` — پنج پرسش ساختاری 🟡 پاسخ نگرفته‌اند (شرط §۶.۳ چک‌لیست برای نوشتن هر فایل Migration) |
| 🟠 HIGH | **۷** | `H-1` کوئری سقف تجمعی §۶.۵ نامعتبر است (`FOR UPDATE` + تجمیع) · `H-2` توقف `migrate:rollback` در `M-07` · `H-3` ایجاد تدریجی Module ناممکن · `H-4` Write Skew در Trigger تعویق‌شده · `H-5` فرار با Soft Delete · `H-6` پیاده‌سازی Audit صفر + `document_uploaded` غایب · `H-7` تضاد `docs/10-database-design.md` با Schema قفل‌شده |
| 🟡 MEDIUM | **۷** | `M-1` Default ماندگار `task_type` · `M-2` مصرف‌کنندهٔ UI `lock_weight` · `M-3` Backfill فرضی `OQ-26` · `M-4` بررسی تجمعی per-row · `M-5` نبود رویداد اختصاصی `task_rejected` · `M-6` انسجام ضعیف `tasks.module_id` · `M-7` ناهم‌خوانی `CHECK IN` |
| 🟢 INFO | **۷** | `I-1`..`I-7` (تأیید Legacy · شمارش مصرف‌کننده‌ها · نبود Model/Enum جدید · WeightChangeRequest بدون UI · شواهد محیطی · وضعیت بیس تست · وراثت `DB_USERNAME` از `.env`) |

### 🛠️ فایل‌های ایجاد / تغییر یافته (فقط `*.md`)

```text
docs/V1.8_MIGRATION_REVIEW_REPORT.md               🆕 (گزارش ۲۰ بخشی + طبقه‌بندی یافته‌ها + دروازهٔ نهایی)
docs/V1.8_MIGRATION_REVIEW_CHECKLIST.md            🔧 (وضعیت بندها + §۶.۳ شرط ورود + §۶.۴ نتیجهٔ اجرا + محور Environment)
project_context/ANTIGRAVITY_HANDOFF.md             🔧 (وضعیت، موانع، اقدامات)
project_context/tasks.md                           🔧 (فاز ۱۸ تکمیل شد · فاز ۱۹ با موانع مشخص مسدود است)
MEMORY.md                                          🔧
ACTION_TRACKER.md                                  🔧
ERROR_LOG.md                                       🔧 (محیط تأیید شد · بیس تست · شکاف `document_uploaded`)
```

> ℹ️ **هیچ قاعدهٔ کسب‌وکاری جدیدی ثبت نشد** و هیچ تصمیم باز 🟡 از خودمان پاسخ نگرفت. `docs/10-database-design.md` **عمداً تغییر نکرد** — در فهرست فایل‌های مجاز این فاز نبود (یافتهٔ `H-7`).

### 🧪 تست‌ها

```text
php artisan test --testsuite=Unit --compact
→ 1 deprecated, 16 passed (51 assertions) · Duration: 4.96s
   (پیش‌شرط ایمنی: tests/Unit صفر ارجاع به RefreshDatabase / DB:: دارد)

php artisan test --compact
→ NOT RUN — would require schema mutation
   (phpunit.xml → pgsql/tms_testing · tests/Pest.php → RefreshDatabase برای همهٔ Featureها)

بیس «۱۱۴ تست / ۳۳۴ assertion» → UNVERIFIED — نه PASS و نه FAIL.
هیچ نتیجه‌ای ساخته، تخمین زده یا جعل نشد. هیچ fallback به SQLite پذیرفته نشد.
```

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۶/۳۱ (2026-09-22) - فاز V1.8 Migration Review Inputs Closed

### 📝 خلاصه اقدامات

- بستن سه پرسش 🔴 پایانی که ورودی مرحلهٔ Migration بودند — با تصمیم قطعی مالک پروژه (`DEC-016` · `DEC-017` · `DEC-018`).
- 🚫 **صفر تغییر کد، صفر Migration، صفر SQL روی Database، صفر تغییر داده، صفر عملیات مخرب.**
- ساخت چک‌لیست رسمی Migration Review: `docs/V1.8_MIGRATION_REVIEW_CHECKLIST.md` — **۳۸ بند در ۵ محور**.
- بازساختار بخش Open Questions سند Schema: **هیچ پرسش حل‌شده‌ای دیگر به‌عنوان blocker نمایش داده نمی‌شود** (🔴 = صفر).
- تثبیت دروازه `READY FOR MIGRATION REVIEW` با تصریح **«مجوز اجرای Migration نیست»** و جداسازی Review از Implementation.
- مشاهدهٔ محیطی (فقط خواندنی): سرور PostgreSQL اینک روی `127.0.0.1:5432` در حال شنیدن است — **برخلاف وضعیت ثبت‌شدهٔ فاز قبل (`NO`)**.
- علامت‌گذاری دروازهٔ `BLOCKED` در دو سند قدیمی به‌عنوان **تاریخی (SUPERSEDED)**.

### ✅ سه تصمیم قطعی ثبت‌شده

| پرسش | پاسخ | مرجع |
|---|---|---|
| `OQ-27` | Backfill `task_type = 'development'` برای تسک‌های موجود؛ Support از V1.8 رسمی می‌شود | `DEC-016` |
| `OQ-28` | `expected_output = "مشاهدهٔ Checklist"` برای فازهای جدید؛ Checklist مرجع واقعی خروجی است | `DEC-017` |
| `OQ-29` | `down()` مهاجرت `M-07` **آگاهانه `throw`** می‌کند؛ بدون هیچ دادهٔ مصنوعی | `DEC-018` |

### 🛠️ فایل‌های ایجاد / تغییر یافته (فقط `*.md`)

```text
docs/V1.8_MIGRATION_REVIEW_CHECKLIST.md            🆕 (چک‌لیست ۵ محوری Migration Review)
docs/V1.8_DETAILED_SCHEMA_DESIGN.md                🔧 (§۲.۴ · §۹.۴ · §۱۱.۳ · §۱۱.۵ · §۱۲.۳ · §۱۳.۴ · §۱۴ · §۱۵ · پیوست ج)
docs/V1.8_RECONCILIATION_AND_MIGRATION_GATE.md     🔧 (دروازهٔ BLOCKED → تاریخی/SUPERSEDED)
docs/10-database-design.md                         🔧 (وضعیت پیوست → READY FOR MIGRATION REVIEW)
project_context/ANTIGRAVITY_DECISIONS.md           🔧 (DEC-016 تا DEC-019 + به‌روزرسانی DEC-015)
project_context/ANTIGRAVITY_CHANGELOG.md           🔧
project_context/ANTIGRAVITY_SESSION_LOG.md         🔧
project_context/ANTIGRAVITY_HANDOFF.md             🔧 (بازنویسی کامل وضعیت و اقدام بعدی)
project_context/tasks.md                           🔧 (فاز ۱۸: سه پرسش 🔴 بسته شد)
MEMORY.md                                          🔧
ACTION_TRACKER.md                                  🔧
```

> ℹ️ **توجه:** `TMS_PROJECT_TRACKER.md` طبق قاعدهٔ تفکیک نقش‌ها (Architect/Manager در برابر Developer) در این فاز تغییر **نکرد**.

### 🧪 تست‌ها

```text
هیچ تستی اجرا نشد.

علت: اجرای تست مستلزم اجرای SQL روی Database است و این فاز صریحاً ممنوع کرده بود.
      (ممنوعیت‌ها: هیچ SQL اجرایی روی Database · هیچ تغییری در داده)

مشاهدهٔ محیطی (فقط خواندنی — بدون تغییر):
  - netstat -ano                → پورت ۵۴۳۲ در حالت LISTENING (127.0.0.1 و [::1])
  - pg_isready -h 127.0.0.1 -p 5432 → accepting connections (exit=0)
  - Get-Process -Id 22436       → postgres

وضعیت ثبت‌شده:
  PostgreSQL server reachable:   YES   ← تازه مشاهده شد
  Database reachable (tms):      NOT VERIFIED
  Migrations executable:         NOT ATTEMPTED
  Tests executable:              NOT ATTEMPTED

بیس «۱۱۴ تست / ۳۳۴ assertion» → نامعلوم (UNVERIFIED) — نه PASS و نه FAIL
هیچ نتیجه‌ای ساخته، تخمین زده یا جعل نشد.
هیچ fallback به SQLite برای «سبز کردن» وضعیت پذیرفته نشد.
```

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۶/۳۰ (2026-09-21) - فاز V1.8 Final Reconciliation

### 📝 خلاصه اقدامات

- اجرای فاز **Final Business Decision Reconciliation & Migration Gate** — تطبیق تصمیمات قطعی مالک پروژه با سابقهٔ موجود Repository.
- 🚫 **صفر تغییر کد:** هیچ Migration، Model، Service، Controller، Route، Blade یا Test ایجاد یا تغییر نکرد. هیچ تنظیمات سیستمی تغییر نکرد (فقط خوانده شد).
- تثبیت نهایی `BD-01`..`BD-07` در `DEC-009` — شامل سه قید تازه: «WBS Phase برای محاسبهٔ Weight استفاده نمی‌شود»، «مدل Boolean برای Stage ممنوع است»، و «Supervisor می‌تواند مقدار را تغییر دهد و سپس تأیید کند».
- حل **۱۱ مورد** از پرسش‌های باز **فقط از سابقهٔ Repository** — بدون اختراع هیچ قاعدهٔ کسب‌وکاری جدید (`DEC-010`).
- اعتبارسنجی معماری Step 2: **هر ۶ بند معتبر، صفر مغایرت.**
- تحلیل طبقه‌بندی Task: `task_type` **خودسرانه اضافه نشد**؛ به‌عنوان Business Decision گزارش شد (`OQ-04`).
- تأیید مانع Audit و ثبت آن به‌عنوان گام صفر پیاده‌سازی (`DEC-011`).
- بررسی محیط: PostgreSQL در دسترس نیست؛ **هیچ نتیجهٔ تستی جعل نشد**.
- اعلام دروازه: `BLOCKED — BUSINESS DECISION REQUIRED` — **دقیقاً ۳ پرسش** (`DEC-012`).

### 🔍 پاسخ OQها و محل استخراج

| OQ | نتیجه | محل استخراج |
|---|---|---|
| `OQ-03` | ✅ بدون رابطهٔ WBS Phase ↔ Module | دیاگرام Step 2 مالک پروژه + BD-02 |
| `OQ-06` | ✅ فقط یک `pending` | `TMS_PROJECT_TRACKER.md §13` + `WeightChangeRequestService.php:24-26` + تست |
| `OQ-06-a` | ✅ `module_id` Denormalized حفظ می‌شود | الگوی `tasks.contract_id`/`.contractor_id` + `§3` |
| `OQ-06-b` | ✅ `approved_amount` می‌تواند متفاوت باشد | BD-05 |
| `OQ-07` | 🟡 برچسب باید تغییر کند (غیرمسدودکننده) | `§2` «No financial payment formula» + `01-project-overview.md:83` |
| `OQ-12` | 🟢 غیرمسدودکننده | `documents.attachable_type VARCHAR(255)` آزاد + `Task::documents()` |
| `OQ-05` قدیمی | ⚠️ منقضی | `SystemSettingSeeder` (توصیف `lock_weight`) + `§13` + `database_physical_design_v1.5.md` |
| `OQ-01a`, `OQ-04`, `OQ-05` جدید | ⏳ **باقی‌مانده** | سابقه‌ای در Repository وجود نداشت |

### 🛠️ فایل‌های ایجاد / تغییر یافته (فقط `*.md`)

```text
docs/V1.8_RECONCILIATION_AND_MIGRATION_GATE.md    🆕 (گزارش ۱۱ بخشی + دروازهٔ نهایی)
docs/V1.8_WEIGHT_MODULE_ARCHITECTURE_AUDIT.md     🔧 (اشاره به سند تطبیق در §17)
project_context/ANTIGRAVITY_DECISIONS.md          🔧 (DEC-009 تا DEC-012)
project_context/ANTIGRAVITY_CHANGELOG.md          🔧
project_context/ANTIGRAVITY_SESSION_LOG.md        🔧
project_context/ANTIGRAVITY_HANDOFF.md            🔧 (بازنویسی کامل وضعیت و اقدام بعدی)
project_context/tasks.md                          🔧
TMS_PROJECT_TRACKER.md                            🔧 (D-14..D-23)
MEMORY.md                                         🔧
ACTION_TRACKER.md                                 🔧
TASKS.md                                          🔧
ERROR_LOG.md                                      🔧
```

### 🧪 تست‌ها

```text
هیچ تستی اجرا نشد.

علت: PostgreSQL در دسترس نیست.
  - netstat → هیچ شنونده‌ای روی پورت ۵۴۳۲
  - pg_isready -h 127.0.0.1 -p 5432 → no response (exit=2)
  - Get-Service برای postgres → خالی
  - پورت‌های ۵۴۳۰–۵۴۳۵ → خالی
  - psql --version → 18.6 (کلاینت نصب است، سرور نیست)

بیس «۱۱۴ تست / ۳۳۴ assertion» → نامعلوم (UNVERIFIED)
هیچ نتیجه‌ای ساخته، تخمین زده یا جعل نشد.
هیچ تغییری در کد انجام نشده که بخواهد رگرسیون ایجاد کند.
```

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۶/۳۰ (2026-09-21) - فاز V1.8 Design Freeze (Weight / Module / WBS)

### 📝 خلاصه اقدامات

- اجرای ممیزی **READ-ONLY** کامل Repository برای تطبیق تصمیمات قطعی مالک پروژه (BD-01..BD-23) با معماری موجود.
- 🚫 **صفر تغییر کد:** هیچ Migration، Model، Service، Controller، Route، Blade یا Test ایجاد یا تغییر نکرد. هیچ Schema تغییر نکرد.
- تثبیت مدل نهایی: Weight متعلق به `Module / Deliverable` و `Module Stage` است، نه Task.
- کشف تفکیک مفهومی کلیدی: **WBS Phase و Module Stage دو موجودیت متفاوت‌اند** و جدول `wbs_phases` فعلی هم‌زمان تلاش می‌کند هر دو باشد (DEC-004).
- کشف مانع بحرانی: **Audit Trail در محیط اجرا مرده است** — `AuditServiceInterface` به `NullAuditService` بایند شده و `activity_logs` هرگز پر نمی‌شود (DEC-007).
- کشف تناقضات مستنداتی: قاعدهٔ «مجموع وزن = 100» هرگز پیاده نشده؛ `01-project-overview.md` بند ۶ متناقض؛ علامت Resolved نادرست برای Dashboard widgets.
- تلاش برای اجرای تست → شکست به دلیل عدم اجرای PostgreSQL (`97 failed, 16 passed`, همه `Connection refused`).
- ثبت ۶ ADR جدید و اعلام دروازهٔ مهاجرت: `BLOCKED — BUSINESS DECISION REQUIRED`.

### 🛠️ فایل‌های ایجاد / تغییر یافته

```text
docs/V1.8_WEIGHT_MODULE_ARCHITECTURE_AUDIT.md      🆕 (گزارش ممیزی ۲۰ بخشی + Migration Gate)
docs/weight-module-design-v1.8.md                  🆕
docs/module-stage-approval-design-v1.8.md          🆕
docs/wbs-phase-completion-design-v1.8.md           🆕
docs/dashboard-data-requirements-v1.8.md           🆕
docs/10-database-design.md                         🔧 (از خالی پر شد)
docs/01-project-overview.md                        🔧 (رفع ۴ تناقض مستنداتی + بخش V1.8)
project_context/ANTIGRAVITY_DECISIONS.md           🔧 (DEC-003 تا DEC-008)
project_context/ANTIGRAVITY_CHANGELOG.md           🔧
project_context/ANTIGRAVITY_SESSION_LOG.md         🔧
project_context/ANTIGRAVITY_HANDOFF.md             🔧 (بازنویسی کامل)
project_context/tasks.md                           🔧
TMS_PROJECT_TRACKER.md                             🔧 (D-13 Resolved، D-12 Reopened، D-14..D-21)
MEMORY.md                                          🔧
ACTION_TRACKER.md                                  🔧
TASKS.md                                           🔧 (تکمیل Placeholderها)
```

### ⚠️ موانع فعال ثبت‌شده (نیازمند اقدام در فاز بعد)

1. **`NullAuditService`** — گام صفر V1.8a باید `DatabaseAuditService` بسازد و بایند را عوض کند.
2. **PostgreSQL خاموش** — بیس تست تأییدنشده؛ باید در `ERROR_LOG.md` ثبت شود.
3. **۷ پرسش باز** (`OQ-01`, `OQ-03`, `OQ-04`, `OQ-05`, `OQ-06`, `OQ-07`, `OQ-12`) — مسدودکنندهٔ طراحی Schema.

### 🧪 تست‌ها

```text
php artisan test --compact
→ 1 deprecated, 97 failed, 16 passed (51 assertions) — Duration: 1594.48s

علت: SQLSTATE[08006] [7] connection to server at "127.0.0.1", port 5432 failed: Connection refused
      pg_isready → "127.0.0.1:5432 - no response"

نتیجه: بیس «۱۱۴ تست / ۳۳۴ assertion» در این فاز تأیید نشد. مانع محیطی، نه رگرسیون کد.
هیچ تغییری در کد انجام نشده که بخواهد رگرسیون ایجاد کند.
```

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۶/۳۰ (2026-09-20) - فاز تثبیت وب (Web Stabilization Phase)

### 📝 خلاصه اقدامات
- اصلاح فراخوانی متد `TaskAssignmentService::assign` و نگاشت دقیق پارامترهای سازنده DTO در `AssignTaskRequest`.
- اصلاح فرآیند ارسال کار در `TaskController::submit` با جایگزینی `submitForReview` به جای متد ناموجود و اصلاح ماشین وضعیت.
- بازنویسی نمایش وضعیت SLA در ویوی `tasks/show.blade.php` با استفاده مستقیم از رابطه `slaRecords` و هلپر تاریخ شمسی.
- تغییر روت حذف وابستگی به متد امنیتی `POST` و تصحیح مدل `TaskDependency` جهت امکان‌پذیر شدن حذف وابستگی.
- نگارش ۱۲ تست Feature در `WebTaskStabilizationTest.php` و پاس شدن ۱۰۰٪ آزمون‌های سراسری در PostgreSQL (۱۱۴ تست، ۳۳۴ assertion).

### 🛠️ فایل‌های تغییر یافته

```text
app/Http/Controllers/Web/TaskAssignmentController.php
app/Http/Controllers/Web/TaskController.php
app/Http/Requests/Web/AssignTaskRequest.php
app/Models/TaskDependency.php
resources/views/tasks/show.blade.php
routes/web.php
tests/Feature/Web/WebTaskStabilizationTest.php
project_context/ANTIGRAVITY_CHANGELOG.md
project_context/ANTIGRAVITY_SESSION_LOG.md
TMS_PROJECT_TRACKER.md
MEMORY.md
ACTION_TRACKER.md
```

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۶/۳۰ (2026-09-20)

### 📝 خلاصه اقدامات
- پیاده‌سازی و یکپارچه‌سازی جامع تقویم و تاریخ هجری شمسی (جلالی) در تمام بخش‌های سامانه:
  - ایجاد هلپرها و کلاس‌های هسته `SafeJalali` و `JalaliDate` با جلوگیری از NPE و پشتیبانی از ارقام فارسی/عربی.
  - تعبیه استایل‌ها و کتابخانه تقویم شمسی در لی‌آوت اصلی و فعال‌سازی در اینپوت‌های فرم‌ها.
  - اصلاح فرم‌های ثبت تسک، فرم آپلود مدارک و نمایش ستون مهلت در جدول تسک‌ها به شمسی.
  - تبدیل خودکار تاریخ و زمان شمسی ورودی به میلادی در لایه Request قبل از اعتبارسنجی.
  - اصلاح نام و محتوای خروجی فایل‌های گزارش CSV به فرمت شمسی.
  - نگارش تست‌های کامل Unit و Feature برای اعتبارسنجی قابلیت‌های شمسی با پاس شدن ۱۰۰٪ تمام ۱۰۲ تست پروژه.

### 🛠️ فایل‌های تغییر یافته

```text
app/Support/JalaliDate.php
app/Support/helpers.php
app/Providers/AppServiceProvider.php
composer.json
resources/views/layouts/app.blade.php
resources/views/tasks/create.blade.php
resources/views/tasks/show.blade.php
resources/views/tasks/index.blade.php
app/Http/Controllers/Web/ReportController.php
app/Http/Requests/Web/StoreTaskRequest.php
app/Http/Requests/Web/StoreDocumentRequest.php
tests/Unit/JalaliDateTest.php
tests/Feature/Web/WebTaskJalaliDateTest.php
project_context/ANTIGRAVITY_CHANGELOG.md
project_context/ANTIGRAVITY_HANDOFF.md
project_context/ANTIGRAVITY_SESSION_LOG.md
project_context/walkthrough.md
TMS_PROJECT_TRACKER.md
MEMORY.md
ACTION_TRACKER.md
```
---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۷/۱۴ (2026-10-06) - فاز Phase 5B-1 (ادامهٔ UI Presentation)

### 📝 خلاصه اقدامات

ادامهٔ Phase 5B-1 پس از دو خطای ابزاری مراحل قبل (`Select-String -Recurse` و خطای TokenPlan). هیچ کار معتبر قبلی بازگردانی یا تکرار نشد؛ Board/Drawer/Design-tokens/Sidebar دست‌نخورده ماندند. مسیر تشخیص با ابزار درست (ripgrep) به‌طور کامل انجام و مستند شد.

**نتیجهٔ تشخیص (فقط گزارش — بدون تغییر):**
- `persianDatepicker` → **مصرف‌کنندهٔ واقعی دارد**: `resources/views/tasks/create.blade.php` (۲ ورودی `.datedown`)، `resources/views/tasks/show.blade.php` (۱ ورودی `.datedown`)، بارگذاری در `resources/views/layouts/app.blade.php` + `public/js/persianDatepicker/`. ⇒ **دست‌نخورده**.
- `Select2` → کتابخانه در `package.json` نیست، در هیچ layout/entrypoint بارگذاری نشده و **هیچ ویویی کلاس `select2` ندارد**؛ تنها اشاره‌ها `resources/js/app.js` و CSS RTL در `app.css` هستند. ⇒ **دست‌نخورده** (فقط گزارش).
- `FontAwesome` → npm `@fortawesome/fontawesome-free@7.3.1` نصب است و در `resources/css/app.css` با `@import` وارد شده، **و همزمان** CDN نسخهٔ ۶.۵.۱ در `layouts/app.blade.php` بارگذاری می‌شود ⇒ افزونگی تأیید شد، اما حذف انجام **نشد** (واگرایی نسخه ۶→۷ نتیجهٔ بصری قطعی ندارد) ⇒ «FontAwesome cleanup blocked — evidence insufficient for a safe removal».

**کارهای انجام‌شده (A–P):**
- A/B: عنوان هدر صفحات `فهرست وظایف` و `جزئیات وظیفه`.
- C: متریک «نیاز به بررسی» داشبورد از `'completed'` (وضعیت ناموجود) به سه وضعیت واقعی بررسی، با خواندن از `BoardColumns::statusesOf('review')`.
- D/E/F: ارائهٔ یکپارچهٔ وضعیت/اولویت از منبع واحد (بدون نگاشت تکراری در Board/Card/Drawer/Index/Detail/Dashboard)؛ `TaskPriority::label()/variant()/viewOf()` افزوده شد.
- G: فیلترهای واقعی سمت سرور صفحهٔ فهرست (search/project/status/priority) با همان معناشناسی Board از طریق `TaskController::applyTaskFilters()`.
- H: حذف لنگر شکستهٔ «دانلود» (`href="#"`) از ردیف مستندات.
- I: حذف رندر تکراری پیام‌های فلش در `tasks/show` و `settings/index` (تنها در layout).
- J/K/L: مدیریت تمرکز Drawer و سایدبار موبایل (ورود تمرکز، تلهٔ Tab/Shift+Tab، بازگشت تمرکز، `inert` در حالت بستهٔ موبایل، دسکتاپ ثابت) و `aria-busy` پویا.
- M: اصلاح ویژگی‌های منطقی RTL در محل‌های قطعی (`mr-3`→`ms-3`، `mr-2`→`ms-2`).
- N: یکدستی توکن فلش بررسی شد و از قبل یکسان بود (`status` = موفق، `error` = خطا).
- O: تست‌های دودی/رگرسیون جدید.
- P: ستون «لغو شده» به‌صورت پیش‌فرض جمع‌شده (با حفظ رندر سمت سرور کارت‌ها).

**یافتهٔ جانبی (گزارش، بدون تغییر — نیازمند تأیید مالک):**
- `resources/js/app.js` فراخوانی `$(".select2").select2({...})` دارد که **همیشه خطا می‌دهد** (کتابخانه در هیچ صفحه‌ای بارگذاری نمی‌شود؛ در صفحات مهمان حتی `jQuery` هم بارگذاری نمی‌شود → `ReferenceError: $ is not defined`). مقدار `Alpine.start()` **قبل** از این خط اجرا می‌شود، بنابراین قابلیت‌های Alpine آسیب نمی‌بینند.

### 🛠️ فایل‌های تغییر یافته

```text
app/Domain/Enums/TaskPriority.php                              (افزودن label/variant/viewOf)
app/Http/Controllers/Web/TaskController.php                    (applyTaskFilters + استفاده در index/board)
app/Http/Controllers/Web/DashboardController.php               (متریک بررسی از BoardColumns)
resources/views/tasks/index.blade.php                          (هدر، فرم فیلتر واقعی، وضعیت canonical)
resources/views/tasks/show.blade.php                           (هدر، حذف فلش تکراری، وضعیت/اولویت canonical، حذف لینک شکسته)
resources/views/tasks/board.blade.php                          (aria-busy پویا، برچسب اولویت، ستون لغو‌شده جمع‌شده)
resources/views/tasks/create.blade.php                         (گزینه‌های اولویت از enum)
resources/views/dashboard.blade.php                            (ارائهٔ canonical وضعیت + ms-2)
resources/views/settings/index.blade.php                       (حذف فلش تکراری)
resources/views/layouts/app.blade.php                          (mr-3 → ms-3)
resources/views/components/elements/drawer.blade.php           (مدیریت تمرکز)
resources/views/components/elements/sidebar.blade.php          (مدیریت تمرکز موبایل + inert)
resources/views/components/tasks/board-column.blade.php        (collapsed پیش‌فرض + رفع $refs)
resources/views/components/tasks/task-card.blade.php           (focus پیش از باز کردن Drawer)
resources/views/components/tasks/task-drawer-content.blade.php (برچسب اولویت canonical)
tests/Feature/Ui/Phase5B1UiTest.php                            (جدید — رگرسیون Phase 5B-1)
tests/Feature/V111/TaskDrawerTest.php                          (به‌روزرسانی assertion برچسب اولویت)
```

فایل‌های تولیدشدهٔ build (`public/build/**`) در `.gitignore` هستند و commit/تغییر ردیابی‌شده‌ای ایجاد نکردند.

### 🧪 تست‌ها

```text
php artisan test --compact tests/Feature/Ui/Phase5B1UiTest.php
→ 26 passed (132 assertions)

php artisan test --compact tests/Feature/V111/TaskBoardTest.php tests/Feature/V111/TaskDrawerTest.php tests/Feature/Ui
→ 123 passed (580 assertions)

php artisan test --compact                     (کل مجموعه، PostgreSQL)
→ 2 deprecated, 673 passed (2279 assertions) — 0 failed

vendor/bin/pint <۵ فایل تغییریافته> --format agent
→ fixed: TaskController.php, Phase5B1UiTest.php

npm run build
→ ✓ built in 3.61s  (هیچ فایل ردیابی‌شدهٔ جانبی تولید نشد)
```

راستی‌آزمایی مرورگری (روی سرور واقعی `php artisan serve`): `dir="rtl"`/`lang="fa"`، بارگذاری CSS ساخته‌شده، رزولوشن توکن‌ها (`bg-surface-inset`→`#f1f5f9`، `text-ink`→`#0f172a`، `rounded-ui-md`→`12px`، `ms-3`/`ps-12` به‌صورت logical)، پشتهٔ فونت Vazirmatn، و بارگذاری Alpine تأیید شد.

### ⚠️ عدم تغییر عمدی

- `persianDatepicker`، دارایی‌های تاریخ‌ساز، `Select2` و `FontAwesome`: **بدون تغییر** (شواهد بالا).

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۷/۱۵ (2026-10-06) - Phase 5B-1 Final Review & Cleanup

### 📝 خلاصه اقدامات

مرحلهٔ Review/Cleanup محدود. اصل حاکم: **Evidence > Minimal Change > Regression Safety > Owner Decision > Refactor**. Phase 5B-2 شروع نشد.

**۱) Select2 — شواهد قطعی (EVIDENCE):**
- `package.json` و `package-lock.json` → **صفر** اشاره (نصب نیست).
- `resources/js/` → فقط `resources/js/app.js:11` (نفس initializer).
- `resources/css/` → فقط `resources/css/app.css:10-11` (`.select2-container` RTL).
- `resources/views/**` → **صفر** استفاده از `.select2` یا API آن؛ صفر کلاس `select2` در هیچ blade.
- هیچ layout/entrypoint دارایی Select2 را بارگذاری نمی‌کند (صفر ارجاع به `js/select2`/`select2.min`).
⇒ **اقدام:** فقط initializer مرده از `resources/js/app.js` حذف شد. دارایی ردیابی‌شدهٔ `public/js/select2/4.1.0/select2.min.js` و CSS `.select2-container` **حذف نشدند** (حدس‌محور).

**۲) FontAwesome:** بدون تغییر — npm `7.3.1` + CDN `6.5.1` (افزونگی اثبات‌شده، ولی واگرایی major ⇒ حذف ناایمن).

**۳) Datepicker:** بدون تغییر — مصرف واقعی: `tasks/create` (۲× `.datedown`)، `tasks/show` (۱×)، wiring در `layouts/app.blade.php` + `public/js/persianDatepicker/`.

**۴) BoardColumns Review:** `cohesive — no refactor required`. مسئولیت‌ها فقط: Board column grouping + status presentation. صفر write surface / قاعدهٔ کسب‌وکاری. ۱۸ call site بررسی شد: `tasks/components/task-card.blade.php` (`statusView`/`reviewTier`) · `task-drawer-content.blade.php` (`statusView`×۲/`reviewTier`) · `dashboard.blade.php` (`statusView`) · `tasks/board.blade.php` (`filterableStatuses`/`statusLabel`) · `tasks/index.blade.php` (`filterableStatuses`/`statusLabel`/`statusView`) · `tasks/show.blade.php` (`statusView`/`statusLabel`) · `TaskController::board/applyTaskFilters` (`COLUMNS`/`columnOf`/`filterableStatuses`) · `DashboardController` (`statusesOf('review')`).

**۵) Manual Browser QA:** `NOT EXECUTED — environment limitation` (عدم دسترسی به session احرازشده؛ Drawer/Sidebar پشت middleware `auth`). فقط سطح markup/test تأیید شد: Drawer (`x-ref="drawerPanel"`/`tabindex="-1"`/`x-on:keydown.tab="trapFocus($event)"`/`keydown.escape.window`/`opener` + بازگشت focus) و Sidebar (`matchMedia('(max-width: 767px)')` → `isMobile`، `:inert="isMobile && ! open"`، همان تلهٔ Tab/Escape/بازگشت).

### 🛠️ فایل‌های تغییر یافته (این مرحله)

```text
resources/js/app.js                                   (حذف فقط بلاک initializer مردهٔ Select2 — دیف = همان بلاک)
ACTION_TRACKER.md · MEMORY.md                          (ثبت گزارش/حافظه)
project_context/ANTIGRAVITY_CHANGELOG.md               (ثبت تغییر)
project_context/ANTIGRAVITY_SESSION_LOG.md             (گزارش جلسه)
```

### 🧪 تست‌ها

```text
php artisan test --compact <۵ سوییت تمرکزی>
→ 123 passed (580 assertions) — exit 0

php artisan test --compact                     (کل مجموعه، PostgreSQL)
→ 2 deprecated, 673 passed (2279 assertions) — 0 failed  (= برابر بیس)

vendor/bin/pint <فایل‌های PHP فاز 5B-1> --format agent
→ {"tool":"pint","result":"passed"}   (هیچ فایل PHP در این مرحله تغییر نکرد)

npm run build
→ ✓ built — app-BOcwtWD-.js (133.14 kB)  ·  باندل خروجی صفر اشارهٔ select2 دارد و Alpine حاضر است
```

### 🔒 Safety Audit
- `database/` پاک · صفر Migration جدید · صفر Schema Mutation · صفر تغییر داده.
- build output در `.gitignore` (`.gitignore:17 /public/build`) و هیچ artefact تولیدی tracked نیست.
- `template/` و `v19_out.txt` دست‌نخورده · صفر commit · صفر push.
- `routes/web.php` و `app/Domain/Enums/TaskPriority.php` و `app/Policies/WbsPhasePolicy.php` **از این مرحله نیستند** (کار قبلی فازهای V1.11/Phase 3؛ این مرحله فقط گزارش کرد).
- `TaskPriority.php` فقط presentation افزوده (`label/variant/viewOf`) — صفر تغییر case/مقدار دامنه. `routes/web.php` فقط یک GET خواندنی `tasks.board` (Phase 3) — صفر policy/permission جدید.

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۷/۱۵ (2026-10-06) - Phase 5B-2 Visual Migration

### 📝 خلاصه اقدامات

مهاجرت بصری سه صفحهٔ Legacy به Design System فعلی TMS. **صفر تغییر در Domain/Authorization/Business Rule/داده.** مرجع درون‌مخزنی هدف: `resources/views/tasks/board.blade.php` (که در Phase 3 با همین سیستم طراحی ساخته شده بود) — تا محصول دیگر «دو محصول کنار هم» به نظر نرسد.

**۱) Dashboard:** سه کارت آماری و پنل توزیع وضعیت به `x-ui.panel` منتقل شدند؛ آیکون‌ها به کاشی‌های token-based (`bg-primary/10`, `bg-danger-100`)، اعداد با `tabular-nums`، و empty state واقعی به `x-ui.empty-state`. تمام metricها/query/متن‌ها یکسان.

**۲) Task Index:** هدر صفحه با h1 توکنی + پیل تعداد (`$tasks->total()`) هم‌شکل با Board؛ آیکون جستجو داخل input (`ps-9`); جدول به توکن‌ها منتقل شد (`border-line`/`divide-line-subtle`/`bg-surface-inset`/`text-ink-muted`) داخل `x-ui.panel body-class="p-0"`؛ ردیف خالی با `x-ui.empty-state` در سلول colspan=8؛ Pagination داخل فوتر پنل. **فرم فیلتر، ۴ کنترل، GET، query string، pagination و scope دست‌نخورده.**

**۳) Task Detail:** تمام کارت‌ها به `x-ui.panel`؛ متادیتا به `x-ui.key-val` (۷ قلم، همان برچسب‌ها)؛ اولویت به `x-ui.badge` از `TaskPriority::viewOf()`؛ stateهای SLA/پیش‌نیاز به `x-ui.badge` با variant معنایی. دو کارتابل تأیید با `accent` (primary/success) و آیکون در `x-slot:icon`. **تمام فرم‌ها/route‌ها/name-id‌ها/`@can`/`hasRole`/شرط‌های وضعیت/`.datedown` دست‌نخورده.**

**رنگ‌ها:** `red/green/yellow/blue/slate/purple` پراکنده → توکن‌های `danger/success/warning/info/surface/ink/line`. `purple` (که توکن معادل نداشت) فقط در دو کارتابل تأیید به `primary`/`success` نگاشت شد و با `accent` حفظ شد.

### 🛠️ فایل‌های تغییر یافته

```text
resources/views/dashboard.blade.php                 (مهاجرت بصری — Panel/تokens/EmptyState)
resources/views/tasks/index.blade.php               (مهاجرت بصری — هدر/فیلتر/جدول/Pagination/EmptyState)
resources/views/tasks/show.blade.php                (مهاجرت بصری — Panel/KeyVal/Badge؛ همهٔ فرم‌ها و دروازه‌ها حفظ)
tests/Feature/Ui/Phase5B2UiTest.php                 (جدید — ۱۷ تست قراردادی، ۱۴۷ assertion)
```

### 🧪 تست‌ها

```text
php artisan test tests/Feature/Ui/Phase5B2UiTest.php
→ 17 passed (147 assertions)

php artisan test <Ui + V111 + V110 + Web/Task + Dependency + Evidence + TwoTierApproval>
→ 465 passed (1692 assertions)   (= بدون رگرسیون)

php artisan test --compact                     (کل مجموعه، PostgreSQL)
→ 2 deprecated, 690 passed (2426 assertions) — 0 failed
   (بیس Phase 5B-1: 673 · 2279 — افزایش ۱۷ تست = همان سوییت جدید؛ صفر regression)

vendor/bin/pint tests/Feature/Ui/Phase5B2UiTest.php --format agent
→ {"tool":"pint","result":"passed"}

npm run build
→ ✓ built in 1.46s · app-CjzuLki-.css (171.47 kB) · app-BOcwtWD-.js (133.14 kB)
   => زیباشدن CSS ⇐ کلاسهای جدید واقعاً کامپایل شدند (file:me-2 · bg-primary/10 · ps-9 · divide-line-subtle · bg-success-600 ...)
```

### 🖥️ Manual Browser QA
`NOT EXECUTED — environment limitation`. شواهد: `php artisan serve` → `/dashboard`, `/tasks`, `/tasks/board` همه **302** (پشت middleware `auth`)؛ هیچ credential/session واقعی در محیط نیست و ساخت کاربر موقت = تغییر داده در DB توسعه ⇒ مجاز نبود. تنها راستی‌آزمایی HTTP ممکن: صفحهٔ عمومی `/login` → **200**؛ CSS/JS ساخته‌شده → **200** (`app-CjzuLki-.css` = 171479 بایت، دقیقاً همان باندل تازه‌ساخته)؛ لاگ سرور → صفر error. **راستی‌آزمایی بصری واقعی انجام نشده و PASS اعلام نمی‌شود.**

### 🔒 Safety
صفر Migration · صفر Schema Mutation · صفر تغییر داده · صفر تغییر route/policy/domain/lifecycle/approval/SLA/financial/WBS · صفر permission جدید · `template/` و `v19_out.txt` دست‌نخورده · build output gitignored · صفر commit · صفر push.

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۷/۱۵ (2026-10-06) - Phase 5B-2 Final Review & Heading Architecture Gate

### 📝 نوع مرحله: Review/Decision Gate — **صفر تغییر کد**

**۱) Heading Inventory (ریپ‌واید):** `app-header` عنوان هر صفحه را با `<h2>` ثابت رندر می‌کند. نتیجهٔ بررسی ۱۷ مصرف‌کننده: **۶ صفحه بدون h1** (dashboard, tasks/show, tasks/create, settings, reports, audit) · **۲ صفحه با h1 تکراری همان‌متن** (tasks/index, tasks/board — header h2 + page h1 هر دو «فهرست/تابلوی وظایف») · ۹ صفحهٔ modules/users/wbs با h1 صحیح و بدون تکرار · `settings` + `reports` دارای skip-level (h4/h3 زیر h2).

**۲) گزینه‌ها:** A (header→h1): ۹ صفحهٔ دارای h1 تکراری می‌شوند + ۵ تست را می‌شکند ⇒ مردود. B (وضع موجود): دو نقص باقی می‌ماند ولی صفحات دارای h1 از قبل الگوی صحیح را دارند. C (heading قابل‌تنظیم): الگوی B را عمومی می‌کند؛ abstraction کوچک و محتمل، ولی نیازمند تصمیم مالک روی ۶ صفحهٔ فاقد h1.

**۳) Recommendation:** **گزینه B** (وضع موجود) — الگوی `board`/`index`/`users`/`wbs` (h1 صفحه + header h2) الگوی اکثریت و صحیح ریپ است؛ تغییر کد فقط با اصلاح ۶ صفحهٔ فاقد h1 معنا دارد که خارج از scope این Gate است.

**۴) Stat Card:** `No x-ui.stat required at this stage` — الگوی label+value+icon فقط در dashboard.blade.php (+ یک مورد legacy در elements/header که خارج از shell فعلی است) وجود دارد؛ reuse چندصفحه‌ای اثبات نشده.

**۵) Integrity 5B-2:** بازبینی‌شده و تأیید — Dashboard (متریک‌ها/شرط‌ها/متن‌ها/برچسب canonical) · Index (۴ فیلتر GET/pagination/query string/canonical) · Detail (routes/فرم‌ها/name/id/`@can`/شرط‌های وضعیت/`.datedown`/کارتابل‌ها/متادیتای سند) همگی حفظ شده‌اند.

**۶) Browser QA:** `NOT EXECUTED — environment limitation` (صفحات پشت `auth` → 302؛ بدون credential؛ بدون ساخت کاربر/seed/mutation).

**۷) تست‌ها:** Suiteهای Ui/UtFoundation/LayoutShell/Phase5B1/Phase5B2/V111 → **123+17 = 140 passed** · Pint نتیجه‌ای برای تغییر نداشت (صفر فایل PHP) · build لازم نبود (صفر تغییر Blade/CSS/JS).

### 🔒 Safety
صفر تغییر کد · صفر DB/Migration · صفر Domain/Policy/Auth · صفر commit/push · HEAD `08b2629` · app-header دست‌نخورده.

### 🚦 Final Gate
**`READY FOR PHASE 5B-3`** — نقص heading به‌عنوان تصمیم آینده ثبت شد (گزینهٔ C پس از تأیید مالک روی ۶ صفحهٔ فاقد h1).

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۷/۱۵ (2026-10-06) - Phase 5B-3 Visual Migration (Wave 2)

### 📝 Discovery (Evidence First)

سنجش legacy بر اساس تعداد کلاس‌های `slate-*` و رنگ‌های قدیمی (`red/green/yellow/blue/purple/emerald/rose`) در ۱۶ صفحه:

| Page | Lines | Slate | Old colors | Priority |
|---|---:|---:|---:|---|
| reports/index | 164 | 52 | 10 | بالا (عنوان/کارت‌های متعدد) |
| modules/stages/show | 230 | 50 | 12 | بالا (رکورد/نمودار/فرم) |
| users/index | 140 | 20 | 6 | **انتخاب شد** |
| audit/index | 96 | 30 | 1 | متوسط |
| wbs-phases/index | 79 | 21 | 4 | **انتخاب شد** |
| wbs-phases/show | 109 | 23 | 6 | متوسط |
| users/create+edit | 199/203 | 27 | 11 | متوسط (فرم‌های بزرگ) |
| settings/index | 75 | 17 | 4 | **انتخاب شد** |
| modules/index/show | 51/120 | 10/28 | 2/6 | پایین |

Primitive usage: `panel` ×۳، `badge` ×۵، `avatar` ×۴، `empty-state` ×۵، `key-val` ×۲، `status-indicator` ×۱، `progress/ring/chip/segmented` ×۰ (بدون مصرف).

**Scope انتخاب‌شده:** users/index + wbs-phases/index + settings/index — یک خانوادهٔ منسجم «مدیریت/پیکربندی» با ریسک پایین (هر سه حداقلی‌ترین منطق دامنه را دارند: paginate/empty/satisfying form/save). سه صفحه‌ای مانند `reports` یا `modules/stages/show` ارزش بالاتری داشتند ولی ریسک/حجم بالاتری هم دارند؛ برای موج بعدی.

### 🛠️ فایل‌های تغییر یافته

```text
resources/views/users/index.blade.php       (جدول + delete form + role badges → Panel/Badge/Avatar/EmptyState)
resources/views/wbs-phases/index.blade.php  (create form + status badges → Panel/labels/EmptyState)
resources/views/settings/index.blade.php    (۳ toggle + select → Panel/tokens/logical RTL)
tests/Feature/Ui/Phase5B3UiTest.php         (جدید — ۹ تست، ۱۲۰ assertion)
```

### 🔄 مهاجرت

- **users/index:** `bg-blue-600` → `bg-primary`؛ role badges (admin=primary, supervisor=info, contractor=success) → `x-ui.badge`؛ فعال/غیرفعال → badge success/danger؛ avatar دستی → `x-ui.avatar`؛ empty → `x-ui.empty-state`. **فرم حذف: `@method('DELETE')` + `onsubmit confirm` + guard «خودِ ادمین» حفظ شد.**
- **wbs-phases/index:** فرم ایجاد با ۵ فیلد → توکن‌ها + label associated (`for=`)؛ نشان‌های وضعیت (در انتظار/تکمیل‌شده/تکمیل‌نشده) → badge warning/success/danger. **`route('wbs-phases.store')`/نام‌فیلدها/required/maxlength حفظ شد.**
- **settings/index:** ۳ toggle با `peer` → توکن‌ها + `rtl:` variant برای flip درست؛ `approval_mode` select حفظ. **action/name/checked پیش‌فرض (evidence+lock_weight=true) حفظ شد.**

### 🧪 تست‌ها

```text
php artisan test tests/Feature/Ui/Phase5B3UiTest.php
→ 9 passed (120 assertions)

Focused (Ui + WebUserController + AuditVisibility + V19)
→ 135 passed (571 assertions)

php artisan test --compact                     (کل مجموعه، PostgreSQL)
→ 2 deprecated, 699 passed (2546 assertions) — 0 failed
   (بیس 690 + ۹ تست جدید = صفر regression)

vendor/bin/pint tests/Feature/Ui/Phase5B3UiTest.php --format agent → passed
npm run build → ✓ built in 2.16s · app-CuLaWWO4.css (171.81 kB)
   => verify: peer-checked:bg-primary · rtl:after:translate-x-full · after:end-[2px] همگی کامپایل شدند
```

### 🖥️ Manual Browser QA
`NOT EXECUTED — environment limitation` (طبق §13: بدون credential، بدون user/seed/mutation).

### 🔒 Safety
صفر DB/Migration · صفر Domain/Policy/Auth/Lifecycle/Approval/SLA/Financial/WBS · صفر route جدید · صفر permission جدید · Heading Architecture دست‌نخورده (Option B) · Phase 5C دست‌نخورده · `template/` و `v19_out.txt` دست‌نخورده · صفر commit · صفر push.

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۷/۱۶ (2026-10-07) - Phase 5B-4 Visual Migration (Reports)

### 📝 Discovery (Evidence First)

- صفحهٔ `reports/index` (۱۶۴ خط): **۵۲ کلاس `slate-*` + ۱۰ رنگ قدیمی** (blue/green/red/amber) — بالاترین تراکم legacy در survey ۱۶ صفحه‌ایِ 5B-3.
- Route: `GET /reports` → `reports.index` با `role:admin|management|employer|project_manager` · `GET /reports/export` → `reports.export` (سه type: `approved_tasks` / `all_tasks` / `stage_progress` + `array_filter` پارامترهای فیلتر) · لینک ردیف جدول → `modules.stages.show`.
- ساختار: فرم GET فیلتر (`project_id`/`from`/`to`) + ۳ KPI + جدول پیشرفت مراحل +۳ کارت خروجی CSV + بنر استثنای DEC-040.
- **صفر chart / صفر JS hook / صفر `id=` / صفر `<script>`** در صفحه — هیچ class/id مصرف‌شده توسط JS/CSS برای حفظ وجود ندارد (با grep اثبات شد).
- **صفر `@can` درون Blade** — authorization کاملاً سمت route middleware.
- پین‌های موجود: `ReportExportTest` (200/403/CSV `شناسه`) · `V110/ReportEnhancementsTest` (`وزن کل تسک‌ها`، `99.0`، `0.00`/`10.00`، `1 وظیفه بدون مرحله`) · `V110/TaskStageAssignmentTest` — همگی بدون تغییر سبز ماندند.
- Primitiveهای reuse شده: `x-ui.panel` (header/footer slot) · `x-ui.empty-state` · توکن‌ها. `x-ui.badge`/`status-indicator` کاربردی در این صفحه نداشتند (وضعیت/اولویتی نمایش داده نمی‌شود) ⇒ بدون کامپوننت جدید.

### 📐 Contract Inventory (Before → After)

```text
Routes:    reports.index (form action + reset) · reports.export (۳ لینک) · modules.stages.show (لینک ستون «مرحله») — همه حفظ شدند
Forms:     method=GET ×۱ — حفظ شد
Fields:    project_id (select) · from (date) · to (date) — نام‌ها حفظ شدند
IDs:       قبل: صفر — بعد: report-project/report-from/report-to (additive فقط، برای label association)
Selected:  $filters['project_id'] === (int) $project->id · value توپر from/to — عیناً حفظ شد
Query:     project_id/from/to روی index · type(+project_id/from/to) روی export (array_filter) — حفظ شد
Auth:      صفر @can در Blade · middleware سمت route — دست‌نخورده
Status:    rows->isEmpty() · approved > 0 · excluded_no_stage_tasks > 0 — حفظ شد
JS hooks:  هیچ — چیزی برای حفظ نبود
Data:      $projects · $filters · $totalWeight · $delayedTasks · $stageProgress{total_approved,total_allocated,rows,excluded_no_stage_tasks} — حفظ شد
Labels:    «فیلتر گزارش» «وزن کل تسک‌ها» «مبلغ تأییدشدهٔ مراحل» «وظایف دارای تاخیر بحرانی» «پیشرفت مراحل (مبتنی بر مبلغ تأییدشده)» «برون‌ریزی داده‌ها (CSV Export)» «۱ وظیفه بدون مرحله» + ۵ هدر جدول + ۳ عنوان کارت CSV — همه حفظ شدند
Headings:  h2×3 (محتوا) + h3×3 (کارت‌های CSV) + h2 اپ-هدر — سطوح عیناً حفظ شدند
```

### 🛠️ فایل‌های تغییر یافته

```text
resources/views/reports/index.blade.php          (مهاجرت بصری کامل)
tests/Feature/Ui/Phase5B4UiTest.php              (جدید — ۱۲ تست، ۸۷ assertion)
```

### 🔄 مهاجرت

- **پنل‌ها:** چهار بخش (فیلتر / جدول مراحل / برون‌ریزی + ۳ کارت KPI) → `x-ui.panel`؛ برای حفظ سطح عنوان، به‌جای `title` از `x-slot:header` استفاده شد (h2×3 و h3×3 عیناً حفظ شدند — Option B دست‌نخورده).
- **KPI:** کاشی‌های آیکون `blue/green/red` → `bg-primary/10` · `bg-success-100/text-success-700` · `bg-danger-100/text-danger-700`؛ `number_format` و متن‌ها بی‌تغییر.
- **جدول:** `divide-slate-*` → `divide-line-subtle` · thead → `bg-surface-inset` · لینک مرحله `text-blue-600` → `text-primary` · مقدار مثبت `text-green-700` → `text-success-700` (صفر → `text-ink-subtle`) · `overflow-x-auto` حفظ + `tabular-nums`.
- **بنر amber** → توکن‌های `warning-*` و انتقال به `x-slot:footer` (تمام‌عرض زیر پنل، مثل قبل).
- **Empty state** متنی → `x-ui.empty-state` (همان متن).
- **فرم فیلتر:** `slate/blue` → توکن‌ها + `for=`/`id=` جدید + focus-visible روی دکمه/لینک؛ GET/نام‌فیلدها/selected-state/`dir="ltr"` تاریخ‌ها بی‌تغییر.
- **RTL:** `ms/me/ps/pe` + `text-right` (هم‌شکل با tasks/index مهاجرت‌شده) — بدون تغییر جهت‌بندی سراسری.
- **a11y:** `aria-hidden` روی ۵ آیکون تزئینی · label-assoc سه فیلد · focus-visible ring.

### 🧪 تست‌ها

```text
php artisan test tests/Feature/Ui/Phase5B4UiTest.php
→ 12 passed (87 assertions)

Focused (ReportExportTest + V110/ReportEnhancementsTest + V110/TaskStageAssignmentTest)
→ 31 passed (102 assertions)

php artisan test --compact                     (کل مجموعه، PostgreSQL)
→ 2 deprecated, 711 passed (2633 assertions) — 0 failed
   (بیس 699 + ۱۲ تست جدید = صفر regression)

vendor/bin/pint tests/Feature/Ui/Phase5B4UiTest.php --format agent → fixed+passed
npm run build → ✓ built in 10.25s · app-i4r-HJ14.css (171.72 kB)
   => verify: bg-warning-50 · border-warning-200 · text-success-700 · bg-danger-100 · bg-surface-inset/40 همگی با grep -F کامپایل شدند
```

### 🖥️ Manual Browser QA
`NOT EXECUTED — environment limitation` (صفحه پشت `auth` → 302؛ بدون credential؛ ساخت کاربر/seed/mutation ممنوع).

### 🔒 Safety
صفر DB/Migration · صفر Domain/Policy/Auth/Lifecycle/Approval/SLA/Financial/WBS · صفر route/permission جدید · Heading Architecture دست‌نخورده (Option B؛ h1 صفحه DEFER گزارش شد) · Phase 5C دست‌نخورده · `template/` و `v19_out.txt` دست‌نخورده · صفر commit · صفر push · HEAD `08b2629`.

### 🚦 Final Gate
**`READY FOR PHASE 5B-5`**

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۷/۱۶ (2026-10-07) - Phase 5B-5 Visual Migration (Users Create + Edit)

### 📝 Discovery (Evidence First)

- دو فایل هدف: `users/create.blade.php` (۱۹۹ خط) + `users/edit.blade.php` (۲۰۳ خط) — هر کدام **۲۷ `slate-*` + رنگ‌های قدیمی** (blue/rose/purple/emerald).
- Route: `GET users.create` / `GET users.edit` · فرم create → `POST users.store` · فرم edit → `POST` + `@method('PUT')` → `users.update` · همه زیر `role:admin` (بدون Policy — فقط middleware سمت route + `authorize()` در FormRequest).
- **JS hook زنده:** بلوک `@push('scripts')` در هر دو صفحه (با `layout` خط ۱۰۷ `@stack('scripts')`) → `.role-checkbox` + `#contractor-wrapper` + `cb.value === 'contractor'` — مصرف‌کنندهٔ واقعی، عیناً حفظ شد. هیچ CSS/JS خارجی به این hookها اشاره ندارد (grep در `resources/js|css` صفر).
- Validation: `@error` روی name/national_code/mobile/email/password/roles/contractor_id + `old(...)` همهٔ فیلدها + قدیم fallback به مقادیر کاربر در edit. قواعد از `StoreUserRequest`/`UpdateUserRequest` (فقط خوانده شد، تغییر نکرد).
- پین‌های موجود: `WebUserControllerTest` («مشخصات کاربر جدید» 200 · 403 برای supervisor/contractor · store/update حذف/تغییر نقش) · `Phase5B3UiTest` (لینک `users.create` از index).

### 📐 Contract Inventory (خلاصه)

```text
Create: POST users.store · @csrf · fields: name*/national_code*(max10)*/mobile*(max11)*/email/password*(min6)/is_active(1,default checked)/roles[]/contractor_id · ids: name..password, contractor_id, contractor-wrapper · class .role-checkbox · wrapper hidden مگر contractor در old
Edit:   POST+PUT users.update · همان fields (password اختیاری، بدون value) · values: old(x, $user->x) · roles checked = old ? old : roles کاربر · contractor visible = old ? old : نقش کاربر
Auth:   صفر @can در Blade · role:admin سمت route — دست‌نخورده
JS:     DOMContentLoaded + toggleContractorSelect + change listener — بلوک script عیناً کپی شد
```

### 🛠️ فایل‌های تغییر یافته

```text
resources/views/users/create.blade.php     (مهاجرت بصری)
resources/views/users/edit.blade.php       (مهاجرت بصری)
tests/Feature/Ui/Phase5B5UiTest.php        (جدید — ۱۳ تست، ۱۳۵ assertion)
```

### 🔄 مهاجرت

- سربرگ (h1 + زیرعنوان + لینک بازگشت) → `x-ui.panel` با `x-slot:header` (**سطح h1 عیناً حفظ شد** — app-header دست‌نخورده).
- input/select → `rounded-ui` + `border-line-strong`/`bg-surface-base`/`focus:ring-primary` + **حالت خطا به‌صورت قطعی** با `$errors->has()` → `border-danger-500` (قابلیت اطمینان بیشتر از ترتیب کلاس‌ها).
- کارت‌های نقش: purple/blue/emerald → همان نگاشت semantic 5B-3 (admin=primary · supervisor=info · contractor=success · default=surface)؛ checkbox آبی → `text-primary`.
- دکمه‌های toggle آبی/خلیجی → `bg-line-strong`/`peer-checked:bg-primary` + `peer-focus:ring` (**موقعیت/transform بدون تغییر** — رفتار عیناً حفظ).
- دکمه اصلی → `bg-primary`/`focus-visible`؛ انصراف → توکن secondary؛ `mr-3`/`ml-*` → `ms-3`/`gap-*` (منطقی، RTL).
- a11y: `for` از قبل بود ✓ + `aria-describedby` شرطی برای errorها · `id="*-error"` · `aria-hidden` روی ۳ آیکون FontAwesome (خودِ FA دست‌نخورده).
- **هیچ کامپوننت جدیدی ساخته نشد.**

### 🧪 تست‌ها

```text
php artisan test tests/Feature/Ui/Phase5B5UiTest.php
→ 13 passed (135 assertions) — دو بار پیاپی اجرا شد (پایدار)

Focused (WebUserControllerTest + Phase5B3 + Phase5B4)
→ 26 passed (232 assertions)

php artisan test --compact                     (کل مجموعه، PostgreSQL)
→ 2 deprecated, 724 passed (2768 assertions) — 0 failed
   (بیس 711 + ۱۳ تست جدید = صفر regression)

vendor/bin/pint tests/Feature/Ui/Phase5B5UiTest.php --format agent → passed (بدون تغییر)
npm run build → ✓ built in 9.36s · app-C3kpr45Z.css (170.71 kB)
   => verify: border-danger-500 · bg-info-50 · border-success-200 · bg-primary/5 · ring-primary/40 همگی با grep -F کامپایل شدند
```

**یافتهٔ تستی ارزشمند (رفع شد):** ترتیب فراخوانی `assertSessionHasErrors` بعد از POST، به‌دلیل `serialization=json` کانفیگ session، کیسهٔ خطا را در حافظه به شیء تبدیل می‌کند و `marshalErrorBag` در GET بعدی آن را با کیسهٔ خالی جایگزین می‌کند (رَندوم نبود — علتِ شکست اولیهٔ تست). تست با اتکا به **خروجی رندرشده** نوشته شد؛ جریان واقعی POST→GET بدون این فراخوانی، رندر خطاها را تأیید کرد. (تنها باگ احتمالیِ تستی — نه اپ — در همین مرحله شناسایی و ریشه‌یابی شد.)

### 🖥️ Browser QA
`Browser QA NOT EXECUTED — valid authenticated credentials unavailable.` (بدون credential؛ ساخت کاربر/seed/mutation ممنوع.)

### 🔒 Safety
صفر DB/Migration · صفر Controller/Request/Service/Policy/Model/Route · صفر Domain/Auth/Permission · Heading Architecture دست‌نخورده · صفر RTL سراسری · FontAwesome/Select2/Datepicker/dark-mode دست‌نخورده · `template/` و `v19_out.txt` دست‌نخورده · صفر commit · صفر push · HEAD `08b2629`.

### 🚦 Final Gate
**`READY FOR PHASE 5B-6`**

---

# فاز Phase 5B-6 — مهاجرت بصری Modules / Stages (۲۰۲۶-۱۰-۰۷)

### 🔍 Gate A — Discovery + Contract Inventory
- سه کاندید سنجیده شد: `modules/stages/show` (۲۳۰ خط · ۵۰ slate · ۲۸ رنگ قدیمی) · `modules/show` (۱۲۰ · ۲۸ · ۱۲) · `modules/index` (۵۱ · ۱۰ · ۴) · پوشهٔ `resources/views/stages/` خالی.
- خواندن فقط: `routes/web.php` (سه گروه `role:`) · `ModuleController`/`ModuleStageController`/`StageProgressApprovalController` (نام فقط) · `ModulePolicy` + `ModuleStagePolicy` · سرویس‌های وزن/تأیید (نام متد) · تست‌های `V19/V111/Ui` مرتبط. **هیچ فایل PHP در Discovery تغییر نکرد.**
- شواهد کلیدی: **صفر `@can`/`@role` در ویو** · **صفر hook جاوااسکریپت** (فقط `details/summary` بومی) · نشان‌های وضعیت تأیید از قبل برچسب فارسی ثابت داشتند · وضعیت وظایف خام رندر می‌شد (نگاشت مرجع `BoardColumns` موجود بود) · هیچ تستی به CSS class یا HTML این دو صفحه وابسته نبود (تنها `assertSee($stage->name)`).
- **دامنهٔ انتخابی:** `modules/stages/show` + `modules/show` · **deferred:** `modules/index` (کمترین شدت). بدون `OWNER DECISION REQUIRED`.

### 🎨 Gate B — تغییرات بصری
- `resources/views/modules/stages/show.blade.php`: نوار وزن → `x-ui.key-val` · ۳ پنل با `x-slot:header` (h2 سطح‌ها حفظ) · نشان‌های تأیید → `x-ui.badge` (همان متن، variant همان رنگ قبلی: amber=warning · green=success · red=danger · slate=muted) · empty-state → `x-ui.empty-state` · وضعیت وظایف → `BoardColumns::statusView` · دکمه‌ها → primary/success/danger/secondary/warning-token · `text-left` → `justify-end` · inputهای وزن/مبلغ → توکن‌ها + `aria-label`.
- `resources/views/modules/show.blade.php`: شاخهٔ خالی (فرم تعریف ۳ ماژول) + شاخهٔ ساختار (کارت ماژول/چیپ مراحل/فرم inline/بازتوازن) با همان ساختار منطقی و `x-slot:header` برای سطح h2.
- `tests/Feature/Ui/Phase5B6UiTest.php` (جدید): ۱۴ تست/۱۴۶ assertion — authorization · routes/links · قرارداد کامل فرم‌ها (نام فیلدها/مقدارها/min-max/required/disabled/مخفی‌ها) · نمایش وزن عین کنترلر · رندر pending/decided · empty-state · وضعیت canonical · حذف legacy.

### 🧪 تست‌ها

```text
php artisan test --compact tests/Feature/Ui/Phase5B6UiTest.php
→ 14 passed (146 assertions)

Focused (Module/Stage — V19 + V111، ۱۰ فایل)
→ 137 passed (379 assertions)

php artisan test --compact                     (کل مجموعه، PostgreSQL)
→ 2 deprecated, 738 passed (2914 assertions) — 0 failed
   (بیس 724 + ۱۴ تست جدید = صفر regression)

vendor/bin/pint --test tests/Feature/Ui/Phase5B6UiTest.php → PASS (1 file)
npm run build → ✓ built in 14.04s · app-Bnd3FIHE.css (170.30 kB)
   => verify: bg-warning-500 · bg-warning-600 · ring-warning-500 · hover\:bg-line ·
      focus-visible\:ring-danger-500 · divide-line-subtle همگی با grep -F کامپایل شدند
```

### 🖥️ Browser QA
`Browser QA NOT EXECUTED — valid authenticated credentials unavailable.` (بدون credential؛ ساخت کاربر/seed/mutation ممنوع.)

### 🔒 Safety
صفر DB/Migration · صفر Controller/Request/Service/Policy/Model/Route · صفر Domain/Auth/Permission/Lifecycle/Approval/SLA/Financial · Heading Architecture دست‌نخورده · صفر RTL سراسری · FontAwesome/Select2/Datepicker/dark-mode دست‌نخورده · `template/` و `v19_out.txt` دست‌نخورده · صفر commit · صفر push · HEAD `08b2629`.

### 🚦 Final Gate
**`READY FOR PHASE 5B-7`**

---

## 📅 گزارش تغییرات - ۱۴۰۵/۰۷/۱۹ (2026-10-10) - Phase 5B-8 (WBS Decision UI Authorization Audit + Modules Index Migration)

### 🔍 Part A — ممیزی ارائه‌ی UI تصمیم WBS
- **یافته:** فرم تصمیم ناظر از HEAD به‌صورت **بی‌قید** برای هر بیننده‌ی صفحه رندر می‌شد (هیچ `@can('decide')` وجود نداشت) — Admin/PM/viewer فرم تصمیم را به‌عنوان اقدام موجود می‌دیدند؛ POST سمت سرور همیشه 403 بود (WBS-03b) ⇒ UI visibility ≠ security boundary.
- **اصلاح (فقط ارائه):** پنل «تصمیم ناظر پروژه» با `@can('decide', $phase)` … `@endcan` در `resources/views/wbs-phases/show.blade.php` محافظت شد — فقط مصرف همان `WbsPhasePolicy::decide` موجود از طریق convention بلید؛ بدون توانایی/قانون مجوز جدید، بدون تغییر policy/route/controller/service/request. سایر بخش‌ها (اطلاعات/چک‌لیست/فرم افزودن) برای Admin بدون تغییر.

### 🎨 Part B — مهاجرت بصری modules/index
- `resources/views/modules/index.blade.php`: **۱۰ slate / ۲ رنگ قدیمی → 0 / 0** — `x-ui.panel` با `x-slot:header` (h2 نام پروژه + زیرنویس «پروژه #id» حفظ شد)، لینک `modules.show` («مدیریت ساختار ماژول») با توکن primary + focus-visible، کارت‌های ماژول در `ul[role="list"]`، وزن → `x-ui.badge variant="info"` با همان `number_format($module->weight, 2).%`، شمارش مراحل با همان متن «N مرحله استاندارد»، هر دو متن حالت خالی → `x-ui.empty-state` عیناً.
- **قرارداد حفظ‌شده:** GET `modules.index` + گروه role خواندن (admin|pm|employer|management|supervisor|viewer) · فیلتر `ProjectScope` کنترلر دست‌نخورده · صفر فرم/JS/فیلتر/پارامتر query/صفحه‌بندی (صفحه strictly read-only) · `withCount('tasks')` همچنان نمایش داده نمی‌شود (بدون متریک جعلی) · سطوح h1/h2 · RTL با فضاهای منطقی.

### 🧪 تست‌ها
- **جدید:** `tests/Feature/Ui/Phase5B8UiTest.php` — **9 passed (35 assertions):** تصمیم UI برای supervisor عضو فعال · تصمیم UI غایب برای Admin · POST غیرمجاز همچنان 403 + state/audit بدون تغییر · بخش‌های غیرتصمیم برای Admin · ۴ قرارداد modules/index (لینک مدیریت/وزن+مراحل/هر دو empty-state/h1 واحد).
- WBS فوکوس (۵ فایل: Phase5B7 · WbsChecklist · AuthorizationWiring · CrossProjectAttackMatrix · PhasePolicy): **97 passed (409 assertions)**.
- Modules+Audit (۵ فایل: ModuleUi · ModuleControllerAuthorization · ModulePolicy · ModuleForeignProjectAttack · AuditVisibility): **70 passed (202 assertions)**.
- Suite کامل PostgreSQL (`tms_testing`): **2 deprecated · 764 passed (3035 assertions) · 0 failed** (بیس 755 + ۹ جدید = صفر regression · 142s).
- `vendor/bin/pint --dirty --test`: **PASS** (فایل تست جدید یک‌بار با `--format agent` اصلاح شد: no_unused_imports / blank lines · 23 files).
- `npm run build`: **PASS** — `public/build/assets/app-B0fDkaaL.css`؛ `border-line-subtle · bg-surface-base · text-ink-muted · rounded-ui · bg-primary` با grep تأیید شد.

### 🖥️ Browser QA
- **انجام‌شده (Admin 0123456789 · dev server 127.0.0.1:8000 · assetهای build‌شده):** `modules/index` — دسکتاپ و موبایل 390، RTL سالم، overflow-X ندارد، هر دو empty-state درست رندر شد؛ `wbs-phases/1` — دسکتاپ 1440 و موبایل 390: **فرم تصمیم در DOM غایب** (`input[name="decision]`=0 · متن «تصمیم ناظر پروژه» غایب · `decide` action غایب) و صفحه 200 با اطلاعات/چک‌لیست کامل. صفر خطای console · همهٔ network 200 (شامل `app-B0fDkaaL.css`). هویت Admin از طریق دسترسی `/settings` و سایدبار تأیید شد. (اسکرین‌شات ابزار یک فریم عقب‌تر از navigation بود؛ a11y-tree و evaluate زنده تأیید کردند.)
- **انجام‌شده برای Supervisor: نه** — محدودیت شناخته‌شدهٔ drift اسکیمای دیتابیس توسعه: جدول `project_memberships` در `tms` وجود ندارد (information_schema count=0) ⇒ scope queryهای سیاست می‌شکند. **بدون migrate** و بدون ساخت کاربر گزارش شد: `Supervisor Browser QA NOT EXECUTED — blocked by known development database schema drift (project_memberships missing in dev DB tms).`
- **نکته:** یک آیتم چک‌لیست (`id=1`، `created_at=2026-10-10 03:23:01Z`) حین QA روی dev DB ظاهر شد — خارج از اقدامات این عامل (فقط GET/خواندن صادر شد؛ احتمالاً تعامل کاربر در مرورگر مشترک). راستی‌آزمایی: phase 1 همچنان `pending` و `supervisor_approved_at=null` · `modules=0` · `activity_logs=0` ⇒ هیچ mutation دیگری رخ نداد.

### 🔒 Safety
صفر DB/Migration · صفر Controller/Request/Service/Policy/Model/Route · صفر تغییر رفتار Auth/Permission/ProjectScope (فقط `@can` ارائه‌ای) · صفر تغییر lifecycle/decide semantics/audit · Heading Architecture · FontAwesome/Select2/Datepicker دست‌نخورده · `template/` و `v19_out.txt` دست‌نخورده · صفر commit · صفر push · staged=0 · HEAD `08b2629`.

### 🚦 Final Gate
**`READY FOR PHASE 5B-9`**
