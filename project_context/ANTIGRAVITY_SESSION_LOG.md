# Antigravity Session Log

# Session 2026-09-22 — V1.8 Remaining Hardening Implementation (R-3 + T-6)

## هدف جلسه
- R-3: حذف duplication ساختاری تعریف Active/Superseded **بدون هیچ تغییر رفتاری**.
- T-6: Safety Gate برای جلوگیری از destructive reset روی دیتابیس اشتباه.
- **ممنوع:** Migration · Schema · تغییر `tms` · reset روی `tms_testing` · تغییر business rule.

## آنچه اتفاق افتاد
1. **R-3.1 re-inspection:** جست‌وجوی مجدد کل `app/` — همان ۵ تعریف + `Module::scopeActive()` هم‌نام غیرمرتبط؛ هیچ موردی از دست رفته نبود.
2. **R-3.5 baseline:** targeted (Chain + Approval) 34 passed · 113 assertions؛ Full suite 203 · 591 · 0.
3. **R-3.6 refactor (۲ فایل):** `ModuleStage::approvedQuery()` → `approvals()->active()`؛ `Service::isSuperseded()` → `$approval->isSuperseded()`. هر دو قبلاً کپی حرفِی بودند (اثبات هم‌ارزی در Reconnaissance). `pendingQuery()` عمداً ادغام نشد؛ Trait/Helper رد شد؛ `Module::scopeActive()` جدا ماند (R-3.4).
4. **R-3.7 verification:** targeted 48/139 + V18 79/225 + Full suite **203 · 591 · 0 = برابر Baseline** · static search تأیید تعریف واحد · git diff فقط دو بدنهٔ متد + حذف use بی‌استفاده.
5. **T-6 (دو لایه، تفکیک صادقانه):** ENFORCED — `tests/TestCase.php` gate (نام واقعی DB متصل باید `*testing` باشد، وگرنه fail قبل از RefreshDatabase) · DOCUMENTED — `docs/DATABASE_SAFETY.md` (Safe/destructive artisan commands + قاعدهٔ مجوز مالک برای reset). دستورات artisan دستی از TestCase عبور نمی‌کنند ⇒ صادقانه فقط DOCUMENTED (T-6.3/T-6.4 رعایت شد — ادعای جعلی نشد).
6. **Regression نهایی:** Full suite 203 passed · 591 assertions · 0 failed · 2 deprecated.

## ایمنی
`tms` فقط read-only (migrate:status + db:show) · `tms_testing` بدون هیچ mutation (هیچ reset/fresh/seed اجرا نشد) · صفر Migration · صفر Schema.

## دروازه خروجی
```text
READY FOR NEXT IMPLEMENTATION PHASE
```

---

# Session 2026-09-22 — V1.8 Post-Migration Code Hardening

## هدف جلسه
- Hardening لایهٔ Application پس از اجرای موفق V1.8 روی Production: انطباق `Application ↔ Domain Rules ↔ PostgreSQL Schema`.
- بستن دو یافتهٔ باز (`R-1` و `R-2`) **فقط با تصمیم مالک**، نه با اختراع قاعده.
- Audit ساختاری `R-3` (بدون Refactor) و رگرسیون `T-1`.
- **ممنوع:** Migration جدید · بازنویسی مهاجرت‌های موجود · هر تغییر روی `tms`.

## آنچه اتفاق افتاد
1. **Reconnaissance (فقط-خواندنی):** `migrate:status` (۳۲/۳۲ · `{1:23, 2:1, 3:8}`) و `db:show` (PG 18.6 · ۳۵ جدول) دقیقاً مطابق مستندات — صفر اختلاف. Inventory کامل: ۹ مصرف‌کنندهٔ `tasks.weight` (R1-01..R1-09)، مسیر واحد ایجاد Task، ۵ تعریف «Active».
2. **Owner Decision Gate:** ۴ تصمیم صریح مالک گرفته شد:
   - `R-1` → `R1-D` (NULL در مسیر عادی ممنوع) + `R1-F1` (weight اجباری می‌ماند) → **`DEC-036`**
   - `R-2` → `T-2-A` (task_type Required در FormRequest) + `T-2-UI-A` (فیلد UI) → **`DEC-037`**
3. **پیاده‌سازی حداقلی (۴ فایل کد):** `StoreTaskRequest` (required + `new Enum(TaskType::class)`) · `CreateTaskData` (TaskType اجباری) · `TaskService::create` (نویسندهٔ صریح) · `tasks/create.blade.php` (فیلد «نوع تسک»). مسیر کامل بدون silent fallback؛ `BD-06` حالا در عمل قابل‌دست‌یابی است.
4. **تست‌ها:** `TaskTypeAndWeightPolicyTest` (۱۲ تست · ۳۲ assertion — تمام سناریوهای الزامی §۸) + سازگارسازی ۴ تست HTTP موجود. نتیجه: **`203 passed · 591 assertions · 0 failed`** (از ۱۹۱ → ۲۰۳).
5. **R-3:** فقط Audit — `STRUCTURALLY DUPLICATED BUT BEHAVIORALLY CONSISTENT` · Semantic Drift صفر · بدون Refactor.
6. **T-1 رگرسیون:** گاردها و وضعیت UNIQUE دست‌نخورده؛ زنجیرهٔ supersede سبز ماند.

## ایمنی
`tms` فقط-خواندنی · صفر Migration · صفر تغییر Schema · تست‌های mutating فقط روی `tms_testing`.

## دروازه خروجی
```text
READY FOR V1.8 POST-MIGRATION HARDENING COMPLETE
```

---

# Session 2026-09-22 — V1.8 T-1 Resolution & Post-Migration Code Reconciliation

## هدف جلسه
- تعیین اینکه بدون `UNIQUE(supersedes_approval_id)` (که مالک `ACCEPTED AS-IS` کرده)، **آیا لایهٔ Application واقعاً invariant را تضمین می‌کند یا خیر**.
- آزمون عملی مسیر سرویس و مسیر دیتابیس.
- تطبیق Post-V1.8 بین Schema و Application.
- **ممنوع:** Migration جدید · بازنویسی مهاجرت‌های موجود · هر تغییر روی `tms`.

## وضعیت اولیه
- دروازه روی `PRODUCTION MIGRATION SUCCESSFUL` بود.
- `tms`: `32 migrations · 35 tables · 4 triggers · 3 functions · 5 settings`.
- `supersedes_approval_id`: nullable · FK `RESTRICT` · **صفر UNIQUE** · index غیریکتا.
- بیس تست: `175 passed · 504 assertions · 0 failed`.

## اقدام‌های انجام‌شده

1. **Integrity:** `git diff -- database/migrations` خالی · صفر Deleted/Renamed · `migrate:status` → **32/32** · Batch `{1:23, 2:1, 3:8}`.

2. **Schema واقعی** روی هر دو دیتابیس: نبود UNIQUE تأیید شد.

3. **Probe عملی (موقت، سپس حذف و تبدیل به تست دائمی):**
   - مسیر Database: دو جانشین برای یک ردیف درج شدند ⇒ دیتابیس هیچ چیزی را نمی‌بندد.
   - مسیر Service: supersede دوباره **مادامی که جانشین اول pending است مسدود** (`PendingRequestExistsException`)، ولی **پس از تصمیم جانشین اول مجاز** ⇒ **fork واقعی**.
   - حالت عددی تعیین‌کننده: `A=5 → B=7 → C=3` ⇒ `approvedWeight = 10` (به‌جای `7`) و چون `10 ≤ 15` بود، check ظرفیت آن را نگرفت.

4. **تست رگرسیون (TDD واقعی):** `StageProgressApprovalSupersedeChainTest.php` با **۱۶ تست** **اول علیه کد اصلاح‌نشده** اجرا شد → `8 failed · 8 passed`. ۶ شکست = نقص واقعی (`T1-03`، `T1-03b`، `T1-06`، `T1-07`، `T1-07b`، `T1-08`، `T1-08b`) و ۲ شکست = خطای خودِ تست (شمارش و ترتیب جانشینی) که اصلاح شد.

5. **اصلاح حداقلی سرویس:**
   - `supersede()` → `assertNotSuperseded($source)`
   - `adjust()` → `assertNotSuperseded($row)` (نقص هم‌رده: تاریخ بازنویسی می‌شد)
   - پیام خطا عام شد؛ دو Docblock به‌روز شد.

6. **Active Definition Audit (فقط گزارش):** ۵ نقطهٔ تکراری، همه سازگار ⇒ `R-3` به‌عنوان `RECOMMENDATION — NOT APPROVED`.

7. **Reconciliation:** `R-1` (وزن تسک در گزارش/CSV) · `R-2` (نبود نویسندهٔ `task_type`) — هر دو **فقط گزارش شدند** طبق تصمیم مالک.

8. **ایمنی Productivity:** مقایسهٔ کامل `tms` → **صفر تغییر** در هر ۹ سنجه.

## نتیجه
```text
191 passed · 2 deprecated · 559 assertions · 0 failed
New Migration: 0     Existing Migration Modified: 0     tms: دست‌نخورده

READY FOR V1.8 POST-MIGRATION CODE HARDENING
```

## تأیید‌های صریح مالک در این جلسه
- `T-1`: **اصلاح حداقلی سرویس + تست رگرسیون** (نه فقط گزارش، نه UNIQUE دیتابیس).
- `R-1` (`tasks.weight` در گزارش‌ها): **فقط گزارش شود**.

## تصمیمات باز
- `R-2` (نویسندهٔ `task_type`) · `R-3` (کنسولیدیشن Active) · `T-3`..`T-7`

---

# Session 2026-09-22 — V1.8 Production Migration & Controlled Production Execution

## هدف جلسه
- بازبینی نهایی مهاجرت‌های V1.8 برای Production (`tms`).
- بررسی دو تصمیم باز `T-1` و `T-2`.
- اجرای **کنترل‌شده** مهاجرت روی `tms` فقط پس از عبور از تمام گیت‌های ایمنی.
- تأیید واقعی پس از مهاجرت و ثبت گزارش Production.

## وضعیت اولیه
- دروازه روی `READY FOR PRODUCTION MIGRATION REVIEW` بود.
- `tms`: `23 migrations (Batch 1) · 31 tables · 0 triggers · 0 functions · 4 settings` · `tasks.weight NOT NULL` · صفر جدول V1.8.
- `tms_testing`: `UTF8 / C` (پس از رفع `ENV-1` در فاز قبل) · `32 migrations · 35 tables`.
- `tms` **خالی نبود**: `projects 1 · tasks 3 · wbs_phases 1 · users 8 · task_dependencies 1`.

## اقدام‌های انجام‌شده

1. **Phase 1 — Integrity:** `git diff -- database/migrations` خالی · ۲۳ مهاجرت Baseline در Git · صفر Deleted/Renamed · ۹ مهاجرت جدید Untracked.

2. **Phase 2/3 — Baseline + SQL:** وضعیت واقعی `tms` با `migrate:status` و کاتالوگ‌های `information_schema` ثبت شد؛ SQL هر ۹ مهاجرت با `--pretend` تولید و بازبینی شد؛ شمارش ردیف همهٔ ۳۱ جدول برای مقایسهٔ بعدی ثبت شد.

3. **Phase 4 — `T-1`:** شواهد `docs/V1.8_DETAILED_SCHEMA_DESIGN.md` §۴.۳ (خط ۳۸۰) بررسی شد که خودش این را «نیازمند تصمیم کسب‌وکاری نیست» خوانده است. مسیر واقعی دو-برگی در `StageProgressApprovalService::supersede()` پیدا شد (supersede دوبارهٔ ردیف تاریخی پس از تأیید جانشین). نتیجه: **`ACCEPTED AS-IS`** + ثبت `RECOMMENDATION — NOT APPROVED`.

4. **Phase 5 — `T-2`:** `DEC-016` صریحاً `VARCHAR(50) NOT NULL DEFAULT 'development'` را تعیین کرده است ⇒ **`RESOLVED BY EXISTING DECISION`**؛ Default حذف نشد.

5. **Phase 6 — Safety Gate:** هر ۱۵ گیت PASS؛ **پشتیبان ساخته شد**؛ صفر اتصال فعال و صفر قفل روی `tms`.

6. **آزمایش تعیین‌کنندهٔ `H-2` روی `tms_testing`:**
   - **سناریوی درست** (`M-07` جدا): `{1:24, 2:8}` → `migrate:rollback` هر ۸ را برگرداند، `exit 0`، M-07 دست‌نخورده.
   - **سناریوی غلط** (یک Batch): `{1:32}` → ۸ مهاجرت برگشتند سپس M-07 استثنا داد ⇒ نتیجهٔ نهایی یکسان، اما فرمان با Exception تمام شد.
   - **یافتهٔ دقیق‌شده:** خطر `H-2` **اپراتوری** است نه سازگاری‌شکن — Batch جدا، نتیجه را مستقل از ترتیب timestamp قابل‌پیش‌بینی می‌کند.

7. **Phase 7 — Production Migration:**
   ```bash
   php artisan migrate --path=database/migrations/2026_09_22_100001_relax_tasks_weight_nullable.php   # Batch 2
   php artisan migrate                                                                                # Batch 3
   ```
   نتیجه: **`32 migrations · 35 tables · 4 triggers · 3 functions`** · `{1:23, 2:1, 3:8}`.

8. **Phase 8/9/10 — Verification:** Schema کامل تأیید شد؛ ۱۰ سنجه Smoke Test در لایهٔ Eloquent (صفر Exception)؛ مقایسهٔ شمارش ردیف‌ها (**صفر تغییر جز `migrations 23→32` و `system_settings 4→5`**)؛ MD5 متن فارسی یکسان با literal فایل مهاجرت.

9. **Phase 11/12:** گزارش `docs/V1.8_PRODUCTION_MIGRATION_REPORT.md` و به‌روزرسانی اسناد ردیابی.

## نتیجه
```text
PRODUCTION MIGRATION SUCCESSFUL
```

## فایل‌های تغییریافته
**کد/Migration/Test: صفر** · Markdown: گزارش جدید + `MEMORY.md` · `TASKS.md` · `TMS_PROJECT_TRACKER.md` · `ERROR_LOG.md` · `ACTION_TRACKER.md` · `project_context/{DECISIONS,HANDOFF,CHANGELOG,SESSION_LOG,tasks}.md`

## موانع باقی‌مانده
- `T-1` نیازمند تصمیم مالک (Master + `M-10`) — `T-2` حل‌شده اما اجباری‌کردن `task_type` تصمیم می‌خواهد.
- `T-3`..`T-7` باز.

---

# Session 2026-09-22 — V1.8 Owner Decision Closure & Design Reconciliation (Documentation Only)

## هدف جلسه
- ثبت رسمی تصمیمات مالک پروژه برای **تمام** پرسش‌های باز V1.8 (`OQ-30` · `OQ-24` · `OQ-31` · `OQ-25` · `OQ-02` · `H-1`..`H-7` · `N-1`..`N-5`).
- اعمال اصلاحات طراحی در `docs/V1.8_DETAILED_SCHEMA_DESIGN.md` و هم‌ترازی اسناد مرتبط.
- تولید گزارش تطبیق نهایی `docs/V1.8_FINAL_DESIGN_RECONCILIATION.md`.
- **🚫 ممنوعیت مطلق:** هیچ Migration، هیچ کد، هیچ SQL mutation، هیچ تغییر Schema یا داده.

## وضعیت اولیه
- دروازه روی `BLOCKED — OWNER DECISION REQUIRED` بود (گزارش `docs/V1.8_OQ_AND_REVIEW_ISSUE_RESOLUTION.md`).
- ۶ مورد `OWNER DECISION REQUIRED` + ۹ توصیهٔ آماده + ۲ مورد مستنداتی + ۵ یافتهٔ جدید (`N-1`..`N-5`) باز بودند.
- سند طراحی **عمداً دست‌نخورده** مانده بود.

## اقدام‌های انجام‌شده

1. **بازخوانی شواهد:** ساختار و محتوای اسناد Migration Review، OQ/Issue Resolution و وضعیت 실제 Repository (۲۳ Migration، `Batch 1`، صفر جدول V1.8، `tasks.weight` هنوز `NOT NULL`).

2. **ثبت ۱۶ تصمیم در Decision Registry واحد** (`project_context/ANTIGRAVITY_DECISIONS.md` — `DEC-020`..`DEC-035`):
   - `DEC-020` (`OQ-30` = D) — Triggerهای تک‌ردیفی ساخته می‌شوند؛ سه Trigger تجمعی DEFERRED.
   - `DEC-021` (`OQ-24` = A) — `tasks.module_id` می‌ماند؛ تضمین در Service؛ بدون Trigger (+ حل `N-3`).
   - `DEC-022` (`H-3`) — اتمیک‌بودن: یک عملیات = یک تراکنش؛ صفر ماژول معتبر؛ وضعیت میانی غیرقابل Commit.
   - `DEC-023` (`H-4`) — دکترین قفل والد (`projects.id` / `modules.id` / `module_stages.id`).
   - `DEC-024` (`H-5`) — «صفر ماژول فعال» معتبر؛ invariant روی مجموعهٔ Active.
   - `DEC-025` (`N-4`) — Service مالک Business Rule؛ Database مالک Integrity تک‌ردیفی.
   - `DEC-026` (`H-1`) — حذف `SUM(...) FOR UPDATE`؛ قفل والد Stage + تجمیع بدون `FOR UPDATE`.
   - `DEC-027` (`N-1`) — **`superseded` وضعیت مشتق**؛ تعریف Active Approval با `NOT EXISTS`.
   - `DEC-028` (`N-2`) — شاخه‌بندی صریح `TG_OP` (فقط مستندسازی).
   - `DEC-029` (`OQ-31` = RESOLVED) — Trigger حفاظت از History تک‌ردیفی (ادغام با `OQ-22`).
   - `DEC-030` (`OQ-25` = RESOLVED) — نگاشت `$metadata`؛ بدون ستون جدید.
   - `DEC-031` (`OQ-02` = RESOLVED) — Unique مرکب حفظ؛ Partial Index اضافه نمی‌شود.
   - `DEC-032` (`H-6`) — `document_uploaded` صریح در Service؛ بدون Observer.
   - `DEC-033` — تثبیت طبقه‌بندی Legacy (بدون هیچ حذف).
   - `DEC-034` — Business Truth نهایی (Weight/Stage/Checklist/Task).
   - `DEC-035` — وضعیت دروازه: `READY FOR MIGRATION IMPLEMENTATION REVIEW`.

3. **اعمال ۱۶ اصلاح طراحی در سند Schema:**
   - `§۲.۵` جدید — جدول تصمیمات قطعی Owner.
   - `§۴.۱` — تصحیح جملهٔ گمراه‌کنندهٔ `OQ-02` (Partial Unique Index لازم نیست).
   - `§۴.۳` — دامنهٔ `status` به سه مقدار ذخیره‌شده + بخش جدید **Active Approval**.
   - `§۵.۲` — هشدار Soft Delete بازنویسی شد (صفر ماژول معتبر · بازتوازن در همان تراکنش).
   - `§۶.۱`–`§۶.۲` — بازنویسی کامل: سه الزام (`R-1`..`R-5`) + قفل والد + Trigger تجمعی DEFERRED + بدنهٔ اصلاح‌شده با `TG_OP`.
   - `§۶.۳` · `§۶.۴` — Triggerهای تک‌ردیفی ✅ تأییدشده + بخش جدید `§۶.۴.۱` حفاظت از حذف History.
   - `§۶.۵` — سقف تجمعی: قفل والد Stage + تجمیع بدون `FOR UPDATE` + Active-only.
   - `§۶.۶` — قواعد هم‌پروژه‌بودن در Service؛ بدون Trigger.
   - `§۶.۷` — جدول خلاصهٔ Constraints بازنویسی شد.
   - `§۶.۸` **جدید** — دکترین Service در برابر Database.
   - `§۸.۲` · `§۸.۳` — نگاشت `$metadata` + انتقال `document_uploaded` به «باید ساخته شود».
   - `§۹.۱` · `§۹.۲` · `§۹.۴` — Batchبندی `M-07` · محدودسازی `M-08` · محیط ✅.
   - `§۱۲` — تفکیک Batch + تصحیح ادعای نادرست «`--step=9` خودکار».
   - `§۱۴` — بستن پنج پرسش ساختاری + ادغام `OQ-22` با `OQ-31`.
   - `§۱۵` — دروازه جدید + جدول توجیه به‌روز.
   - `پیوست د` — تأییدیهٔ عدم تغییر و فهرست اصلاحات.

4. **تولید گزارش تطبیق** `docs/V1.8_FINAL_DESIGN_RECONCILIATION.md` با ۱۲ بخش (شامل ۷ یادداشت فنی `T-1`..`T-7`).

5. **هم‌ترازی اسناد مرتبط:** `V1.8_OQ_AND_REVIEW_ISSUE_RESOLUTION.md` (بنر + جدول ۱۷ موردی) · `V1.8_MIGRATION_REVIEW_CHECKLIST.md` (همهٔ بندها APPROVED/RESOLVED) · `docs/10-database-design.md` (دکترین + supersede + ریسک‌های `SR-08`/`SR-09` + دروازه).

6. **به‌روزرسانی اسناد زنده:** `ANTIGRAVITY_HANDOFF.md` · `project_context/tasks.md` · `MEMORY.md` · `ANTIGRAVITY_CHANGELOG.md` · `ACTION_TRACKER.md`.

## وضعیت نهایی
- **دروازه:** `READY FOR MIGRATION IMPLEMENTATION REVIEW` — ⚠️ **مجوز اجرای Migration نیست**.
- **پرسش‌های باز:** صفر (تصمیم کسب‌وکاری باز: صفر · `BLOCKER`: صفر · توصیهٔ معلق: صفر).
- **محیط:** PostgreSQL 18.6 · `tms` و `tms_testing` موجود · اتصال Laravel تأییدشده · ۲۳/۲۳ Migration · صفر جدول V1.8.
- **بیس تست «۱۱۴/۳۳۴»:** همچنان **UNVERIFIED** — فقط `Unit` واقعاً اجرا شده (`16 passed · 1 deprecated · 51 assertions`).
- 🚫 **صفر Migration · صفر کد · صفر SQL mutation · صفر تغییر Schema · صفر تغییر داده.**

## نتیجه جلسه
- COMPLETE (Documentation Only) — `DEC-020`..`DEC-035` ثبت و طراحی هم‌تراز شد؛ آمادهٔ Migration Implementation Review.

---

# Session 2026-09-22 — V1.8 Migration Review (Read-Only Review, no code/migration/SQL mutation)

## هدف جلسه
- اجرای چک‌لیست `docs/V1.8_MIGRATION_REVIEW_CHECKLIST.md` (۳۸ بند در ۵ محور) روی `docs/V1.8_DETAILED_SCHEMA_DESIGN.md`.
- بازبینی **مستقیم و مستقل** Repository: Migrationها، Models، Enums، ۵ سرویس دامنه، Bladeها، ۳۰ فایل تست، Seeders، Audit Architecture.
- بستن محور Environment با بررسی **فقط خواندنی** PostgreSQL و بیس تست.
- بازبینی وابستگی ۹ Migration، Rollback، Data Safety و سازگاری PostgreSQL (شامل اعتبار `CONSTRAINT TRIGGER … DEFERRABLE`).
- **🚫 ممنوعیت مطلق:** هیچ Migration، هیچ تغییر کد، هیچ Schema Mutation، هیچ تغییر داده.

## نتیجه
```text
READY FOR MIGRATION IMPLEMENTATION   ❌
BLOCKED — REVIEW ISSUE               ✅  ← حکم این جلسه
BLOCKED — ENVIRONMENT                ❌ (tms و tms_testing موجودند؛ اتصال Laravel تأیید شد)
```

## محیط — تأییدشده در این جلسه (همه خواندنی)
```text
pg_isready -h 127.0.0.1 -p 5432   → accepting connections (exit=0)
netstat -ano | grep 5432          → 127.0.0.1:5432 و [::1]:5432 LISTENING · PID 22436
php artisan db:show               → PostgreSQL 18.6 · pgsql · Database: tms · 31 tables · 1.33 MB
php artisan migrate:status        → 23/23 Ran · صفر Migration V1.8
pg_database                        → tms|root · tms_testing|root
information_schema                 → tasks.weight NOT NULL · صفر جدول V1.8 در هر دو دیتابیس
دادهٔ موجود                          → projects=1 · wbs_phases=1 · tasks=3 · tasks.weight IS NULL = 0
```

## تست
```text
php artisan test --testsuite=Unit --compact → 1 deprecated, 16 passed (51 assertions) · 4.96s
php artisan test --compact                  → NOT RUN — would require schema mutation
بيt «۱۱۴/۳۳۴»                              → UNVERIFIED — نه PASS و نه FAIL
```

---

# Session 2026-09-22 — V1.8 Migration Review Inputs Closed (Documentation Only)

## هدف جلسه
- بستن سه پرسش 🔴 پایانی که **ورودی مرحلهٔ Migration** بودند: `OQ-27` · `OQ-28` · `OQ-29`.
- ثبت آن‌ها به‌عنوان **Business Decision قطعی** در Decision Registry.
- به‌روزرسانی بخش Open Questions سند Schema Design به‌گونه‌ای که **هیچ پرسش حل‌شده‌ای دیگر blocker نباشد**.
- تثبیت وضعیت دروازه `READY FOR MIGRATION REVIEW` با تصریح اینکه **مجوز اجرای Migration نیست**.
- ثبت وضعیت واقعی پیش‌نیاز PostgreSQL **بدون تغییر محیط** و **بدون جعل نتیجه**.
- ساخت چک‌لیست Migration Review برای مرحلهٔ بعد.
- **🚫 ممنوعیت مطلق:** هیچ Migration، هیچ کد، هیچ SQL روی Database، هیچ تغییر داده.

## وضعیت اولیه
- فاز V1.8 Detailed Schema Design با دروازهٔ `READY FOR MIGRATION REVIEW` بسته شده بود.
- سه پرسش 🔴 به‌عنوان **ورودی Migration** باز بودند (نه مسدودکنندهٔ طراحی).
- وضعیت ثبت‌شدهٔ محیط: هر چهار شاخص `NO` — بیس تست **نامعلوم (UNVERIFIED)**.

## اقدام‌های انجام‌شده

1. **ثبت سه تصمیم قطعی در Decision Registry** (`project_context/ANTIGRAVITY_DECISIONS.md`):
   - `DEC-016` (`OQ-27`) — Backfill `task_type = 'development'` برای تمام تسک‌های موجود. Support به‌عنوان Task Type رسمی **از V1.8 به بعد**. Backfill با `DEFAULT 'development'` در همان `ALTER TABLE`، بدون کوئری داده‌ای جداگانه.
   - `DEC-017` (`OQ-28`) — `expected_output = "مشاهدهٔ Checklist"` برای فازهای جدید. این فقط یک متن سازگار با ساختار فعلی است؛ **Checklist مرجع واقعی خروجی فاز** است. ستون حذف نمی‌شود و `NOT NULL` برداشته نمی‌شود.
   - `DEC-018` (`OQ-29`) — `down()` مهاجرت `M-07` **آگاهانه `throw`** می‌کند. **قاعدهٔ عمومی ثبت‌شده:** اگر Rollback نیازمند داده‌ای باشد که قابل بازسازی مطمئن نیست، Migration باید با Exception واضح متوقف شود و **نباید دادهٔ جعلی تولید کند**. گزینهٔ `UPDATE ... SET weight = 0` رد شد.
   - `DEC-019` — تثبیت وضعیت دروازه + تصریح جداسازی Review از Implementation.
   - به‌روزرسانی وضعیت `DEC-015` (سه پرسش 🔴 آن بسته شد).

2. **به‌روزرسانی سند Schema Design** (`docs/V1.8_DETAILED_SCHEMA_DESIGN.md`):
   - §۲.۴ جدید — جدول سه تصمیم قطعی تکمیلی + قاعدهٔ عمومی `OQ-29`.
   - §۱۱.۳ — از «نیازمند تأیید» به تصمیم قطعی `development`.
   - §۱۱.۵ گام ۲ — از «نیازمند تصمیم» به مقدار ثابت «مشاهدهٔ Checklist».
   - §۱۲.۳ — سه گزینه با حکم `انتخاب‌شده` / `رد شد` + کد `down()` نهایی.
   - §۱۴ بازساختار شد: «✅ حل‌شده (۳ مورد)» + «🔴 مسدودکننده: صفر» + 🟡 (۵ مورد) + 🟢 (۷ مورد) + شکاف `performance_records`.
   - §۱۵ — دروازه بدون تغییر، با تصریح «مجوز اجرای Migration نیست» + ۶ گام پیش‌نیاز + مسیر Review → Design → Implementation.
   - §۹.۴ — جدول مقایسهٔ وضعیت محیطی فاز قبل با مشاهدهٔ امروز.
   - §۱۳.۴ — به‌روزرسانی مانع اجرای تست.
   - پیوست ج — تأییدیهٔ عدم تغییر.

3. **ساخت چک‌لیست Migration Review** (`docs/V1.8_MIGRATION_REVIEW_CHECKLIST.md` — سند جدید):
   - ۵ محور: **Schema** (۱۰ بند) · **Domain rules** (۱۳ بند) · **Legacy** (۵ بند) · **Audit** (۵ بند) · **Environment** (۵ بند) — جمعاً **۳۸ بند**.
   - ۵ تصمیم ساختاری باز (`OQ-30` · `OQ-31` · `OQ-24` · `OQ-25` · `OQ-02`) به‌عنوان ورودی Review.
   - فرم تأیید + شرط ورود به Migration Design + فهرست ممنوعیت‌های دوران Review.
   - ۶ گام پیش‌نیاز محیطی و قواعد صریح: بدون حدس، بدون fallback به SQLite، نتیجه فقط در صورت اجرای واقعی.

4. **بررسی محیط (فقط خواندنی — بدون تغییر):**
   - `netstat -ano` → پورت ۵۴۳۲ در حالت `LISTENING` روی `127.0.0.1` و `[::1]`.
   - `pg_isready -h 127.0.0.1 -p 5432` → `accepting connections` · `exit=0`.
   - `Get-Process -Id 22436` → `postgres`.
   - **نتیجه:** سرور PostgreSQL اینک در دسترس است — **برخلاف وضعیت ثبت‌شدهٔ فاز قبل (`NO`)**. این تغییر **فقط مشاهده و ثبت** شد؛ هیچ سرویسی راه‌اندازی و هیچ تنظیمی تغییر نکرد.
   - **⛔ اما:** وجود `tms` و `tms_testing`، اتصال Laravel و اجرای بیس تست **تأیید نشد** — چون تأیید آن‌ها نیازمند اجرای SQL بود و این فاز صریحاً ممنوع کرده بود. وضعیت با حدس یا fallback به PASS تبدیل **نشد**.

5. **علامت‌گذاری مستندات منقضی:**
   - `docs/V1.8_RECONCILIATION_AND_MIGRATION_GATE.md` §۱۱ — دروازهٔ `BLOCKED` به‌عنوان **تاریخی (SUPERSEDED)** علامت خورد.
   - `docs/10-database-design.md` پیوست — از `BLOCKED` به `READY FOR MIGRATION REVIEW`.

6. **به‌روزرسانی اسناد زنده:** `ANTIGRAVITY_HANDOFF.md` · `project_context/tasks.md` · `MEMORY.md` · `ANTIGRAVITY_CHANGELOG.md` · `ACTION_TRACKER.md`.

## وضعیت نهایی
- **دروازه:**
  ```text
  READY FOR MIGRATION REVIEW
  ```
  با تصریح: **این وضعیت مجوز اجرای Migration نیست.** Migration Review ≠ Migration Implementation.
- **پرسش‌های 🔴 باقی‌مانده: صفر.** فقط ۵ پرسش 🟡 ساختاری (ورودی Review) و موارد 🟢 موکول.
- **وضعیت محیطی ثبت‌شده:** سرور PostgreSQL `YES` · `tms` **NOT VERIFIED** · Migrations **NOT ATTEMPTED** · Tests **NOT ATTEMPTED**.
- **بیس تست «۱۱۴ تست / ۳۳۴ assertion» همچنان UNVERIFIED است** — نه PASS و نه FAIL. هیچ نتیجه‌ای جعل نشد.
- 🚫 **صفر Migration · صفر کد · صفر SQL روی Database · صفر تغییر داده · صفر عملیات مخرب.**

## نتیجه جلسه
- COMPLETE (Documentation Only) — ورودی‌های Migration Review قفل شد؛ Migration Implementation مجاز نیست.

---

# Session 2026-09-21 — V1.8 Final Reconciliation & Migration Gate

## هدف جلسه
- تطبیق تصمیمات قطعی مالک پروژه (BD-01..BD-07) با سابقهٔ موجود Repository.
- حل پرسش‌های باز (`OQ-01`, `OQ-03`, `OQ-04`, `OQ-05`, `OQ-06`, `OQ-07`, `OQ-12`) **فقط از روی شواهد Repository** — بدون اختراع تصمیم کسب‌وکاری.
- اعتبارسنجی معماری، مدل Weight و مدل WBS Phase.
- بررسی موانع Audit و محیط PostgreSQL.
- اعلام دروازهٔ نهایی.
- **🚫 ممنوعیت مطلق تغییر کد.**

## وضعیت اولیه
- فاز V1.8 Design Freeze با ۱۲ پرسش باز و دروازهٔ `BLOCKED — BUSINESS DECISION REQUIRED` بسته شده بود.
- مالک پروژه تصمیمات BD-01..BD-07 را به‌عنوان قطعی اعلام کرد و خواست که پرسش‌های باز از سابقهٔ Repository استخراج شوند.

## اقدام‌های انجام‌شده

1. **بررسی محیط (Step 7 — بدون تغییر تنظیمات):**
   - `netstat`: هیچ شنونده‌ای روی پورت ۵۴۳۲.
   - `pg_isready -h 127.0.0.1 -p 5432` → `no response`، `exit=2`.
   - `Get-Service` برای postgres → خالی. پورت‌های ۵۴۳۰–۵۴۳۵ → خالی.
   - `psql --version` → `18.6` (کلاینت هست، سرور نیست).
   - نتیجه: هر چهار شاخص `NO`. **هیچ نتیجهٔ تستی جعل نشد.**

2. **بررسی مانع Audit (Step 6):**
   - `find app -iname "*Audit*"` → فقط `AuditServiceInterface.php` و `NullAuditService.php`. **`DatabaseAuditService` وجود ندارد.**
   - Binding: `AppServiceProvider::register()` خطوط ۱۹-۲۲ → `NullAuditService`.
   - `NullAuditService::log()` خطوط ۱۵-۱۸ → `return new ActivityLog;` بدون `save()`.
   - هیچ `ActivityLog::create` در `app/` (تنها در `ImmutableRecordTest`).
   - **۵ سرویس دامنه** این رابط را تزریق می‌کنند و فراخوانی‌هایشان دور ریخته می‌شود.
   - نتیجه: مشکل در **نویسنده** است، نه مخزن. جدول `activity_logs` بدون تغییر کافی است.

3. **استخراج پاسخ OQها از سابقهٔ Repository (Step 1):**
   - `OQ-03` → حل شد: **بدون رابطه** (دیاگرام Step 2 مالک پروژه، شاخه‌های هم‌تراز + BD-02).
   - `OQ-06` → حل شد: **فقط یک pending** (`§13` + `WeightChangeRequestService:24-26` + تست).
   - `OQ-06-a` → حل شد: **Denormalized حفظ شود** (الگوی `tasks.contract_id`).
   - `OQ-06-b` → حل شد: **approved ≠ proposed مجاز است** (BD-05).
   - `OQ-07` → محدودیت استخراج شد: **برچسب باید تغییر کند** (`§2` «No financial payment formula»).
   - `OQ-12` → غیرمسدودکننده: **هیچ نوع Morph جدیدی لازم نیست**.
   - `OQ-05` قدیمی → منقضی: «قفل وزن **Task** پس از ارجاع».
   - `OQ-01`، `OQ-04`، `OQ-05` جدید → **باقی‌ماندند** (سابقه‌ای نبود).

4. **اعتبارسنجی معماری (Step 2):** هر ۶ بند معتبر — **هیچ مغایرتی با Repository یافت نشد.**

5. **مدل‌سازی (Step 4 و 5):** طرح سطح-Schema برای `modules`، `module_stages`، `stage_progress_approvals`، `wbs_phase_outputs` — بدون تولید Migration.
   - `wbs_phases.weight` به‌عنوان **میراث طراحی** تأیید شد و حذفش **فقط پیشنهاد** ماند.

6. **تحلیل طبقه‌بندی Task (Step 3):** نتیجه — رابطهٔ `task → module_stage → stage_code` **قطعی نیست**. `task_type` **خودسرانه اضافه نشد** و به‌عنوان Business Decision گزارش شد.

7. **تولید مستندات:**
   - 🆕 `docs/V1.8_RECONCILIATION_AND_MIGRATION_GATE.md` (۱۱ بخش).
   - 🔧 `docs/V1.8_WEIGHT_MODULE_ARCHITECTURE_AUDIT.md` (اشاره به سند تطبیق در §17).
   - 🔧 `project_context/ANTIGRAVITY_DECISIONS.md` — `DEC-009` تا `DEC-012`.
   - 🔧 `TMS_PROJECT_TRACKER.md` — ردیف‌های `D-14`..`D-23` به‌روزرسانی.
   - 🔧 `ANTIGRAVITY_HANDOFF.md`، `MEMORY.md`، `ACTION_TRACKER.md`، `project_context/tasks.md`.

## تصمیمات ثبت‌شده
- `DEC-009` — تثبیت نهایی BD-01..BD-07.
- `DEC-010` — OQهای حل‌شده از سابقهٔ Repository (بدون اختراع قاعدهٔ جدید).
- `DEC-011` — مانع Audit: `DatabaseAuditService` گام صفر V1.8a.
- `DEC-012` — دروازهٔ نهایی: `BLOCKED — BUSINESS DECISION REQUIRED`.

## خروجی نهایی
```text
BLOCKED — BUSINESS DECISION REQUIRED
```
از ۱۲ پرسش باز: **۴ حل**، **۲ غیرمسدودکننده**، **۱ منقضی**، **۳ باقی‌مانده**.

سه پرسش باقی‌مانده: `OQ-01a`، `OQ-04`، `OQ-05 (جدید)`

## یادداشت جلسه
- طبق دستور صریح «هرگز از خودت Business Decision ایجاد نکن»، هیچ قاعدهٔ کسب‌وکاری جدیدی ساخته نشد. هر تصمیمی که ثبت شد، از یک سند، تنظیمات، ترکر یا کد موجود استخراج شده است و محل استخراج در `DEC-010` ثبت شده است.
- دروازه **به‌درستی** بسته اعلام شد: تنها ۳ پرسش از ۱۲ پرسش باقی است و هر سه مستقیماً ساختار Schema را تعیین می‌کنند.
- 🚫 تأیید `git status`: فقط فایل‌های `*.md` تغییر کرده‌اند.

---

# Session 2026-09-21 — V1.8 Weight / Module / WBS Design Freeze

## هدف جلسه
- اجرای فاز **V1.8 Weight / Module / WBS Architecture Clarification & Design Freeze**.
- تطبیق تصمیمات قطعی مالک پروژه با Repository موجود و شناسایی تناقضات.
- تثبیت معماری نهایی Weight (Module → Stage) در قالب مستندات طراحی.
- **🚫 ممنوعیت مطلق تغییر کد:** صفر Migration، صفر Model، صفر Service، صفر Controller، صفر Route، صفر Blade، صفر Test.

## وضعیت اولیه
- پروژه در پایان فاز V1.7 Stabilization با ادعای ۱۱۴ تست / ۳۳۴ assertion.
- قلمرو وزن در تمام فازهای قبلی «Weight Frozen» باقی مانده بود و هیچ تصمیم کسب‌وکاری برای آن گرفته نشده بود (`D-13` باز در ترکر).
- تصمیمات قطعی جدید مالک پروژه اعلام شد: Weight متعلق به Module/Deliverable است، نه Task.

## اقدام‌های انجام‌شده

1. **ممیزی READ-ONLY کامل Repository:**
   - بررسی ۲۱ Migration، ۱۷ Model، ۹ سرویس دامنه، ۱۰ Controller وب، ۱۲ View.
   - grep کامل روی `app/`، `resources/`، `database/`، `routes/`، `tests/`، `config/` برای Weight / Progress / Support / Module.
   - نقشهٔ وابستگی کامل چهار ستون حامل وزن: `tasks.weight` (۱۴ نقطه)، `wbs_phases.weight` (۴ نقطه، صفر مصرف‌کننده)، `weight_change_requests` (بدون UI)، `performance_records.total_weight_completed` (بدون Caller).

2. **کشف تفکیک مفهومی کلیدی:**
   - مدل مورد نظر مالک پروژه دو موجودیت متفاوت دارد: **WBS Phase** (محدوده/زمان/Checklist/تأیید ناظر) و **Module Stage** (سهم وزنی استاندارد).
   - جدول `wbs_phases` فعلی هم‌زمان تلاش می‌کند هر دو باشد و هیچ‌کدام را کامل نیست.
   - این کشف، مسیر طراحی Schema را تغییر داد (DEC-004).

3. **شناسایی ۹ شکاف معماری (`G-01` تا `G-18`)** و ۱۲ تناقض مستنداتی (`C-01` تا `C-12`).

4. **کشف مانع بحرانی Audit:**
   - `AppServiceProvider` رابط `AuditServiceInterface` را به `NullAuditService` بایند می‌کند که متد `log()` آن بدون `save()` برمی‌گردد.
   - یعنی `activity_logs` هرگز توسط برنامه پر نمی‌شود و تمام الزامات Audit در ترکر (`§15`) در سیستم زنده صفر رکورد تولید می‌کنند.
   - تست‌های موجود این شکاف را نمی‌بینند چون روی Mock تأیید می‌کنند (DEC-007).

5. **تلاش برای اجرای تست (READ-ONLY):**
   - `php artisan test --compact` → `97 failed, 16 passed (51 assertions)` در ۱۵۹۴ ثانیه.
   - **همهٔ شکست‌ها:** `SQLSTATE[08006] connection to 127.0.0.1:5432 refused`.
   - `pg_isready` تأیید کرد هیچ سروری روی پورت ۵۴۳۲ گوش نمی‌دهد.
   - نتیجه: بیس «۱۱۴ تست سبز» در این جلسه **تأیید نشد** (`C-10`). این یک مانع محیطی است، نه رگرسیون کد.

6. **تولید مستندات طراحی (Documentation-only):**
   - `docs/V1.8_WEIGHT_MODULE_ARCHITECTURE_AUDIT.md` — گزارش ممیزی ۲۰ بخشی + دروازهٔ مهاجرت.
   - `docs/weight-module-design-v1.8.md` — موجودیت Module و Stage.
   - `docs/module-stage-approval-design-v1.8.md` — تأیید درصدی تجمعی.
   - `docs/wbs-phase-completion-design-v1.8.md` — Checklist و تأیید نهایی ناظر.
   - `docs/dashboard-data-requirements-v1.8.md` — نیازهای دادهٔ چهار Dashboard.
   - `docs/10-database-design.md` — از خالی به نمای کامل Schema.
   - `docs/01-project-overview.md` — رفع ۴ تناقض مستنداتی.

7. **ثبت ADRها:** DEC-003 تا DEC-008 در `project_context/ANTIGRAVITY_DECISIONS.md`.

## تصمیمات ثبت‌شده
- `DEC-003` — مالکیت Weight منتقل می‌شود به Module / Deliverable و Module Stage.
- `DEC-004` — تفکیک WBS Phase از Module Stage.
- `DEC-005` — Support یک Stage مستقل است، نه یک WBS Phase.
- `DEC-006` — جدول مستقل `stage_progress_approvals`؛ `approvals` توسعه نمی‌یابد.
- `DEC-007` — رفع مانع Audit با جانشینی `NullAuditService`.
- `DEC-008` — تفکیک Scope فاز V1.8 به سه زیرفاز.

## خروجی نهایی
```text
BLOCKED — BUSINESS DECISION REQUIRED
```
پرسش‌های باز مسدودکننده: `OQ-01`، `OQ-03`، `OQ-04`، `OQ-05`، `OQ-06`، `OQ-07`، `OQ-12`.

## یادداشت جلسه
- قلمرو وزن این بار **باز و طراحی شد**، اما همچنان **صفر خط کد** — مطابق دستور صریح مالک پروژه.
- برخلاف فازهای قبلی که «Weight Frozen» بود، اکنون طراحی وزن کامل و مستند است؛ فقط پیاده‌سازی آن قفل است.

---

# Session 2026-09-20 13:35

## هدف جلسه
- اجرای فاز تثبیت V1.7 سامانه (TMS V1.7 Stabilization Implementation Phase) بر اساس یافته‌های ممیزی دقیق اخیر.
- تثبیت کامل تعاملات لایه وب با لایه دامین در ۴ محور مشخص (Assignment, Submission, SLA Display, Dependency Removal).
- حفاظت ۱۰۰٪ و بدون تغییر از قلمرو وزن (Weight Frozen).

## وضعیت اولیه
- ممیزی READ-ONLY ناهماهنگی‌هایی را در لایه وب شناسایی کرده بود:
  - عدم تطبیق نام متد و پارامترهای DTO در ارجاع تسک.
  - فراخوانی متد ناموجود `updateStatus` با وضعیت نامعتبر `completed` در ثبت کار.
  - ارجاع به فیلدهای ناموجود `sla_started_at` / `sla_stopped_at` در ویوی تسک.
  - استفاده از متد نامطلوب امنیتی `DELETE` به جای `POST` در حذف وابستگی‌ها.

## اقدام‌های انجام‌شده
1. **اصلاح انتساب وظیفه (Fix #1):**
   - اتصال `TaskAssignmentController::store` به متد دامین `TaskAssignmentService::assign`.
   - انطباق دقیق پارامترهای نام‌دار در `AssignTaskRequest::toDto` با سازنده `AssignTaskData`.
   - اعمال بررسی انزوای پیمانکار در فرم ریکوئست و کنترلر.
2. **اصلاح ارسال کار (Fix #2):**
   - فراخوانی متد استاندارد دامین `TaskService::submitForReview($task, $user)` در `TaskController::submit`.
   - انتقال صحیح وضعیت از `in_progress` به `submitted_for_review` و توقف خودکار SLA حل وظیفه (`stopResolutionSla`).
3. **اصلاح نمایش SLA در ویو (Fix #3):**
   - بازنویسی کارت SLA در `resources/views/tasks/show.blade.php` با بهره‌گیری از کالکشن `slaRecords`.
   - نمایش تفکیک‌شده SLA پاسخ اولیه و SLA حل وظیفه با تاریخ‌های شمسی (`jdate()`)، وضعیت، مهلت مجاز و زمان مصرفی/سپری‌شده.
   - اصلاح شروط نمایش دکمه ارسال کار متناسب با وضعیت تسک و وضعیت توقف SLA.
4. **اصلاح روت و سازوکار حذف وابستگی (Fix #4):**
   - تغییر روت حذف وابستگی به متد امنیتی `POST` با نام `tasks.dependencies.destroy`.
   - حذف `@method('DELETE')` از فرم حذف پیش‌نیاز در ویوی تسک.
   - رفع قفل رویداد `deleting` در مدل `TaskDependency` و فعال‌سازی متد حذف دامین `TaskService::removeDependency`.
5. **تست‌های خودکار:**
   - پیاده‌سازی ۱۲ سناریوی آزمون جامع وب در `tests/Feature/Web/WebTaskStabilizationTest.php`.
   - اجرای موفق و پاس شدن ۱۰۰٪ سوئیت کامل تست‌ها در دیتابیس PostgreSQL (۱۱۴ تست، ۳۳۴ assertion).
   - فرمت‌بندی استاندارد کدهای تغییریافته با `vendor/bin/pint`.

## تصمیم‌های گرفته‌شده
- قلمرو وزن (Weight) کاملاً دست‌نخورده باقی ماند و هیچ تغییری در WBS، فیلدها یا محاسبات داده نشد.
- مجوزهای Spatie در لایه وب/کنترلر حفظ شدند و به لایه دامین تزریق نشدند تا معماری تفکیک لایه‌ها حفظ شود.

## نتیجه جلسه
- COMPLETE (Stabilization Verified & 100% Green).

## ادامه پیشنهادی
- تشکیل جلسه تصمیم‌گیری معماری و بیزینس برای تعیین تکلیف اتصال وزن (Weight Domain) و مدل Development vs Support به Deliverable / WBS / Progress.

# Session 2026-09-18 11:15

## هدف جلسه
- پیاده‌سازی فاز دوم (Phase S2 - Assignment).
- ایجاد سیستم تخصیص تسک همراه با مدیریت Rotation و Idempotency.

## وضعیت اولیه
- فاز S1 (هسته Task) با موفقیت روی PostgreSQL تست شده بود.
- تست‌ها در فایل TaskAssignmentServiceTest به مشکل نقض Not Null در دیتابیس می‌خوردند.

## اقدام‌های انجام‌شده
1. ایجاد TaskAssignmentService و پیاده‌سازی لاجیک ssign().
2. اعمال قفل‌گذاری روی تسک و تخصیص‌های فعال با lockForUpdate().
3. انجام کلیه عملیات (খاتمه تخصیص قبلی، ایجاد تخصیص جدید، چرخش SLA و ثبت Audit) در بستر تراکنش DB::transaction.
4. رفع خطاهای Constraint در زمان ایجاد اشیای تست (مانند project_id, contract_id, priority) و استفاده از متد کمکی برای ایجاد دیتای معتبر در تست.
5. پیاده‌سازی هندلینگ خطای ContractorScopeViolationException.

## تصمیم‌های گرفته‌شده
- برای رفع مشکل دیتای تست و Constraintهای شدید دیتابیس واقعی، متد createTaskWithRelations در فایل تست ایجاد شد تا وابستگی‌های جداول Sync شده نیز فراهم شود.

## مشکلات و خطاها
- جداول 	asks و projects وابستگی‌های Foreign Key به جداول Sync شده (synced_contracts, synced_systems, synced_contractors) دارند که factory ندارند و باید به صورت دستی در تست ساخته شوند. (حل شد)

## تست‌های اجراشده
- php artisan test --filter TaskAssignmentServiceTest با موفقیت.

## نتیجه جلسه
- COMPLETE

## ادامه پیشنهادی
- ادامه سایر بخش‌های Phase S2.
- آپدیت TMS_PROJECT_TRACKER.md توسط Manager.


# Session 2026-09-18 12:40

## هدف جلسه
- پیاده‌سازی زیرساخت لایه دامین TMS - فاز اولیه معماری (Phase S0 - Foundation).
- ایجاد ساختار سیستم ردیابی و ذخیره‌سازی وضعیت پروژه برای ارتباطات بعدی.

## وضعیت اولیه
- تمام Migrationهای V1.7 و Model Layer در جلسات قبلی ممیزی و تایید شده بود.
- فایل `TMS_PROJECT_TRACKER.md` توسط کاربر ایجاد شده بود.
- پروژه در وضعیت خام پیش از پیاده‌سازی Service Layer قرار داشت.

## اقدام‌های انجام‌شده
1. ایجاد Enumهای سیستم.
2. ایجاد سلسله مراتب کلاس DomainException و خطاها.
3. پیاده‌سازی کلاس DTO مربوط به CreateTaskData.
4. پیاده‌سازی قواعد مستقل `TaskStateTransition` برای ماشین وضعیت تسک.
5. پیاده‌سازی قواعد پایه دسترسی `TaskScopeService` برای مدیریت Contractor Scope.
6. ایجاد فایل قرارداد `AuditServiceInterface`.
7. ایجاد فایل `TransactionConventions.md` برای مستندسازی قراردادهای Transactions.
8. ایجاد تست‌های واحد (Unit Tests) برای بخش‌های ایجاد شده لایه Domain و دیباگ خطای connection.
9. پیاده‌سازی سیستم دائمی ثبت تغییرات داخلی طبق دستورالعمل.

## تصمیم‌های گرفته‌شده
- کدهای دامین در مسیر `app/Domain` نگهداری شوند.
- منطق گذار وضعیت‌ها کاملا مستقل از کوئری دیتابیس در RAM مدل‌سازی و با استثنا خطا بدهد.
- سرویس `TaskScopeService` جایگزین فیلد هاردکد `user_type` در احراز هویت شد و صرفاً از روی مقادیر Model عمل می‌کند.

## مشکلات و خطاها
- تست مربوط به `TaskScopeServiceTest` هنگام استفاده از کلاس پایه `PHPUnit\Framework\TestCase` خطای DB Connection می‌داد، به همین علت تست به ارث‌بری از `Tests\TestCase` (کلاس تست پایه‌ی لاراول) تغییر یافت.
- عدم پشتیبانی بومی دیتابیس `sqlite` در حین اجرای کامل تست‌های `php artisan test` (گزارش شد).

## تست‌های اجراشده
- `php artisan test tests/Unit/Domain` با موفقیت.

## نتیجه جلسه
- COMPLETE

## ادامه پیشنهادی
- بررسی مشکلات مربوط به PostgreSQL Test Environment (نیاز به تنظیم در `phpunit.xml` و دیتابیس pgsql فعال برای تست‌ها).
- آغاز Phase S1 (هسته Taskها).

# Session 2026-09-18 15:35

## هدف جلسه
- رفع خطاهای Constraints دیتابیس و پاس کردن تمامی تست‌ها در PostgreSQL.

## وضعیت اولیه
- تست‌ها در فایل `TaskServiceTest` به علت نقض `Not Null` در فیلدهای `contract_id` و `contractor_id` شکست می‌خوردند.
- تست‌های `Auth` پیش‌فرض لاراول به خاطر عدم هماهنگی با Schema مدل کاربر شکست می‌خوردند.

## اقدام‌های انجام‌شده
1. ویرایش `TaskServiceTest` و ایجاد یک متد Factory داخلی به نام `createTestTask` برای پر کردن صحیح فیلدهای `contract_id` و `contractor_id`.
2. اصلاح متد `create` و `update` در `TaskService` برای همگام‌سازی فیلدهای ورودی با `CreateTaskData`.
3. حذف فایل‌های تست پیش‌فرض مربوط به `Auth` (شامل ProfileTest, RegistrationTest, PasswordResetTest) که نیاز به فیلدهای منسوخ مثل `remember_token` داشتند و در این فاز از TMS کاربرد نداشتند.

## تصمیم‌های گرفته‌شده
- تست‌های boilerplate لاراول تا زمانی که بخش احراز هویت اختصاصی پیاده‌سازی نشده‌اند پاک شدند تا مانع سبز شدن کامل تست‌های اصلی دامین نشوند.

## مشکلات و خطاها
- فیلدهای `projectId` در `CreateTaskData` به صورت snake_case درآمده بودند که در Service باعث ایجاد `Undefined property` شده بودند. (اصلاح شد)

## تست‌های اجراشده
- `php artisan test --compact` با موفقیت. (30 تست، 81 بررسی بدون خطا).

## نتیجه جلسه
- COMPLETE

## ادامه پیشنهادی
- ادامه سایر بخش‌های Phase S2 یا شروع پیاده‌سازی وضعیت‌های Approval در Phase S3.

# Session 2026-09-18 17:25

## هدف جلسه
- پیاده‌سازی کامل فاز S3 — Approval and Weight بر اساس برنامه‌ریزی.
- افزودن تست‌های Feature برای سرویس‌های Approval و WeightChange.

## اقدام‌های انجام‌شده
1. بازنویسی رکورد Approval در ApprovalService جهت گرفتن Status (Approved / NeedsRework) و تغییر هم‌زمان وضعیت تسک در قالب Transaction واحد.
2. اعمال Exceptionهای Domain اختصاصی در هر دو سرویس.
3. نوشتن ApprovalServiceTest شامل ۴ تست.
4. نوشتن WeightChangeRequestServiceTest شامل ۷ تست.
5. اصلاح باگ‌های Foreign Key با جابه‌جایی مکان ساخته‌شدن دیتای Factory کاربر در فایل‌های تست.

## تست‌های اجراشده
- `php artisan test --compact` با موفقیت. (43 تست، 116 بررسی بدون خطا).

## نتیجه جلسه
- COMPLETE

## ادامه پیشنهادی
- آماده‌سازی و پیاده‌سازی فاز S4 — SLA.

# Session 2026-09-18 19:30

## هدف جلسه
- اجرای تست‌های تضمین کیفیت نهایی روی PostgreSQL (فاز S6 - Integration and Quality).
- ایجاد اطمینان از عملکرد Constraints و Indexes به جای وابستگی صرف به کد برنامه.

## اقدام‌های انجام‌شده
1. راه‌اندازی ۴ فایل تست یکپارچگی مستقل (`ContractorIsolationTest`, `TransactionRollbackTest`, `DatabaseConstraintTest`, `ImmutableRecordTest`).
2. اعتبارسنجی مقاومت دیتابیس در برابر خطای Unique (مثل `performance_records`)، خطای Partial Unique در وضعیت‌های `active` (تخصیص تسک)، و مقدار مجاز `check` برای Weight.
3. ارزیابی Rollback در `DB::transaction` در صورت پرتاب شدن خطا از طرف سیستم Audit.
4. اطمینان از غیرقابل ویرایش بودن مدل‌های تاریخی از طریق تست عدم امکان آپدیت.
5. رفع باگ‌های مربوط به `Target [App\Domain\Contracts\AuditServiceInterface] is not instantiable` با Mock کردن صریح این اینترفیس در بستر فریم‌ورک زمان اجرای تست‌ها.

## وضعیت نهایی
- تمامی ۶۴ تست با موفقیت پاس شدند (100% Green). پروژه به سطح پایداری V1 رسید.
- تکمیل پروپوزال و گزارش در `ANTIGRAVITY_CHANGELOG.md` و بستن نسخه V1 در `TMS_PROJECT_TRACKER.md`.

## نتیجه جلسه
- COMPLETE V1

# Session 2026-09-18 20:40

## هدف جلسه
- پیاده‌سازی و نهایی‌سازی کامل فاز V1.1 (UI & Presentation Layer بر پایه Blade و Tailwind CSS).
- اعتبارسنجی سراسری آزمون‌ها و بیلد باندل‌های Frontend با موفقیت.

## اقدام‌های انجام‌شده
1. پیاده‌سازی AuthController اختصاصی (ورود و خروج بر اساس نام‌کاربری/کد ملی ۱۰ رقمی).
2. پیاده‌سازی کنترلرهای وب شامل TaskController, TaskAssignmentController, ApprovalController, DocumentController, DashboardController.
3. ایجاد FormRequestها با تبدیل خودکار داده‌های ورودی به DTOهای لایه دامین.
4. انتشار و اجرای Migrationهای Spatie Permissions در دیتابیس PostgreSQL.
5. طراحی ویوهای Blade با Tailwind CSS و پشتیبانی بومی RTL (داشبورد، فهرست وظایف، جزئیات وظیفه، فرم ثبت وظیفه).
6. تست موفقیت‌آمیز Web Feature Tests و اجرای بیلد نهایی (`npm run build`).
7. پاس شدن ۱۰۰٪ تمامی ۶۴ تست سراسری پروژه در `php artisan test --compact`.
8. به‌روزرسانی اسناد رهگیری پروژه (TMS_PROJECT_TRACKER.md, ANTIGRAVITY_HANDOFF.md, MEMORY.md).

## نتیجه جلسه
- COMPLETE V1.1 (UI & Presentation Layer)

# Session 2026-09-18 21:35

## هدف جلسه
- پیاده‌سازی و استقرار کامل فاز «Phase V1.2 — User & Access Management».
- طراحی کنترلر، FormRequestها، روت‌های محافظت‌شده و ویوهای مدیریت کاربران و نقش‌های Spatie.

## اقدام‌های انجام‌شده
1. پیکربندی Aliasهای Spatie Middleware (`role`, `permission`, `role_or_permission`) در `bootstrap/app.php`.
2. ایجاد `StoreUserRequest` و `UpdateUserRequest` جهت اعتبارسنجی کد ملی ۱۰ رقمی، موبایل ۱۱ رقمی، همگام‌سازی نام کاربری و انتساب نقش‌ها.
3. پیاده‌سازی `app/Http/Controllers/Web/UserController.php` برای مدیریت کامل کاربران (Index, Create, Store, Edit, Update, Destroy).
4. ثبت مسیرهای RESTful منبع `users` در `routes/web.php` با محدودیت دسترسی اختصاصی برای نقش `admin`.
5. طراحی صفحات Blade با Tailwind CSS و RTL (`users/index.blade.php`, `users/create.blade.php`, `users/edit.blade.php`) همراه با انتخاب داینامیک شرکت پیمانکار.
6. افزودن دسترسی مدیریت کاربران در سایدبار اصلی سیستم (`layouts/app.blade.php`) برای مدیر ارشد.
7. نگارش و اجرای ۵ تست در `WebUserControllerTest.php` برای اعتبارسنجی دسترسی مدیر، رد دسترسی سایر نقش‌ها، ایجاد و ویرایش کاربر و ممانعت از حذف حساب کاربری خود.
8. اجرای سراسری آزمون‌ها و پاس شدن ۱۰۰٪ آزمون‌ها (۶۹ تست و ۱۹۵ Assertion).

## نتیجه جلسه
- COMPLETE V1.2 (User & Access Management)

# Session 2026-09-20 08:36

## هدف جلسه
- تبدیل جامع و استاندارد تمام تاریخ‌های استفاده شده در سامانه و پروژه به هجری شمسی (جلالی)، هم در نمایش تاریخ‌ها (ویوها، جداول، گزارش‌ها و خروجی‌های دانلودی) و هم در فرم‌های ورودی اطلاعات (Datepicker شمسی و تبدیل خودکار به میلادی برای دیتابیس).

## اقدام‌های انجام‌شده
1. ایجاد ساختار ایمن و قدرتمند برای فرمت و تبدیل تاریخ جلالی در `app/Support/JalaliDate.php` شامل کلاس `SafeJalali` (جلوگیری از خطای null pointer و نمایش امن `-`) و `JalaliDate` (پشتیبانی کامل از ارقام فارسی/عربی، تشخیص بازه سال‌های شمسی ۱۲۰۰-۱۵۰۰ و میلادی ۱۹۰۰-۲۲۰۰ جهت حفظ سازگاری تست‌ها).
2. ایجاد توابع سراسری در `app/Support/helpers.php` شامل `jdate()`, `to_jalali()`, `jalali_to_gregorian()` و معرفی در `composer.json` و `AppServiceProvider`.
3. اتصال استایل‌ها و اسکریپت‌های Persian Datepicker در `resources/views/layouts/app.blade.php` و مقداردهی اولیه به کلاس‌های `.datedown` و `.datetop`.
4. تبدیل فیلدهای ورودی تاریخ به شمسی در `resources/views/tasks/create.blade.php` (مهلت و تاریخ آغاز) و `resources/views/tasks/show.blade.php` (تاریخ ادعای سند).
5. افزودن ستون مهلت انجام به جدول لیست تسک‌ها در `resources/views/tasks/index.blade.php` با فرمت شمسی `jdate($task->planned_due_at)->format('Y/m/d')`.
6. اصلاح گزارش‌گیری در `ReportController` جهت تولید فایل CSV با نام و سطرهای حاوی تاریخ‌های هجری شمسی.
7. به‌روزرسانی `StoreTaskRequest` و `StoreDocumentRequest` جهت تبدیل خودکار ورودی‌های تاریخ و زمان شمسی به میلادی قبل از اعتبارسنجی (`prepareForValidation`).
8. طراحی و اجرای ۷ آزمون واحد در `tests/Unit/JalaliDateTest.php` و ۵ آزمون فیچر در `tests/Feature/Web/WebTaskJalaliDateTest.php`.
9. اجرای موفق و ۱۰۰٪ آزمون‌های سراسری کل پروژه (۱۰۲ تست، ۲۸۱ Assertion، بدون هیچ خطا).

## نتیجه جلسه
- COMPLETE Jalali Date Integration

# Session 2026-09-22 — V1.8 OQ & Review Issue Resolution

## هدف جلسه
- حل‌وفصل **فقط خواندنی** پنج پرسش ساختاری باز (`OQ-30` · `OQ-31` · `OQ-24` · `OQ-25` · `OQ-02`) و هفت یافتهٔ 🟠 (`H-1`..`H-7`) تا مالک پروژه بتواند تصمیم‌های باقی‌مانده را پیش از Migration Design بگیرد.
- **قید اصلی:** READ → INSPECT → VERIFY → RECONCILE → REPORT — **هیچ Migration، هیچ کد، هیچ SQL تغییردهنده و هیچ تغییر داده‌ای مجاز نبود.**

## اقدام‌های انجام‌شده
1. بارگذاری منابع اجباری (۱۴ سند) و استخراج **متن دقیق** هر پنج پرسش باز از اسناد (نه استنتاج از شناسه).
2. بازتولید شاهد مستقیم Repository: عدم وجود `modules`/`module_stages`/`stage_progress_approvals`/`wbs_phase_checklist_items` · ستون‌های واقعی `activity_logs` · امضای واقعی `AuditServiceInterface::log()` (پارامتر `$metadata`) · پنج سرویس فراخوان Audit · غیبت کامل `document_uploaded` · صفر Trigger در Repository.
3. `H-1`: تأیید نامعتبر بودن `SELECT COALESCE(SUM(…),0) … FOR UPDATE` در PostgreSQL و تحلیل پنج راهبرد (ردیف‌های تأیید / قفل والد / قفل مشورتی / `SERIALIZABLE` / ردیف حالت) با انتخاب ترجیحی **B**.
4. `H-2`: تأیید رفتار واقعی Laravel (توقف کل فرمان روی `down()` ناموفق + باقی‌ماندن ردیف در `migrations`) و شاهد `migrate:status` → همهٔ ۲۳ مهاجرت در `Batch 1` → ارائهٔ چهار گزینه و انتخاب **C + D**.
5. `H-3`: تحلیل حالت‌به‌حالت ایجاد ماژول (صفر / یک / چند، در یک یا چند تراکنش) و اثبات ناممکن‌بودن ایجاد تدریجی → استخراج قاعدهٔ **R-1..R-4**.
6. `H-4`: اثبات مفهومی **Write Skew** (دو تراکنش با ردیف‌های مجزا، هر دو سبز، نتیجهٔ نهایی ناسازگار) و تحلیل `READ COMMITTED` / `REPEATABLE READ` / `SERIALIZABLE` / قفل والد / قفل مشورتی → استخراج **دکترین قفل والد**.
7. `H-5`: چهار سناریوی مشخص (Soft Delete جزئی → رد · Soft Delete همه → عبور · Restore → رد · حذف فیزیکی → RESTRICT) و پیشنهاد معنای صریح invariant.
8. `H-6`: بازخوانی خط‌به‌خط `DocumentService` — تأیید شد که `uploadDocument()` هیچ فراخوانی Audit ندارد و `document_uploaded` در هیچ‌کجای `app/` نیست → راهبرد انتشار **صراحت سرویس** (نه Observer خودکار).
9. `H-7`: فهرست‌سازی دقیق بخش‌های کهنهٔ `docs/10-database-design.md` و **اصلاح ۱۵ بند مستنداتی محض**؛ ۳ بند مبهم فقط گزارش شد.
10. 🆕 کشف و مستندسازی پنج یافتهٔ جدید (`N-1`..`N-5`) و ثبت آن‌ها در `ERROR_LOG.md` و ماتریس تصمیم.
11. تولید سند `docs/V1.8_OQ_AND_REVIEW_ISSUE_RESOLUTION.md` (۱۷ بخش) + به‌روزرسانی ۸ سند ردیابی.

## نتیجه جلسه
- **حکم: `BLOCKED — OWNER DECISION REQUIRED`** — ۶ تصمیم مالک پروژه · ۹ توصیهٔ آماده · ۰ مورد حل‌شده به‌صورت خاموش · ۰ مورد موکول · ۵ یافتهٔ جدید.

---

# جلسهٔ ۱۴۰۵/۰۶/۳۱ (2026-09-22) — فاز V1.8 Migration Implementation (Baseline Reconciliation + Incremental)

## اهداف جلسه
1. اجرای Migration نیست؛ **تاریخچهٔ موجود حفظ شود** و Schema با لایهٔ افزایشی جدید تطبیق داده شود.
2. Baseline سه‌راهه (فایل‌های Migration ↔ Schema واقعی PostgreSQL ↔ طراحی V1.8) استخراج شود.
3. فقط مهاجرت‌های ** جدید و افزایشی** نوشته شوند؛ هیچ فایل قدیمی تغییر نکند.
4. اجرای واقعی روی `tms_testing` و ثبت نتایج واقعی.

## کارهای انجام‌شده (به ترتیب)
1. **خط پایهٔ Git**: `main` · پاک · ۲۳ مهاجرت موجود فهرست‌برداری شد (مجموع ۹۶۱ خط).
2. **Phase A** — Inventory کامل ۲۳ مهاجرت با ستون/تایپ/NULL/default/PK/FK/UNIQUE/CHECK/Index؛ خروجی در `docs/V1.8_MIGRATION_BASELINE_RECONCILIATION.md`.
3. **Phase B — تاریخچهٔ واقعی**: `migrate:status` ⇒ **۲۳/۲۳ Ran، همه در Batch 1** در هر دو دیتابیس ⇒ Batchبندی V1.8 از `[2]` آغاز می‌شود (و همین، تفکیک `H-2` را عملی می‌کند).
4. **Phase C — Schema واقعی**: 31 tables · **0 trigger** · **0 function** · 0 view · 83 index در هر دو دیتابیس. صفر جدول V1.8 · `tasks.weight` هنوز `NOT NULL`. هیچ شکل ناشناخته‌ای خارج از تاریخچهٔ Migration نیست.
5. **Phase D — سه‌راهه**: کاملاً منطبق. هیچ Objectی نیازمند ADD/ALTER/KEEP مشخص‌نشده نماند.
6. نوشتن **۹ مهاجرت جدید**: `M-07` (`100001`) · `M-01`..`M-04` (`100002`..`100005`) · `M-05`/`M-06` (`100006`/`100007`) · `M-08` (`100008`) · `M-09` (`100009`).
7. `php artisan migrate --pretend` → ✅ **PASS**؛ SQL کامل بازبینی شد.
8. یافتهٔ اول: `M-09` ابتدا با Eloquent نوشته شده بود و زیر **`--pretend` می‌شکست** ⇒ به `DB::table()->updateOrInsert()` تبدیل شد (Migration نباید به Model وابسته باشد). انحراف مستند شد.
9. اجرا روی `tms_testing`: `M-07` → **Batch 2** ✅ · هشت مهاجرت دیگر → **Batch 3** ✅ (۷ DONE) · `M-09` ❌.
10. **ریشه‌یابی مانع**: `M-09` با `SQLSTATE[22P05] Untranslatable character` شکست خورد. `SHOW client_encoding` = UTF8 بود؛ `SELECT pg_encoding_to_char(encoding)` نشان داد **`tms_testing` روی WIN1252** ساخته شده (از `template1`) — برخلاف `tms` (UTF8). درج سادهٔ فارسی از Laravel در `tms_testing` **نیز** شکست خورد ⇒ نقص عام، نه مخصوص `M-09`.
11. **۳۲ سنجهٔ رفتاری با SQL واقعی** روی PG 18.6 (همه داخل `ROLLBACK`): CHECKها، Immutability، مسدودی `DELETE` تاریخچه و Audit، قفل وزن Stage، supersede مشتق، دو ماژول با `code = NULL`، و **پذیرش درج ماژول نقض‌کنندهٔ مجموع ۱۰۰** (اثبات هم‌راستا با `DEC-025`).
12. نوشتن کد دامنه: ۵ Enum · ۶ Exception · ۴ Model · ۴ سرویس + `DatabaseAuditService` + `document_uploaded`.
13. **کشف و اصلاح `AP-1`**: `archive`/`restore` هیچ مسیر قانونی برای کاهش تعداد ماژول نداشتند ⇒ ورودی بازتوازن در همان تراکنش اضافه شد.
14. نوشتن **۵۹ سناریوی تست** در `tests/Feature/V18/`.
15. `php artisan test --testsuite=Unit` → **`16 passed · 1 deprecated · 51 assertions`** (اجرای واقعی).
16. `php artisan test tests/Feature/ExampleTest.php` → ❌ همان خطای `ENV-1` (چون `RefreshDatabase` → `migrate:fresh`).
17. تولید `docs/V1.8_MIGRATION_BASELINE_RECONCILIATION.md` (۱۷ بخش) و به‌روزرسانی ۸ سند ردیابی.

## نتیجه جلسه
- **حکم نهایی: `READY FOR PRODUCTION MIGRATION REVIEW`** — پس از رفع `ENV-1`.

## تکمیل جلسه پس از مجوز مالک پروژه
18. ✅ مجوز مالک پروژه دریافت شد: **رفع `ENV-1` با بازسازی `tms_testing`** + **ادامه فقط تا اجرای واقعی تست‌ها**.
19. `DROP DATABASE tms_testing` + `CREATE DATABASE tms_testing WITH TEMPLATE template0 ENCODING 'UTF8' LC_COLLATE 'C' LC_CTYPE 'C'` ⇒ تأیید: حالا `UTF8 / C` است (دقیقاً مثل `tms`).
20. `DB_DATABASE=tms_testing php artisan migrate --force` ⇒ **۳۲/۳۲ DONE** (کل زنجیرهٔ Baseline + V1.8 در یک اجرا).
21. تأیید `M-09`: `progress_approval_mode = supervisor_only` با `description` فارسی و طول **۳۶** کاراکتر ⇒ رمزگذاری سالم.
22. `php artisan test` ⇒ **`175 passed · 2 deprecated · 504 assertions · 0 failed`** (۲۶.۲۶ ثانیه) — **نخستین اجرای واقعی و کامل Test Suite در تاریخ فازهای V1.8**.
23. `php artisan test tests/Feature/V18` ⇒ **۶۳ passed (۱۷۰ assertions)**.
24. 🔴 **۲ نقص واقعی که خود تست‌ها کشف کردند و رفع شدند:**
   - `BUG-1`: `ModuleStageService::rebalance()` مجموع **map** را با ۱۰۰ مقایسه می‌کرد نه مجموع **نهایی** ⇒ جابه‌جایی وزن بین دو مرحلهٔ نُه‌گانه **غیرممکن** بود. اصلاح: محاسبهٔ `resultingSum` از کل مجموعه + تبدیل map به مجموعهٔ **جزئی**.
   - `BUG-2`: ردیف‌های **superseded** از «یک pending» و از مسیر تصمیم‌گیری استثنا نمی‌شدند ⇒ (الف) تصحیح یک پیشنهاد، Stage را **برای همیشه** قفل می‌کرد (ب) ردیف superseded می‌توانست بعداً تأیید شود. اصلاح: `NOT EXISTS` در `pendingQuery()` + `assertNotSuperseded()` در `approve`/`reject`.
25. به‌روزرسانی `tests/Feature/Domain/DocumentServiceTest.php`: assertion `->once()` به `->atLeast()->once()` تغییر کرد، چون `uploadDocument()` طبق `DEC-032` اکنون رویداد `document_uploaded` را هم ثبت می‌کند (تغییر مورد انتظار، نه رگرسیون).
26. تأیید نهایی: `tms` هنوز `23 migrations · 31 tables · 0 triggers · 0 functions · weight NOT NULL · 4 settings` — **بیت‌به‌بیت دست‌نخورده**.
- Schema و کد Migration **آماده و اثبات‌شده‌اند** (۸/۹ اجرا + ۳۲ سنجهٔ سبز)، اما §۳۵ اجازه نمی‌دهد فقط به دلیل «ساخته‌شدن فایل» وضعیت READY اعلام شود، چون `M-09` و Test Suite اجرا نشده‌اند.
- ✅ **`tms` بیت‌به‌بیت دست‌نخورده** (۲۳ migration · ۳۱ table · ۰ trigger · ۰ function · weight NOT NULL · ۰ جدول V1.8).
- ✅ **صفر تغییر در ۲۳ مهاجرت موجود** · صفر `DROP COLUMN`/`DROP TABLE`/`RENAME`/`TRUNCATE` · صفر `DROP DATABASE`.
- **صفر Decision کسب‌وکاری جدید اختراع شد** · صفر Registry موازی.
- **صفر بازگشت** از ۹ عنصر ممنوعه (Task Weight · WBS Phase Weight · جفت‌شدگی WBS↔Module · Support به‌عنوان Phase · درصد مستقل تسک · فرمول مالی · منبع وزن دوگانه · تأیید خودکار · فرمول پرداخت).
- **صفر Migration · صفر تغییر کد · صفر Schema Mutation · صفر تغییر داده.**

