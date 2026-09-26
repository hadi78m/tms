# ACTION_TRACKER.md - گزارش تغییرات و ریفکتورهای بزرگ

> **هدف:** ثبت دقیق فایل‌های تغییریافته و خلاصه‌ای از اصلاحات انجام‌شده در جلسات کاری سنگین.

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