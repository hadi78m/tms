# Antigravity Decisions Log

## DEC-001 — معماری Clean و جداسازی لایه Domain

### تاریخ
2026-09-18

### تصمیم
کلیه قوانین کسب و کار مانند ماشین وضعیت، DTOها، استثنائات تجاری و Enumها، باید در فضای نام اختصاصی `App\Domain` به جای مسیر پیش‌فرض لاراول قرار بگیرند.

### دلیل
نگه داشتن لایه‌ی تجاری نرم‌افزار به صورت Data-Agnostic و مستقل از فریم‌ورک و کنترلرها تا حد امکان.

### گزینه‌های بررسی‌شده
- قرار دادن این موارد در کنار پوشه Modelها و Exceptionهای معمول لاراول.
- ایجاد پوشه جداگانه `app/Domain`.

### پیامدها
- کلاس‌های پایه و لاجیکِ تغییر وضعیت‌ها به صورت کاملاً مستقل قابل تست‌گیری شدند و در هر جای پروژه (کنسول، وب، جاب‌ها) یکپارچه عمل می‌کنند.

### وضعیت
- ACTIVE

---

## DEC-002 — مدیریت مستقل وضعیت‌ها از دیتابیس (TaskStateTransition)

### تاریخ
2026-09-18

### تصمیم
گذار میان وضعیت‌های تسک در کلاسی مجزا (`TaskStateTransition`) صرفا در فضای RAM به کمک Enumها مدیریت می‌شود و هر گذار غیرمجاز بلافاصله با پرتاب یک `InvalidTaskTransitionException` متوقف می‌گردد.

### دلیل
این رویکرد جلوی پراکندگی کدهای بررسی وضعیت (If/Else) در قسمت‌های مختلف کنترلر و سرویس را می‌گیرد.

### پیامدها
- اگر قرار به تغییر Flow وضعیت‌ها باشد، تنها یک نقطه نیاز به ویرایش دارد.

### وضعیت
- ACTIVE

---

## DEC-003 — مالکیت Weight منتقل می‌شود به Module / Deliverable و Module Stage

### تاریخ
2026-09-21

### تصمیم
Weight دیگر متعلق به Task نیست. در مدل V1.8:

- `Module / Deliverable` مالک سهم توسعهٔ ماژول از پروژه است.
- `Module Stage` (۹ مرحلهٔ استاندارد) مالک سهم مرحله از ماژول است
  (Analysis 15٪، Design 5٪، Coding 35٪، Functional Test 3٪، PenTest 5٪،
  Training 7٪، Pilot 10٪، Production 5٪، Support 15٪ = 100٪).
- Task هیچ وزن و هیچ درصد پیشرفت مستقلی ندارد. Task فقط واحد اجرای کار و Evidence است.

### دلیل
- Task ابزار اجرای کار است، نه حامل سهم توسعه.
- تأیید یک Task نباید به‌تنهایی درصدی از پیشرفت ایجاد کند.
- Progress معتبر باید از تأییدهای مستقل Stage/Module حاصل شود.

### گزینه‌های بررسی‌شده
- نگه‌داشتن وزن روی Task و بازتعریف معنای آن (رد شد — با ذات Task ناسازگار است).
- وزن روی WBS Phase (رد شد — DEC-004).
- ترکیب Module + Module Stage (انتخاب شد).

### پیامدها
- `tasks.weight` در تناقض مستقیم قرار می‌گیرد و باید در دو مرحله Deprecate شود
  (ابتدا `DROP NOT NULL`، سپس `DROP COLUMN` در فاز بعد).
- `wbs_phases.weight` دادهٔ مرده است و باید به `module_stages` منتقل شود.
- گزارش‌هایی که از جمع خام `tasks.weight` ساخته می‌شوند باید بازنویسی شوند.
- ۱۳ فایل تست وابسته به `tasks.weight` نیازمند به‌روزرسانی خواهند بود.

### وضعیت
- ACTIVE (DESIGN FREEZE — پیاده‌سازی در انتظار تأیید مالک پروژه)

---

## DEC-004 — تفکیک WBS Phase از Module Stage

### تاریخ
2026-09-21

### تصمیم
WBS Phase و Module Stage دو موجودیت مستقل با دو نقش متفاوت‌اند و نباید در یک جدول
ادغام شوند:

- **WBS Phase** = محدوده، تاریخ شروع/پایان، مدت تخمینی، خروجی‌های مورد انتظار
  (Checklist)، وضعیت تکمیل، نظر و تأیید نهایی ناظر. **مالک وزن نیست.**
- **Module Stage** = سهم استاندارد وزنی مرحله از ماژول. تاریخ و Checklist ندارد.

### دلیل
- پرامپت V1.8 صریحاً می‌گوید «WBS Phase صرفاً یک درصد وزنی از Project نیست».
- مثال بخش ۵ نشان می‌دهد یک WBS Phase (با عنوان «تحلیل، طراحی، معماری») می‌تواند
  چند Stage را پوشش دهد.
- جدول `wbs_phases` فعلی هم‌زمان تلاش می‌کند هر دو نقش را ایفا کند و در نتیجه
  هیچ‌کدام را کامل ایفا نمی‌کند.

### گزینه‌های بررسی‌شده
- ادغام هر دو در `wbs_phases` (رد شد).
- Support به‌عنوان `wbs_phases.kind = 'support'` (رد شد — DEC-005).

### پیامدها
- دو موجودیت جدید لازم است: `module_stages` و `wbs_phase_outputs`.
- رابطهٔ دقیق WBS Phase ↔ Module مصوب نشده است (`OQ-03`) و ساختار FK را مسدود می‌کند.
- `wbs_phases.expected_output` (یک فیلد TEXT) باید به `wbs_phase_outputs` منتقل شود.

### وضعیت
- ACTIVE (DESIGN FREEZE — `OQ-03` باز)

---

## DEC-005 — Support یک Stage مستقل است، نه یک WBS Phase

### تاریخ
2026-09-21

### تصمیم
Support = ۱۵٪ از سهم Module، و یکی از ۹ Stage استاندارد است. Support **کاملاً مستقل
از WBS Phase** است. Support می‌تواند Task داشته باشد یا نداشته باشد، و Taskهای آن
در طول دورهٔ قرارداد قابل تکرار هستند.

### دلیل
- پرامپت §۱۰ صریحاً می‌گوید Support نباید به‌صورت یک WBS Phase عادی مدل شود.
- درصد Support از همان جنس ۱۰۰٪ ماژول است، پس باید در همان Ledger باشد.
- تأیید درصدی Support عیناً همان مکانیزم `stage_progress_approvals` را می‌خواهد.

### گزینه‌های بررسی‌شده
- `wbs_phases.kind = 'support'` (رد شد — نقض صریح §۱۰).
- جدول مستقل `support_allocations` (رد شد — دو Aggregate موازی برای یک مفهوم).
- `module_stages` با `stage_code = 'support'` (انتخاب شد).
- تسک با `wbs_phase_id = null` به‌عنوان تنها بازنمایی Support (وضع موجود — ناکافی،
  چون ۱۵٪ هیچ خانه‌ای برای ذخیره ندارد).

### پیامدها
- طراحی مرحلهٔ اول ممیزی V1.8 (Support به‌عنوان `wbs_phase`) باطل شد.
- زیرساخت SLA موجود (`SlaService`, `sla_records`, `tasks.priority`) بدون هیچ تغییر
  کل نیازمندی SLA پشتیبانی را پوشش می‌دهد.
- تنها شکاف باقی‌مانده، اتصال Support Task به Stage مربوطه است
  (`tasks.module_stage_id`).

### وضعیت
- ACTIVE (DESIGN FREEZE)

---

## DEC-006 — جدول مستقل برای تأیید درصدی Stage

### تاریخ
2026-09-21

### تصمیم
جدول `stage_progress_approvals` به‌صورت مستقل ساخته می‌شود. جدول `approvals` موجود
**توسعه داده نمی‌شود** و برای Quality Gate چرخهٔ Task دست‌نخورده باقی می‌ماند.

### دلیل
چهار مانع فنی در `approvals` موجود:

1. `task_id` از نوع `BIGINT NOT NULL FK` است — nullable کردنش هر کوئری و تست موجود
   را تضعیف می‌کند.
2. صفر ستون عددی دارد — مفهوم «۵٪ از ۱۵٪» بی‌بازنمایی است.
3. مدل Immutable است (`static::updating(fn() => false)`) — گذار
   `pending → approved` نیازمند Update است.
4. معنایش Quality Gate کیفی چرخهٔ Task است، نه Ledger کمّی پیشرفت.

### پیامدها
- دو مفهوم Approval در سیستم وجود خواهد داشت و **باید در UI با برچسب‌های متفاوت**
  تفکیک شوند («تأیید فنی/نهایی تسک» ≠ «تأیید پیشرفت مرحله»).
- الگوی Row Lock موجود در `WeightChangeRequestService` (خطوط ۵۷ و ۶۴) برای کنترل
  ظرفیت تکرار می‌شود — نیازی به الگوی جدید نیست.
- تاریخچهٔ تغییرات روی `activity_logs` موجود سوار می‌شود (بدون تغییر Schema) —
  **مشروط به رفع DEC-007**.

### وضعیت
- ACTIVE (DESIGN FREEZE)

---

## DEC-007 — رفع مانع Audit: جانشینی NullAuditService

### تاریخ
2026-09-21

### تصمیم
`AuditServiceInterface` در `AppServiceProvider` به `NullAuditService` بایند شده است که
متد `log()` آن فقط `return new ActivityLog;` می‌کند **بدون `save()`**. یعنی
`activity_logs` هرگز توسط برنامه پر نمی‌شود. این باید به‌عنوان **گام صفر** هر
پیاده‌سازی V1.8 رفع شود.

### دلیل
الزامات قطعی V1.8 (BD-17 و BD-21) می‌گویند تمام تغییرات درصد باید Audit داشته باشند
و مقدار قبلی نباید از بین برود. بدون نویسندهٔ فعال، این الزامات غیرقابل تحقق‌اند.

### گزینه‌های بررسی‌شده
- نگه‌داشتن `NullAuditService` (رد شد — تاریخچهٔ V1.8 را خالی می‌گذارد).
- ساخت `DatabaseAuditService` و بایند آن (انتخاب شد).

### پیامدها
- جدول `activity_logs` **بدون هیچ تغییر ساختاری** تمام نیازمندی‌های تاریخچه را پوشش
  می‌دهد (`old_values`/`new_values` JSONB، `user_id`، `reason`، Immutable).
- مشکل در **نویسنده** است، نه در **مخزن**.
- تست‌های موجود Audit را روی Mock تأیید می‌کنند و این شکاف را نشان نمی‌دهند.

### وضعیت
- ACTIVE (DESIGN FREEZE — گام صفر پیاده‌سازی)

---

## DEC-008 — تفکیک Scope فاز V1.8 (Design Freeze قبل از Implementation)

### تاریخ
2026-09-21

### تصمیم
فاز V1.8 در سه زیرفاز جداگانه اجرا می‌شود:

1. **V1.8-Design (این فاز)** — ممیزی، تطبیق معماری، به‌روزرسانی مستندات.
   🚫 صفر تغییر کد.
2. **V1.8a — Schema + Domain + Test** (پس از تأیید مالک پروژه)
3. **V1.8b — Web UI** (ماژول، Stage، Checklist، تأیید پیشرفت)

### دلیل
- WBS امروز **صفر CRUD/Route/View** دارد؛ یکجا انجام دادن دامنهٔ نامتناسب ایجاد می‌کند.
- ۹ پرسش باز (`OQ-01` تا `OQ-12`) برخی مستقیماً ساختار FK و Constraint را تعیین می‌کنند.
- مالک پروژه صریحاً دستور داده تا تصمیم نهایی، هیچ Migration یا Code Change انجام نشود.

### پیامدها
- دروازهٔ مهاجرت فعلی: `BLOCKED — BUSINESS DECISION REQUIRED`
- گذار به `READY FOR SCHEMA DESIGN` مشروط به پاسخ شش پرسش کلیدی است.
- گذار به `READY FOR IMPLEMENTATION` مشروط به تأیید صریح کتبی برای هر عملیات
  مخرب مؤخر (`AGENTS.md` Rule 3).

### وضعیت
- ACTIVE

---

## DEC-009 — تثبیت نهایی تصمیمات V1.8 (BD-01 تا BD-07)

### تاریخ
2026-09-21

### تصمیم
تصمیمات قطعی زیر به‌عنوان مبنای طراحی V1.8 تثبیت می‌شوند و **بازبینی نمی‌شوند**:

| ID | تصمیم |
|---|---|
| **BD-01** | Weight متعلق به `Module Stage` است. Task مالک Weight نیست. WBS Phase نیز مالک Weight نیست. |
| **BD-02** | WBS Phase دارای عنوان، تاریخ شروع، تاریخ پایان/مدت، خروجی‌های مورد انتظار، Checklist Items، وضعیت تکمیل و تأیید نهایی Supervisor است. **WBS Phase برای محاسبه Weight استفاده نمی‌شود.** Checklist هر خروجی را «کامل/ناقص» تعیین می‌کند و مرجع نهایی تکمیل، فقط Project Supervisor است. |
| **BD-03** | Module/Deliverable واحد اصلی توسعه است. هر Module دارای ۹ Stage استاندارد است. مجموع Weight استاندارد یک Module = **۱۰۰٪**. |
| **BD-04** | Stageها می‌توانند **جزئی** تأیید شوند. سیستم **نباید** فقط یک Boolean `approved = true` داشته باشد. مقدار ثبت‌شده، مقدار تأییدشده و History باید ردیابی شوند. |
| **BD-05** | Employer و Supervisor هر دو می‌توانند درصد را ثبت/تغییر دهند. **Supervisor مرجع تأیید نهایی است و می‌تواند خودش درصد را تغییر دهد و سپس همان را تأیید نهایی کند.** تمام تغییرات Audit Trail دارند. |
| **BD-06** | Support یک WBS Phase مستقل نیست؛ یک Stage از Module است (`Support = 15%`). **Taskهای Support مستقل از WBS Phase هستند.** چرخه: تخصیص → انجام → ارسال خروجی → بررسی Employer/Supervisor → تأیید/رد → SLA فعال. |
| **BD-07** | برای Task درصد پیشرفت مستقل نداریم. Task فقط واحد اجرای کار است. Task نباید Weight مستقل داشته باشد. |

### دلیل
تصمیمات قطعی مالک پروژه در فاز Final Reconciliation. این‌ها جایگزین تمام تفاسیر قبلی می‌شوند.

### پیامدهای تازهٔ این فاز (نسبت به DEC-003..DEC-008)
- **BD-02:** «تاریخ پایان/مدت» و «Checklist Items» صریحاً به فهرست فیلدهای Phase اضافه شد و قاعدهٔ منفی «WBS Phase برای محاسبهٔ Weight استفاده نمی‌شود» تثبیت شد.
- **BD-04:** مدل Boolean برای Stage **ممنوع** اعلام شد → `proposed_amount` و `approved_amount` **باید** ستون‌های جدا باشند.
- **BD-05:** چون Supervisor می‌تواند مقدار را تغییر دهد و سپس تأیید کند، `approved_amount` **می‌تواند** از `proposed_amount` متفاوت باشد.
- **BD-06:** چرخهٔ Support Task کامل توصیف شد و SLA آن صریحاً فعال اعلام شد.

### وضعیت
- ACTIVE (طراحی)

---

## DEC-010 — تصمیمات OQ که از سابقهٔ Repository استخراج شدند

### تاریخ
2026-09-21

### تصمیم
پرسش‌های باز V1.8 که پاسخشان **از قبل در Repository وجود داشت** (بدون اختراع تصمیم جدید) استخراج و ثبت می‌شوند:

| OQ | تصمیم استخراج‌شده | سابقهٔ Repository |
|---|---|---|
| **OQ-03** | **بدون رابطه بین WBS Phase و Module** (گزینهٔ ج). هر دو فرزند مستقیم Project هستند. رابطهٔ اختیاری در آینده، افزایشی و بدون شکستن ساختار است. | دیاگرام معماری Step 2 مالک پروژه (شاخه‌های هم‌تراز) + BD-02 (بی‌نیازی کارکردی) |
| **OQ-06** | **فقط یک `pending` در هر لحظه برای هر Stage** (گزینهٔ الف). | `TMS_PROJECT_TRACKER.md §13` («Only one Pending request should exist per Task» — علامت `[x]`) + پیاده‌سازی در `WeightChangeRequestService.php:24-26` با `PendingRequestExistsException` + تست `WeightChangeRequestServiceTest:144-148` |
| **OQ-06-a** | **`module_id` Denormalized حفظ می‌شود.** | الگوی `tasks.contract_id` / `tasks.contractor_id` + `TMS_PROJECT_TRACKER.md §3` («Controlled denormalization exists») |
| **OQ-06-b** | **`approved_amount` می‌تواند از `proposed_amount` متفاوت باشد.** مقدار پیشنهادی برای تاریخچه حفظ و بازنویسی نمی‌شود. | **BD-05** («Supervisor خودش می‌تواند درصد را تغییر دهد و سپس تأیید کند») |
| **OQ-07** | **برچسب «آماده پرداخت» قطعاً باید تغییر کند.** محدودیت استخراج‌شده؛ تشخیص دقیق (تغییر برچسب / حذف / تغییر منبع) توصیه‌شده و غیرمسدودکننده. | `TMS_PROJECT_TRACKER.md §2` («No financial payment formula» — خارج از محدودهٔ V1، علامت `[x]`) + `docs/01-project-overview.md:83` + برچسب فعلی در `resources/views/reports/index.blade.php:26,50` |
| **OQ-12** | **غیرمسدودکننده — به فاز بعد موکول.** هیچ نوع Morph جدیدی در V1.8a لازم نیست؛ جریان شواهد از `documents → Task` (نوع موجود) عبور می‌کند. افزودن نوع جدید Schema-neutral است چون `attachable_type` یک `VARCHAR(255)` آزاد است. | `TMS_PROJECT_TRACKER.md D-08` (Pending) + `documents` migration + `Task::documents()` |
| **OQ-05 (قدیمی)** | **معنای `lock_weight` استخراج شد و منقضی است.** معنای اصلی: «قفل وزن **Task** پس از ارجاع؛ تغییر فقط با درخواست و تأیید.» با BD-07 منتفی می‌شود. | `database/seeders/SystemSettingSeeder.php` (توصیف `lock_weight`) + `TMS_PROJECT_TRACKER.md §13` + `docs/database_physical_design_v1.5.md` («Weight locking frozen») + پیاده‌سازی `WeightChangeRequestService` |
| **OQ-13..OQ-18, OQ-02, OQ-11** | **موکول به فاز پیاده‌سازی** — هیچ‌کدام Schema را مسدود نمی‌کنند. | — |

### دلیل
پرامپت V1.8 Final Reconciliation صریحاً دستور داد: «اگر تصمیم قبلاً مشخص شده، آن را استخراج و ثبت کن. اگر واقعاً تصمیم گرفته نشده، فقط سؤال را برای من مطرح کن. هرگز از خودت Business Decision ایجاد نکن.» این ADR موارد استخراج‌شده را ثبت می‌کند.

### پیامدها
- سه پرسش به‌عنوان **حل‌شده** بسته می‌شوند: `OQ-03`، `OQ-06`، `OQ-06-b`.
- دو مورد **غیرمسدودکننده** اعلام می‌شوند: `OQ-12`، `OQ-07` (با محدودیت استخراج‌شده).
- معنای قدیمی `OQ-05` **منقضی** می‌شود.
- **سه پرسش باقی می‌ماند** که تصمیم کسب‌وکاری می‌خواهند: `OQ-01a`، `OQ-04`، `OQ-05` (معنای جدید).

### وضعیت
- ACTIVE

---

## DEC-011 — مانع Audit: گام صفر پیاده‌سازی V1.8a

### تاریخ
2026-09-21

### تصمیم
`DatabaseAuditService` **وجود ندارد** و باید ساخته شود؛ Binding در `AppServiceProvider` باید از `NullAuditService` به آن تغییر کند. این **گام صفر** پیاده‌سازی V1.8a است.

### دلیل
BD-05 الزام می‌کند: «تمام تغییرات باید Audit Trail داشته باشند.» و BD-04: «مقدار Weight/Percentage ثبت‌شده، مقدار تأییدشده و History آن باید قابل ردیابی باشد.»

بدون نویسندهٔ فعال، این الزامات غیرقابل تحقق‌اند.

### شواهد تأییدشده در این فاز
```bash
$ find app -iname "*Audit*" -type f
app/Domain/Contracts/AuditServiceInterface.php
app/Domain/Services/NullAuditService.php      ← تنها پیاده‌سازی، No-op
```
```php
// AppServiceProvider::register() خطوط ۱۹-۲۲
$this->app->bind(AuditServiceInterface::class, NullAuditService::class);
```
```php
// NullAuditService::log() خطوط ۱۵-۱۸
return new ActivityLog;      // ← بدون save()
```
- هیچ `ActivityLog::create` در `app/` وجود ندارد (تنها در `ImmutableRecordTest`).
- **۵ سرویس دامنه** `AuditServiceInterface` را تزریق می‌کنند و تمام فراخوانی‌هایشان دور ریخته می‌شود: `TaskService`، `ApprovalService`، `TaskAssignmentService`، `WeightChangeRequestService`، `DocumentService`.

### پیامدها
- جدول `activity_logs` **بدون هیچ تغییر ساختاری** تمام نیازمندی‌های تاریخچه را پوشش می‌دهد (دارای `old_values`/`new_values` JSONB، `user_id`، `reason`، و Immutable بودن از طریق `booted()`).
- **مشکل در نویسنده است، نه در مخزن.**
- الزام `TMS_PROJECT_TRACKER.md §8` («Important Audit failure must roll back the business operation») بدون این، غیرقابل تحقق است.
- **هیچ تصمیم کسب‌وکاری لازم نیست** — این یک اقدام فنی است.

### وضعیت
- ACTIVE (گام صفر پیاده‌سازی — اجرا نشده)

---

## DEC-012 — دروازهٔ نهایی V1.8: BLOCKED — BUSINESS DECISION REQUIRED

### تاریخ
2026-09-21

### تصمیم
وضعیت دروازهٔ مهاجرت V1.8 پس از فاز Final Reconciliation:

```text
BLOCKED — BUSINESS DECISION REQUIRED
```

سه پرسش باقی‌مانده که تصمیم کسب‌وکاری می‌خواهند و از خودمان قابل پاسخ‌دهی نیستند:

| ID | پرسش | چرا مسدودکننده است |
|---|---|---|
| **OQ-01a** | آیا `modules.weight` (سهم ماژول از پروژه) وجود دارد؟ | تعریف جدول `modules` را عوض می‌کند |
| **OQ-04** | آیا هر Task الزاماً به یک Module Stage وصل می‌شود؟ | تعیین می‌کند `tasks.module_stage_id` NOT NULL باشد یا NULL، و آیا `task_type` وارد Migration اولیه شود |
| **OQ-05 (جدید)** | آیا وزن پایهٔ Stage پس از ثبت تأیید قفل می‌شود؟ | تعیین می‌کند آیا هر رکورد تأیید باید وزن پایه را Snapshot کند |

چهار توصیهٔ با پیش‌فرض پیشنهادی (در صورت تأیید، مسدود نمی‌کنند): `OQ-01b` (مجموع دقیقاً ۱۰۰)، `OQ-05` قدیمی (بازنشستگی `lock_weight`)، `OQ-06` (یک pending)، `OQ-07` (تغییر برچسب گزارش).

### دلیل
- پرامپت Step 1: «هرگز از خودت Business Decision ایجاد نکن.»
- پرامپت Step 8: اعلام صریح یکی از سه وضعیت.
- تصمیم قبلی مالک پروژه (§۳ پرامپت فاز قبل): «بدون تأیید Constraint جدید ایجاد نکن.»

### مانع جداگانه (پیاده‌سازی، نه طراحی)
**PostgreSQL در دسترس نیست:**
```text
PostgreSQL reachable:   NO
Database reachable:     NO
Migrations executable:  NO
Tests executable:       NO
```
بیس تست «۱۱۴ تست / ۳۳۴ assertion» **نامعلوم (UNVERIFIED)** است. هیچ نتیجه‌ای جعل نشد.

### پیامدها
- طراحی Schema **معلق** است تا پاسخ سه پرسش بالا.
- پس از پاسخ‌ها → `READY FOR SCHEMA DESIGN` → طراحی تفصیلی → `DatabaseAuditService` → راه‌اندازی PostgreSQL → V1.8a → V1.8b.
- **هیچ Migration، Model، Controller، Route، Service، Blade یا Test ایجاد/تغییر نشد.**

### وضعیت
- ACTIVE → **SUPERSEDED by DEC-013** (هر سه پرسش پاسخ گرفتند)

---

## DEC-013 — سه تصمیم قطعی نهایی V1.8 (پاسخ به دروازهٔ طراحی Schema)

### تاریخ
2026-09-21

### تصمیم
مالک پروژه هر سه پرسش باقی‌مانده را **قطعی** پاسخ داد. این تصمیمات **بازبینی نمی‌شوند**:

| پرسش | پاسخ | تصمیم |
|---|---|---|
| `OQ-01a` | **1A** | `modules.weight` **وجود دارد**. یک Project می‌تواند چند Module داشته باشد. `SUM(modules.weight) = 100%` برای دامنهٔ توسعهٔ قابل‌اعمال. **ستون `kind` ساخته نمی‌شود.** وزن WBS Phase در این محاسبه استفاده نمی‌شود. |
| `OQ-04` | **2B** | یک Task **لازم نیست** به Module Stage وصل باشد. `tasks.module_stage_id` **NULLABLE** می‌ماند. چون نوع از اتصال استنتاج نمی‌شود، **`task_type` اضافه می‌شود** با مقادیر `development` و `support`. `task_type` به نقش کاربر ربطی ندارد. |
| `OQ-05` | **3A** | `SUM(module_stages.weight) = 100%` به‌ازای هر Module. **وزن پایهٔ Stage پس از اولین تأیید قفل می‌شود** و `module_stages.weight` پس از آن قابل تغییر مستقیم نیست. **از تنظیم قدیمی `lock_weight` استفاده نشود.** |

### دلیل
پاسخ مالک پروژه در فاز Schema Design. این تصمیمات جایگزین `DEC-012` (وضعیت BLOCKED) می‌شوند.

### پیامدهای کلیدی
1. **`module_stages.weight` تنها منبع حقیقت وزن تخصیصی است** — چون پس از اولین تأیید قفل می‌شود، **نیاز به ستون Snapshot روی `stage_progress_approvals` منتفی است** (دقیقاً مطابق دستور «Do not create a second competing source of truth for allocated weight»).
2. **`task_type` منبع قطعی نوع Task است**، نه `module_stage_id`. یک Support Task می‌تواند `module_stage_id = NULL` و `wbs_phase_id = NULL` داشته باشد.
3. **قفل وزن Stage یک قاعدهٔ ساختاری است، نه یک تنظیم قابل تغییر** — جایگزینی `lock_weight` با کلید تنظیماتی جدید انجام **نمی‌شود**.
4. `SUM(modules.weight) = 100%` و `SUM(module_stages.weight) = 100%` **قواعد چند-ردیفی** هستند که CHECK استاندارد PostgreSQL نمی‌تواند اعمال کند. راهبرد: مرز تراکنش دامنه (الزامی) + Constraint Trigger تعویق‌شده (توصیه‌شده).
5. **`0 <= approved_amount <= proposed_amount`** — تأییدشده هرگز از پیشنهادی بیشتر نمی‌شود (تفاوت با `DEC-010`/`OQ-06-b` که «متفاوت بودن» را مجاز می‌دانست).

### وضعیت
- ACTIVE (قطعی — طراحی Schema بر این مبنا انجام شد)

---

## DEC-014 — ساختار Schema نهایی V1.8 و راهبرد اعمال قواعد مجموع

### تاریخ
2026-09-21

### تصمیم
Schema نهایی V1.8 طراحی شد (سند: `docs/V1.8_DETAILED_SCHEMA_DESIGN.md`):

**۴ جدول جدید:** `modules` · `module_stages` · `stage_progress_approvals` · `wbs_phase_checklist_items`
**۲ جدول تغییر‌یابنده:** `tasks` (+`task_type`، +`module_stage_id`، +`module_id`) · `wbs_phases` (+۵ ستون تأیید نهایی)
**۵ جدول بدون تغییر ساختاری:** `projects` · `performance_records` · `activity_logs` · `system_settings` · `weight_change_requests`

**راهبرد اعمال قواعد مجموع (بدون CHECK جعلی):**
- **لایهٔ ۱ (الزامی):** مرز تراکنش دامنه با `lockForUpdate()` — الگوی موجود `WeightChangeRequestService:55-84`.
- **لایهٔ ۲ (توصیه‌شده):** `CONSTRAINT TRIGGER ... DEFERRABLE INITIALLY DEFERRED` — مکانیزم بومی PostgreSQL برای invariant چند-ردیفی. **CHECK جعلی ساخته نشد.**
- **ضرورت `DEFERRED`:** بازتوازن وزن بین دو ماژول ذاتاً یک حالت میانی ناسازگار دارد؛ Trigger فوری اولین `UPDATE` را می‌شکند.

### دلیل
- دستور صریح: «If PostgreSQL cannot safely enforce the aggregate constraint with a normal CHECK constraint, do NOT fake it with a CHECK constraint. Explain the enforcement strategy.»
- Repository سابقهٔ دقیق خطای مقابل را دارد: قاعدهٔ «مجموع وزن فازهای Project = 100» در دو سند ثبت شد (`docs/database_physical_design_v1.5.md:113` + `TMS_PROJECT_TRACKER.md §6`) اما **هرگز پیاده‌سازی نشد** (`C-06`/`C-07`). لایهٔ ۲ دقیقاً از تکرار این الگو جلوگیری می‌کند.

### پیامدها
- **`tasks.weight`** → `DROP NOT NULL` (گام ۱ از دو). `DROP COLUMN` به V1.9+ موکول با تأیید صریح کتبی.
- **`wbs_phases.weight`** → LEGACY. صفر خواننده، صفر تست — می‌تواند در V1.9 بدون ریسک حذف شود.
- **`system_settings.lock_weight`** → DEPRECATED. صفر مصرف‌کننده. **بدون جایگزین** (قفل یک قاعده است، نه تنظیمات).
- **`weight_change_requests`** → LEGACY. صفر مصرف جدید، بدون UI.
- **`performance_records`** → ACTIVE با معنای اصلاح‌شده (Snapshot دوره‌ای از تأییدهای Stage).
- **⛔ شکاف کشف‌شده:** `performance_records` بعد پیمانکار دارد اما `stage_progress_approvals` ندارد → محاسبهٔ `total_weight_completed` per-contractor تعریف‌نشده است. **از خودمان قاعده نساختیم** — به فاز Performance موکول شد (`OQ-32`).
- **`OQ-14` حل شد:** مالک پروژه رویداد `wbs_phase_reopened` را نام برد → بازگشایی فاز تکمیل‌شده پشتیبانی می‌شود.

### وضعیت
- ACTIVE (طراحی — 🚫 اجرا نشده)

---

## DEC-015 — دروازهٔ Migration Review و پیش‌نیاز محیطی

### تاریخ
2026-09-21

### تصمیم
وضعیت دروازه:

```text
READY FOR MIGRATION REVIEW
```

**پیش‌نیاز محیطی (جدا از دروازه):**
```text
PostgreSQL reachable:   NO
Database reachable:     NO
Migrations executable:  NO
Tests executable:       NO
```

### دلیل
- هر سه تصمیم کسب‌وکاری دریافت شد (`DEC-013`) → هیچ پرسش کسب‌وکاری مسدودکننده‌ای باقی نمانده.
- Schema کامل طراحی شد: ۴ جدول جدید، ۱۲ FK، ~۲۰ Constraint، ۱۸ Index، راهبرد Audit، مهاجرت، Backfill و Rollback.
- **اما PostgreSQL در دسترس نیست** — طراحی Schema یک فعالیت مستندسازی است و مسدود نمی‌شود؛ اجرای Migration و تست مسدود می‌شود.
- دستور صریح: «Do NOT assume PostgreSQL is available merely because it is configured. First complete the schema design and report the exact PostgreSQL prerequisite separately.»

### شواهد محیطی (بدون تغییر تنظیمات — فقط خوانده شد)
| # | بررسی | نتیجه |
|---|---|---|
| ۱ | پورت ۵۴۳۲ در LISTEN | ❌ خیر |
| ۲ | `pg_isready -h 127.0.0.1 -p 5432` | ❌ `no response` · `exit=2` |
| ۳ | سرویس ویندوز `postgres` | ❌ ثبت نشده |
| ۴ | پورت‌های ۵۴۳۰–۵۴۳۵ | ❌ خالی |
| ۵ | `psql --version` | ✅ `18.6` (کلاینت هست، سرور نیست) |

### پیامدها
- **اقدام لازم پیش از هر پیاده‌سازی:** راه‌اندازی PostgreSQL → ایجاد `tms` و `tms_testing` → بازتأیید بیس تست (وضعیت فعلی: **نامعلوم**).
- **⛔ هیچ نتیجهٔ تستی جعل نشد.** ادعای «۱۱۴ تست / ۳۳۴ assertion» تأییدنشده است.
- **۳ پرسش 🔴** که ورودی مرحلهٔ Migration هستند (نه مسدودکنندهٔ طراحی): `OQ-28` (`expected_output` برای فازهای جدید) · `OQ-29` (`down()` مهاجرت `M-07`) · `OQ-27` (Backfill `task_type`).
  ✅ **این سه بسته شدند** — `DEC-016` · `DEC-017` · `DEC-018`.
- **۵ پرسش 🟡** نیازمند تأیید ساختار: `OQ-30` (Triggerها) · `OQ-31` · `OQ-24` · `OQ-25` · `OQ-02`. **(همچنان باز — ورودی Migration Review)**
- **🛑 توقف در همین نقطه** — طبق دستور: «Stop after the Detailed Schema Design.»

### وضعیت
- ACTIVE → **سه پرسش 🔴 آن در `DEC-016`..`DEC-018` بسته شد؛ وضعیت دروازه در `DEC-019` تثبیت شد.**

---

## DEC-016 — Backfill مقدار `task_type` برای تسک‌های موجود (`OQ-27`)

### تاریخ
2026-09-22

### تصمیم
تمام Taskهای موجود در زمان Migration اولیهٔ V1.8 مقدار **`task_type = 'development'`** می‌گیرند.

- `task_type` به‌صورت `VARCHAR(50) NOT NULL DEFAULT 'development'` اضافه می‌شود (Migration `M-05`).
- Backfill از طریق همان `DEFAULT` و در همان `ALTER TABLE` انجام می‌شود — **بدون کوئری داده‌ای جداگانه**.
- هیچ مقدار موجودی بازنویسی نمی‌شود؛ تنها ردیف‌های موجود، مقدار پیش‌فرض می‌گیرند.
- **Support به‌عنوان یک Task Type رسمی از V1.8 به بعد ایجاد می‌شود.**

### دلیل
- مفهوم `support` به‌عنوان یک Task Type رسمی **پیش از V1.8 وجود نداشت** (`OQ-04 = 2B`). پس هیچ نشانهٔ داده‌ای برای تفکیک تسک‌های پشتیبانی موجود در دسترس نیست.
- انتخاب `development` تنها گزینهٔ **غیردلبخواه** است.
- تسک‌های Support جدید از این پس صریحاً با `task_type = 'support'` ثبت می‌شوند (`BD-06`).

### گزینه‌های بررسی‌شده
- `development` برای همه (انتخاب شد).
- مقدار `NULL` و بازطبقه‌بندی بعدی (رد شد — ستون NOT NULL است و منبع حقیقت نوع باید از روز اول پر باشد).
- حدس‌زدن نوع بر اساس عنوان تسک یا `wbs_phase_id` (رد شد — نقض «نوع نباید استنتاج شود»).

### پیامدها
- هیچ تسکی در Migration اولیه دسته‌بندی خودسرانه نمی‌گیرد.
- اگر تسک Supportی در دادهٔ موجود باشد، بازطبقه‌بندی آن **دستی و خارج از دامنهٔ Migration** است.
- `OQ-27` بسته شد و از فهرست ورودی‌های 🔴 Migration Review خارج است.

### وضعیت
- ACTIVE (قطعی — ورودی طراحی Migration)

---

## DEC-017 — مقدار `expected_output` برای WBS Phaseهای جدید (`OQ-28`)

### تاریخ
2026-09-22

### تصمیم
برای WBS Phaseهای **جدید**، مقدار این ستون این متن ثابت است:

```text
expected_output = "مشاهدهٔ Checklist"
```

- این مقدار **فقط یک متنِ سازگار با ساختار فعلی** است (ستون `TEXT NOT NULL`).
- **Checklist (`wbs_phase_checklist_items`) مرجع واقعی خروجی فاز است.**
- ستون `expected_output` حذف نمی‌شود و قید `NOT NULL` آن نیز برداشته نمی‌شود (گزینهٔ «ج» رد شد).

### دلیل
- ستون `NOT NULL` است، پس هر فاز جدید باید مقدار داشته باشد؛ این مقدار باید در UI خوانا و بی‌ابهام باشد.
- رشتهٔ خالی `''` (گزینهٔ الف) در UI به‌عنوان یک فیلد ناقص دیده می‌شود.
- حذف `NOT NULL` (گزینهٔ ج) یک تغییر ساختاری اضافی و بی‌ضرورت است.

### پیامدها
- `M-06` و مسیر درج فاز جدید، مقدار ثابت «مشاهدهٔ Checklist» را می‌نویسند.
- انتقال کامل محتوای `expected_output` به آیتم‌های Checklist همچنان به V1.9 موکول است (بخش ۱۱.۵ سند Schema) و تا آن زمان ستون حفظ می‌شود.
- `OQ-28` بسته شد.

### وضعیت
- ACTIVE (قطعی — ورودی طراحی Migration)

---

## DEC-018 — رفتار `down()` مهاجرت `M-07` و قاعدهٔ Rollback بدون دادهٔ جعلی (`OQ-29`)

### تاریخ
2026-09-22

### تصمیم
`down()` مهاجرت `M-07` (`relax_tasks_weight_nullable`) **آگاهانه `throw` می‌کند**.

```php
// down() — الگوی قطعی
throw new RuntimeException(
    'M-07 cannot be safely rolled back: tasks.weight is NULL for existing rows '
    . 'and no reliable original value can be reconstructed. '
    . 'Decide manually before restoring NOT NULL.'
);
```

**قاعدهٔ عمومی ثبت‌شده در این تصمیم:**

> اگر Rollback نیازمند داده‌ای باشد که دیگر قابل بازسازی مطمئن نیست، Migration باید با **Exception واضح** متوقف شود و **نباید دادهٔ جعلی تولید کند.**

- `UPDATE tasks SET weight = 0 WHERE weight IS NULL` (گزینهٔ ب) **ممنوع** است — تولید دادهٔ مصنوعی.
- `down()` خالی/no-op (گزینهٔ ج) **ممنوع** است — یک حالت ناسازگار (ستون NULL با فرض NOT NULL در فاز قبل) باقی می‌گذارد.
- جهت `up()` این مهاجرت تغییری نمی‌کند: فقط `DROP NOT NULL` — **غیرمخرب** و بدون حذف هیچ داده‌ای.

### دلیل
- مقدار `weight` برای ردیف‌های جدید **هیچ مقدار اصلی قابل بازسازی ندارد**؛ هر عددی که نوشته شود اختراع داده است.
- الزام ممیزی V1.8: «هیچ نتیجه یا دادهٔ ساختگی تولید نشود.» همان اصلی که در تست‌ها رعایت شد، در Rollback هم معتبر است.
- یک استثناء صریح، بهتر از یک Rollback بی‌صدا با دادهٔ نادرست است: اپراتور را وادار به تصمیم آگاهانه می‌کند.

### پیامدها
- مسیر `migrate:rollback` برای `M-07` به‌صورت خودکار کار **نمی‌کند** و نیازمند دخالت دستی است — این رفتار **مطلوب و آگاهانه** است.
- ترتیب Rollback در بخش ۱۲.۲ سند Schema برای هشت مهاجرت دیگر معتبر می‌ماند.
- `OQ-29` بسته شد.

### وضعیت
- ACTIVE (قطعی — ورودی طراحی Migration)

---

## DEC-019 — بستن ورودی‌های Migration Review و تثبیت وضعیت دروازه

### تاریخ
2026-09-22

### تصمیم
سه پرسش 🔴 ورودی Migration بسته شدند و ورودی‌های فاز **Migration Review** قفل شد:

| پرسش | پاسخ قطعی | مرجع |
|---|---|---|
| `OQ-27` | Backfill `task_type = 'development'` برای تسک‌های موجود؛ Support از V1.8 رسمی است | `DEC-016` |
| `OQ-28` | `expected_output = "مشاهدهٔ Checklist"` برای فازهای جدید؛ Checklist مرجع واقعی خروجی | `DEC-017` |
| `OQ-29` | `down()` مهاجرت `M-07` **آگاهانه `throw`** می‌کند؛ بدون دادهٔ جعلی | `DEC-018` |

**وضعیت دروازه (بدون تغییر نسبت به `DEC-015`):**
```text
READY FOR MIGRATION REVIEW
```

**صراحت لازم:** این وضعیت به معنای **مجوز اجرای Migration نیست**.
- Migration Review = بازبینی مستندات طراحی با چک‌لیست `docs/V1.8_MIGRATION_REVIEW_CHECKLIST.md`.
- Migration Implementation = نوشتن و اجرای فایل‌های Migration — **جدا و هنوز مجاز نیست**.
- Migration Review باید پس از آماده‌شدن PostgreSQL انجام شود.

**چک‌لیست رسمی Migration Review ساخته شد:** `docs/V1.8_MIGRATION_REVIEW_CHECKLIST.md` — پنج محور Schema · Domain rules · Legacy · Audit · Environment.

### دلیل
- طبق دستور مالک پروژه، هیچ پرسش حل‌شده‌ای نباید همچنان به‌عنوان blocker نمایش داده شود.
- جداسازی «Review» از «Implementation» الزام صریح است تا بازبینی مستندات با عدم آمادگی محیطی قفل نشود و در مقابل، اجرای Migration بدون Review آغاز نشود.

### پیامدها
- فهرست پرسش‌های 🔴 سند Schema Design به **صفر** رسید؛ فقط ۵ پرسش 🟡 ساختاری (ورودی Migration Review) و موارد 🟢 موکول باقی مانده‌اند.
- **سطح محیطی به‌روزرسانی شد:** در بازبینی ۱۴۰۵/۰۶/۳۱ (2026-09-22)، سرور PostgreSQL روی `127.0.0.1:5432` **در حال شنیدن است** و `pg_isready` پاسخ `accepting connections` می‌دهد (فرایند `postgres`، PID 22436) — برخلاف وضعیت ثبت‌شده در `DEC-015`.
- **و در بازبینی Migration Review (۱۴۰۵/۰۶/۳۱، همه خواندنی) تأیید سطح-دیتابیس نیز انجام شد:** `tms` **موجود** · `tms_testing` **موجود** · اتصال Laravel تأییدشده (`php artisan db:show` → `Database: tms`, ۳۱ جدول) · `php artisan migrate:status` → **۲۳/۲۳ Ran** · صفر جدول V1.8 · `tasks.weight` هنوز `NOT NULL`.
  **تنها مورد بازمانده:** اجرای واقعی Test Suite کامل — `NOT RUN — would require schema mutation` (`tests/Pest.php` → `RefreshDatabase` → عملاً `migrate:fresh` روی `tms_testing`) · اجرای واحد Unit: **۱۶ passed / ۵۱ assertions**. مرجع: `docs/V1.8_MIGRATION_REVIEW_REPORT.md`.
  **توجه:** این یک **به‌روزرسانی وضعیت محیطی** است، نه تصمیم جدید — هیچ قاعدهٔ کسب‌وکاری جدیدی در این فاز ثبت نشده است.
- ادعای «۱۱۴ تست / ۳۳۴ assertion» **همچنان UNVERIFIED** است. هیچ نتیجهٔ تستی — نه PASS و نه FAIL — ثبت یا جعل نشد.
- گام بعدی اجباری پیش از Implementation: راه‌اندازی/تأیید `tms` و `tms_testing` → تأیید اتصال Laravel → اجرای واقعی بیس تست → ثبت نتیجهٔ واقعی.

### وضعیت
- ACTIVE (قطعی — ورودی Migration Review قفل شد؛ Migration Implementation مجاز نیست)

---

## ⏳ پیوست — موارد در انتظار تصمیم مالک پروژه (۱۴۰۵/۰۶/۳۱)

> ✅ **حل شد (۱۴۰۵/۰۶/۳۱ / 2026-09-22).** هر ۶ مورد «نیازمند تصمیم» توسط مالک پروژه پاسخ گرفت و در `DEC-020`..`DEC-034` ثبت شد؛ ۹ توصیهٔ آماده نیز تأیید و در طراحی اعمال شد. `H-2` و `N-2` و `N-3` به‌عنوان اصلاح طراحی/فنی بسته شدند (بدون تصمیم کسب‌وکاری جدید) و `H-7`/`N-5` مستنداتی بودند.
> **مرجع نتیجه:** `docs/V1.8_FINAL_DESIGN_RECONCILIATION.md` · وضعیت دروازه: `DEC-035`.
> این پیوست صرفاً **سابقهٔ تاریخی** است و وضعیت جاری نیست.

> ⛔ **این پیوست «تصمیم» نیست.** در این فاز **هیچ `DEC` جدیدی ثبت نشد** و هیچ قاعدهٔ کسب‌وکاری جدیدی اختراع نشد.
> برچسب هر مورد: `RECOMMENDATION — NOT APPROVED`.
> سند کامل با شواهد، گزینه‌ها و پیامدها: `docs/V1.8_OQ_AND_REVIEW_ISSUE_RESOLUTION.md`.
> حکم جاری دروازه: `BLOCKED — OWNER DECISION REQUIRED`.

### الف) ۶ مورد نیازمند **تصمیم** مالک پروژه

| # | مورد | ماهیت | توصیهٔ فنی در انتظار تأیید |
|---|---|---|---|
| ۱ | `OQ-30` | معماری — ساخت پنج `CONSTRAINT TRIGGER`؟ | گزینهٔ **D** (مشروط): Triggerهای تک‌ردیفی سالم ساخته شوند؛ سه Trigger تجمعی پس از بستن `H-3`/`H-4`/`H-5` و اصلاح `N-1`/`N-2` |
| ۲ | `OQ-24` | معماری — `tasks.module_id` بماند یا حذف شود؟ | بماند + تضمین در Service، **بدون Trigger** (الگوی `tasks.contract_id` و `DEC-010`) |
| ۳ | `H-3` | قاعدهٔ عملیاتی — ایجاد/بازتوازن ماژول در چند تراکنش | قاعدهٔ **R-1..R-4**: همهٔ ماژول‌های یک پروژه در یک تراکنش؛ صفر ماژول مشروع؛ حذف/بازگردانی همراه بازتوازن |
| ۴ | `H-4` | دکترین قفل‌گذاری (Write Skew) | **دکترین قفل والد:** هر تراکنشِ تغییردهندهٔ یک محدودهٔ تجمیعی، نخست ردیف والد را قفل می‌کند (`projects` / `modules` / `module_stages`) |
| ۵ | `H-5` | قاعدهٔ کسب‌وکاری — معنای «صفر ماژول فعال» | invariant روی مجموعهٔ `deleted_at IS NULL`؛ «صفر ماژول فعال» **مشروع** (الزامی برای ایجاد تدریجی) |
| ۶ | `N-4` | دکترین — کدام قاعده در DB و کدام در Service | جدول واحد تفکیک: تک‌ردیفی → DB · تجمعی → Service + Trigger اختیاری (Backstop) · cross-table → Service |

### ب) ۹ مورد با **توصیهٔ آماده** (تأیید سبک لازم است)

| مورد | توصیه |
|---|---|
| `OQ-31` | Trigger `BEFORE DELETE` بساز (تك‌ردیفی؛ هم‌زمان `OQ-22` بسته شود) |
| `OQ-25` | `metadata['reason'] → activity_logs.reason` و باقی → `new_values['metadata']` (گزینهٔ C) |
| `OQ-02` | طراحی فعلی (`code` NULLABLE + `UNIQUE (project_id, code)`) حفظ شود؛ Partial Index اضافه نشود |
| `H-1` | قفل والد `module_stages` سپس تجمیع **بدون** `FOR UPDATE` (گزینهٔ B)؛ جانشین: `pg_advisory_xact_lock` |
| `H-2` | `M-07` در Batch مستقلِ قدیمی‌تر + `down()` شرطی (گزینهٔ C + D) |
| `H-6` | فراخوانی صریح `document_uploaded` در `uploadDocument()` (فاز V1.8a) + اصلاح دو سند |
| `N-1` | سقف ظرفیت روی «تأییدهای فعال» (`NOT EXISTS` روی `supersedes_approval_id`) و Keeping Trigger تغییرناپذیری سخت |
| `N-2` | شاخه‌بندی `TG_OP` در بدنهٔ توابع `assert_project_module_weight_sum` و `assert_module_stage_weight_sum` |
| `N-3` | الزام Service «Stage فقط از همان پروژهٔ تسک» (بدون Constraint جدید) |

### ج) موارد مستنداتی (تصمیم نمی‌خواهند)

| مورد | وضعیت |
|---|---|
| `H-7` | ✅ **انجام شد** — ۱۵ بند کهنه در `docs/10-database-design.md` اصلاح شد؛ ۳ بند مبهم فقط گزارش شد |
| `N-5` | ✅ ثبت شد — هر ۲۳ Migration موجود در `Batch 1` هستند |

### ⛔ آنچه در این فاز **انجام نشد** (و نباید بدون تأیید صریح انجام شود)

```text
• هیچ فایل Migrationی نوشته یا تغییر نکرد
• هیچ Schema Mutation و هیچ تغییر داده‌ای رخ نداد
• هیچ کد اپلیکیشن (Model/Service/Controller/Request/Route/Blade/Test/Seeder) تغییر نکرد
• docs/V1.8_DETAILED_SCHEMA_DESIGN.md عمداً دست‌نخورده ماند (§۶.۵ · §۱۲.۲ · §۸.۳ · §۱۴.۱)
• هیچ بخشی از D-MEMORY/Checklist خودبه‌خود تأیید نشد
```

---

> **پایان سابقهٔ تاریخی پیوست ✅ — از این نقطه، تصمیمات قطعی این مرحله ثبت می‌شوند.**

---

## DEC-020 — راهبرد Trigger دیتابیس (`OQ-30` = گزینهٔ **D**)

### تاریخ
2026-09-22

### تصمیم مالک پروژه
**گزینهٔ D** — تقسیم دوگانهٔ Triggerها:

1. **Triggerهای تک‌ردیفی** که برای Integrity لازم‌اند **ساخته می‌شوند**.
2. **Triggerهای تجمعی** مربوط به (الف) مجموع Weight ماژول‌ها، (ب) مجموع Weight Stageها، (ج) سقف مجموع Approvalها **فعلاً به‌عنوان تصمیم نهایی پیاده‌سازی تلقی نمی‌شوند** تا `H-3` · `H-4` · `H-5` · `N-1` · `N-2` در طراحی نهایی به‌درستی حل شوند.

> این تصمیم به معنای نوشتن Migration در همین مرحله نیست.

### زمینه
`OQ-30` تعیین می‌کرد آیا ۵ Constraint Trigger پیشنهادی §۶ سند Schema ساخته شوند. Triggerهای شمارهٔ ۳ و ۴ (`trg_module_stages_weight_locked` و `trg_spa_immutable`) ذاتاً سالم‌اند (تک‌ردیفی، `BEFORE` فوری، بدون حالت میانی مشروع)؛ سه Trigger تجمعی پیش از حل `H-3`/`H-4`/`H-5` و اصلاح `N-1`/`N-2` نباید ساخته شوند: در برابر Write Skew مصون نیستند، ایجاد تدریجی Module را ناممکن می‌کنند و بدنهٔ تابعشان خطای PL/pgSQL می‌داد. Repository امروز **صفر Trigger** دارد.

### پیامدها
- `M-08` فقط Triggerهای تک‌ردیفی را دربر می‌گیرد: قفل وزن Stage · تغییرناپذیری رکورد تصمیم‌گرفته · حفاظت از حذف History (`DEC-029`).
- سه Trigger تجمعی از دامنهٔ V1.8 خارج شدند؛ طراحی اصلاح‌شدهٔ آن‌ها (با شاخه‌بندی `TG_OP` طبق `DEC-028`) فقط **مستند** می‌شود.
- قواعد تجمعی در V1.8 **فقط** با Service + Transaction + Parent Lock تضمین می‌شوند (`DEC-023` · `DEC-025` · `DEC-026`).
- اگر بعداً Trigger تجمعی تأیید شود، نقشش **Backstop** است، نه جانشین قفل والد.

### Schema متأثر
`modules` · `module_stages` · `stage_progress_approvals` — فقط در حد طراحی Trigger؛ صفر تغییر ستون

### Migration متأثر
`M-08` (محدود به Triggerهای تک‌ردیفی) · سه Trigger تجمعی از دامنهٔ V1.8 حذف شد

### وضعیت
- ACTIVE (قطعی)

---

## DEC-021 — `tasks.module_id` Denormalized باقی می‌ماند (`OQ-24` = گزینهٔ **A**)

### تاریخ
2026-09-22

### تصمیم مالک پروژه
**گزینهٔ A** — ستون `tasks.module_id` **باقی می‌ماند**، با شرایط:

- `module_id` **منبع مستقل Business Truth نیست**.
- سازگاری آن با Project / Contract / Contractor / Module باید در **Application Service** تضمین شود.
- **Trigger دیتابیسی برای این قاعده ایجاد نشود.**
- الگوی موجود Denormalization پروژه حفظ شود و منطق Business در Service بماند.

### زمینه
`§۶.۶` فقط هم‌خوانی `tasks.module_id ↔ module_stages.module_id` را تضمین می‌کرد؛ دو شکاف باقی بود: هم‌پروژه‌بودن Module با `tasks.project_id` (یافتهٔ `N-3`) و انتخاب Stage از پروژهٔ دیگر. Repository الگوی Denormalization کنترل‌شده را از قبل دارد (`tasks.contract_id` · `tasks.contractor_id`).

### پیامدها
- `§۶.۶` به یک قاعدهٔ صریح Service ارتقا می‌یابد: «Stage/Module انتخاب‌شده باید به همان Project تسک تعلق داشته باشد» — **بدون Constraint یا Trigger جدید**.
- `N-3` با همین تصمیم بسته می‌شود.
- ستون در `M-05` با `BIGINT NULLABLE FK → modules RESTRICT` باقی می‌ماند.
- خطر واگرایی داده آگاهانه پذیرفته و به Service + تست سپرده می‌شود.

### Schema متأثر
`tasks.module_id` (باقی می‌ماند) · `tasks.module_stage_id` (باقی می‌ماند)

### Migration متأثر
`M-05` — بدون تغییر ساختاری؛ فقط الزام Service مستند می‌شود

### وضعیت
- ACTIVE (قطعی)

---

## DEC-022 — اتمیک‌بودن عملیات Module (`H-3`)

### تاریخ
2026-09-22

### تصمیم مالک پروژه
ایجاد، حذف، Restore و Rebalance ماژول‌ها باید **اتمیک و تراکنشی** باشد:

```text
یک عملیات کسب‌وکاری → یک Transaction → تمام تغییرات Weight → اعتبارسنجی مجموع → Commit
```

- مجموع Weight ماژول‌های **Active** در **وضعیت نهایی** باید `100` باشد.
- ایجاد Module با Weight جزئی فقط اگر **کل Rebalance در همان Transaction** تکمیل شود مجاز است.
- **وضعیت میانی خارج از Transaction نباید قابل مشاهده یا Commit باشد.**
- **صفر Module فعال مجاز است.**

### زمینه
`H-3`: Trigger تعویق‌شدهٔ `SUM(modules.weight) = 100` عملاً ایجاد تدریجی Module را ناممکن می‌کرد — نخستین `INSERT` (‹count=1, total=40›) در `COMMIT` رد می‌شد.

### پیامدها
- `ModuleService::createModules(Project, definitions)` به‌صورت Bulk در یک تراکنش + ۹ Stage هر ماژول در همان تراکنش.
- `ModuleService::rebalance()` کل بازتوازن را در یک تراکنش اعمال می‌کند.
- هیچ مسیری نباید تراکنش را در وضعیت مجموعِ نامعتبر Commit کند (مستند در `§۶.۱`).

### Schema متأثر
`modules` — قاعدهٔ مرز-تراکنش؛ بدون تغییر ستون

### Migration متأثر
`M-01` — بدون تغییر ساختاری؛ الزام در لایهٔ Service

### وضعیت
- ACTIVE (قطعی)

---

## DEC-023 — دکترین قفل والد برای جلوگیری از Write Skew (`H-4`)

### تاریخ
2026-09-22

### تصمیم مالک پروژه
**قفل والد الزامی است.** هر عملیات مؤثر بر مجموعهٔ Weight یک Project باید ابتدا Parent Resource مناسب را Lock کند:

```text
BEGIN TRANSACTION
  Lock Parent Project / Weight Aggregate Owner
  Read current active modules
  Apply requested changes
  Validate SUM(weight)
  Write changes
COMMIT
```

- اتکای صرف به `Read Committed` / `Repeatable Read` **مجاز نیست**.
- **Source of Truth دوم** و **Snapshot غیرضروری** ساخته نشود.
- اکتفا به Application-Level Check **بدون Lock** مجاز نیست.
- انتخاب والد دیگر فقط پس از بررسی دقیق Repository و با مستندسازی دلیل مجاز است.

### زمینه
`H-4`: Trigger تعویق‌شده در `READ COMMITTED` تراکنش‌های همزمان را سریالایز نمی‌کند (Write Skew). والد از Repository استخراج شد:

| محدودهٔ تجمیع | والد قابل‌قفل |
|---|---|
| `SUM(modules.weight)` یک Project | `projects.id` |
| `SUM(module_stages.weight)` یک Module | `modules.id` |
| `SUM(approved_amount)` یک Stage | `module_stages.id` |

### پیامدها
- `lockForUpdate()` روی والد در **تمام** مسیرهای تغییردهندهٔ محدوده الزامی است (نه فقط `approve`).
- الگو از قبل در Repository موجود است (`WeightChangeRequestService:57,64`) — الگوی جدیدی ساخته نشد.
- `SERIALIZABLE` فقط به‌عنوان گزینهٔ آیندهٔ Jobهای Import انبوه باز می‌ماند.
- Trigger تجمعی (در صورت تأیید آینده) **Backstop** است، نه تضمین.

### Schema متأثر
بدون تغییر Schema — دکترین تراکنشی/Service

### Migration متأثر
`M-01`..`M-03` — بدون تغییر ساختاری

### وضعیت
- ACTIVE (قطعی — دکترین)

---

## DEC-024 — «صفر Module فعال» وضعیت معتبر است (`H-5`)

### تاریخ
2026-09-22

### تصمیم مالک پروژه
```text
Active Modules = 0    → VALID
Active Modules > 0    → SUM(active_modules.weight) MUST = 100
```

- Soft Delete آخرین Module **نباید** به‌دلیل صفر شدن Active Modules رد شود.
- Restore یک Module باید **دوباره** Invariant را بررسی کند.
- حذف/غیرفعال‌سازی Module فقط اگر **وضعیت نهایی معتبر** باشد مجاز است.
- مجموع Weight **فقط** روی Moduleهای Active محاسبه می‌شود.
- Moduleهای Soft Deleted در محاسبهٔ Weight دخالت **نمی‌کنند**.

### زمینه
`H-5`: چون شرط `deleted_at IS NULL` بود، Soft Delete همهٔ ماژول‌ها باعث `count = 0` و پذیرش Trigger می‌شد (حفرهٔ فرار). تفسیر «صفر مشروع» با `H-3` سازگار است (پروژه باید با صفر ماژول آغاز شود) و نیازمند ستون/وضعیت جدید نیست.

### پیامدها
- گارد `count > 0` در اعتبارسنجی مجموع **حفظ می‌شود**.
- Soft Delete و Restore در **همان تراکنش** بازتوازن می‌شوند.
- ستون/وضعیت جدیدی برای «پروژه‌ای که قبلاً ماژول داشته» ساخته نمی‌شود.
- **Soft Delete خودِ Project:** در V1.8 هیچ مسیر کدی ندارد و Invariant ماژول‌ها را **تعلیق** می‌کند (رفتار مستند شد، نه اجراشده).

### Schema متأثر
`modules.deleted_at` — بدون تغییر ستون؛ قاعدهٔ مجموعهٔ Active

### Migration متأثر
`M-01` — بدون تغییر ساختاری

### وضعیت
- ACTIVE (قطعی — قاعدهٔ کسب‌وکاری)

---

## DEC-025 — دکترین «Service در برابر Database» (`N-4`)

### تاریخ
2026-09-22

### تصمیم مالک پروژه
**Service Layer مالک Business Rule است:**
ایجاد Module · Rebalance · حذف/Restore · محاسبه و اعتبارسنجی Business Rule · Approval · Authorization · کنترل Workflow · Audit orchestration.

**Database Layer مالک Data Integrity است** — و فقط Integrityهایی را enforce می‌کند که:

- **deterministic** هستند؛
- **مستقل از UI** هستند؛
- با Constraint / Trigger **قابل اتکا** هستند؛
- **باعث ایجاد Business Workflow داخل Database نمی‌شوند**.

```text
درست:  Business Rule → Application Service → Transaction + Parent Lock → Database Integrity Constraint/Trigger
غلط:   Business Workflow → Database Trigger
```

### زمینه
`N-4`: `docs/10-database-design.md §۱` می‌گفت «قواعد کسب‌وکاری در لایه Application — نه در Database»، در حالی که سند Schema پنج تا هفت Trigger دیتابیسی پیشنهاد می‌کرد و Repository سابقهٔ **صفر Trigger** داشت. بدون تفکیک صریح، نویسندهٔ بعدی نمی‌داند کدام قاعده Service-only و کدام DB-enforced است — همان شکافی که `C-06`/`C-07` را ساخت.

### پیامدها
- جدول تفکیک «کدام قاعده DB-enforced و کدام Service-only» به `§۶.۸` سند Schema اضافه شد.
- `docs/10-database-design.md §۱` با این دکترین هم‌تراز شد.
- فقط Integrityهای تک‌ردیفی و deterministic به DB سپرده می‌شوند؛ قواعد تجمعی و cross-table در Service با قفل والد می‌مانند.

### Schema متأثر
بدون تغییر Schema — دکترین و طبقه‌بندی قواعد

### Migration متأثر
`M-08` — دامنهٔ Trigger بر همین مبنا محدود شد

### وضعیت
- ACTIVE (قطعی — دکترین)

---

## DEC-026 — اصلاح سقف تأیید تجمعی (`H-1`)

### تاریخ
2026-09-22

### تصمیم مالک پروژه
راهکار قبلی **حذف شود**:

```sql
SELECT SUM(...) FOR UPDATE      -- ❌ نامعتبر در PostgreSQL
```

راهبرد طراحی باید این باشد:

```text
Lock Parent Stage → Read SUM(approved_amount) → Validate ceiling → Insert/Update Approval
```

- **هیچ Source of Truth دوم** برای Approved Weight ساخته نشود.
- `approved_weight` همچنان **محاسباتی** است: `SUM(stage_progress_approvals.approved_amount)`.
- `remaining_weight = allocated_weight − approved_weight`.

### زمینه
`H-1`: کوئری `SELECT COALESCE(SUM(approved_amount),0) … FOR UPDATE` در PostgreSQL خطای `FOR UPDATE is not allowed with aggregate functions` می‌دهد؛ کل راهبرد سقف تجمعی و Trigger ظرفیت بر همین کوئری استوار بود. گزینه‌های جایگزین بررسی و رد شدند: `pg_advisory_xact_lock` (جانشین، نه اصلی) · `SERIALIZABLE` (پیچیدگی Retry) · ردیف حالت تجمیعی (Source of Truth دوم — ممنوع).

### پیامدها
- `§۶.۵` سند Schema بازنویسی شد: **قفل ردیف والد `module_stages`** یکتا مرجع ایمنی است؛ تجمیع **بدون** `FOR UPDATE`.
- قفل والد در **همهٔ** مسیرهای تغییردهندهٔ `stage_progress_approvals` الزامی است: `propose` · `adjust` · `approve` · `reject` · `supersede`.
- Trigger ظرفیت تجمعی از `M-08` خارج شد (`DEC-020`)؛ در صورت ساخت آینده فقط **Backstop** است.
- هزینه: یک قفل ردیف به‌ازای هر عملیات تأیید — ترافیک تأیید کم است.

### Schema متأثر
`stage_progress_approvals` — بدون تغییر ستون؛ قاعدهٔ سقف اصلاح شد

### Migration متأثر
`M-03` — بدون تغییر ساختاری · حذف Trigger ظرفیت از `M-08`

### وضعیت
- ACTIVE (قطعی — اصلاح طراحی تأییدشده)

---

## DEC-027 — معناشناسی `supersede` و تعریف «Active Approval» (`N-1`)

### تاریخ
2026-09-22

### تصمیم مالک پروژه
تناقض State Transition اصلاح شود؛ به‌ویژه:

- گذار `approved → superseded` **نباید با Immutable Audit/History قاطی شود**.
- سقف Approval **نباید یک Approval قبلی را دوباره در `SUM` محاسبه کند**.

پیش از هر پیشنهاد Schema: (۱) State Machine واقعی بررسی شود · (۲) Transitionهای موجود استخراج شوند · (۳) **تعریف دقیق Active Approval** مشخص شود · (۴) فقط Approvalهای معتبر در سقف تجمعی لحاظ شوند · (۵) History حذف نشود · (۶) **هیچ State جدیدی بدون ضرورت اضافه نشود**.

### زمینه
`N-1`: سه تعارض هم‌زمان — (الف) `trg_spa_immutable` هر `UPDATE` روی رکورد غیر-`pending` را رد می‌کرد، پس گذار `approved → superseded` غیرقابل اجرا بود؛ (ب) اگر رکورد قبلی `approved` می‌ماند، سقف هر دو رکورد را جمع می‌زد → دوباره‌شماری؛ (ج) درج مستقیم با `status='superseded'` معنای رویداد را خراب می‌کرد.

### پیامدها (راه‌حل اعمال‌شده)
- **`superseded` یک وضعیت ذخیره‌شده نیست؛ یک وضعیت مشتق است.** دامنهٔ ذخیره‌شده: `pending | approved | rejected`.
- تعریف **Active Approval:**
  ```text
  رکوردی با status = 'approved' که هیچ رکورد دیگری آن را supersede نکرده باشد:
  NOT EXISTS (SELECT 1 FROM stage_progress_approvals s WHERE s.supersedes_approval_id = t.id)
  ```
- سقف تجمعی = `SUM(approved_amount)` روی **Active Approvalهای همان Stage**.
- **هیچ `UPDATE`ی برای ابطال لازم نیست** → `trg_spa_immutable` کاملاً سخت می‌ماند و History هرگز بازنویسی نمی‌شود.
- زنجیرهٔ اصلاح (A ← B ← C) درست جمع می‌شود: فقط **برگ** (C) شمرده می‌شود.
- State جدیدی اضافه نشد.
- **یادداشت فنی باز (تصمیم مالک نیست):** جلوگیری از supersede دوبارهٔ یک رکورد توسط دو رکورد مختلف — که دو «برگ» می‌سازد — نیازمند `UNIQUE` روی `supersedes_approval_id` است. **اعمال نشد** (خارج از تصمیمات این مرحله) و به‌عنوان یادداشت فنی در گزارش Reconciliation ثبت شد.

### Schema متأثر
`stage_progress_approvals.status` (دامنهٔ ذخیره‌شده محدود شد) · `supersedes_approval_id` (بدون تغییر ساختار)

### Migration متأثر
`M-03` (اصلاح دامنهٔ وضعیت و `chk_spa_decided_consistency`) · `M-08` (Trigger تغییرناپذیری بدون استثنا)

### وضعیت
- ACTIVE (قطعی)

---

## DEC-028 — شاخه‌بندی `TG_OP` در Trigger Function (`N-2`)

### تاریخ
2026-09-22

### تصمیم مالک پروژه
الگوی زیر **بدون توجه به Operation** استفاده نشود:

```sql
COALESCE(NEW.column, OLD.column)      -- ❌ در INSERT/DELETE خطای زمان اجرا
```

Trigger Function باید برای هر operation **صریح** طراحی شود:

```text
INSERT → NEW        UPDATE → NEW / OLD as required        DELETE → OLD
```

اگر Trigger هنوز صرفاً در طراحی است، **فقط طراحی صحیح مستند شود و Migration نوشته نشود.**

### زمینه
`N-2`: بدنهٔ `assert_project_module_weight_sum()` با `COALESCE(NEW.project_id, OLD.project_id)` نوشته شده بود. در Trigger ردیفی، `NEW` برای `DELETE` و `OLD` برای `INSERT` تعریف‌نشده‌اند → خطای `record "new"/"old" is not assigned yet`. تحلیل **ایستا** بود (DDL ممنوع) و با اجرا تأیید نشد.

### پیامدها
- بدنهٔ اصلاح‌شدهٔ توابع تجمعی **فقط به‌عنوان طراحی موکول (DEFERRED)** مستند شد — با شاخه‌بندی صریح `TG_OP`.
- چون Triggerهای تجمعی طبق `DEC-020` از `M-08` خارج شدند، این اصلاح امروز **فقط روی کاغذ** اعمال می‌شود.
- `M-08` (Triggerهای تک‌ردیفی) از این نقص مصون است: آن‌ها فقط `BEFORE UPDATE`/`BEFORE DELETE` با دسترسی به `OLD` هستند.

### Schema متأثر
بدون تغییر — مستندات طراحی توابع (موکول)

### Migration متأثر
`M-08` — سه Trigger تجمعی حذف شدند؛ طراحی اصلاح‌شده موکول به تصمیم آینده

### وضعیت
- ACTIVE (قطعی — نقص فنی پذیرفته و اصلاح مستند شد)

---

## DEC-029 — Trigger حفاظت از History (`OQ-31` = **RESOLVED**)

### تاریخ
2026-09-22

### تصمیم مالک پروژه
توصیهٔ قبلی **تأیید می‌شود**: Trigger جلوگیری از حذف History/Audit **در صورت نیاز** می‌تواند به‌عنوان **Integrity Trigger تک‌ردیفی** پیاده‌سازی شود، با قیود:

- **Audit History نباید قابل حذف شدن باشد.**
- Trigger **نباید Business Workflow** ایجاد کند.
- رفتار آن باید با **FK** و **Model-Level protection** هماهنگ باشد.

### زمینه
`OQ-31`: مدل Eloquent (`deleting → false`) `DELETE` سطح-SQL را نمی‌گیرد و `DB::table()->delete()` می‌تواند رکورد تاریخی را حذف کند. این محدودیت از قبل در `TMS_PROJECT_TRACKER.md §7` مستند شده بود. توابع حفاظتی تک‌ردیفی‌اند و حالت میانی مشروع ندارند، پس در دستهٔ مجاز `DEC-020` قرار می‌گیرند.

### پیامدها
- **`OQ-31` بسته شد** و با `OQ-22` (که همان موضوع را در فهرست 🟢 تکرار می‌کرد) **ادغام** می‌شود → پرسش تکراری باقی نمی‌ماند.
- `M-08` سه Trigger تک‌ردیفی خواهد داشت: قفل وزن Stage · تغییرناپذیری رکورد تصمیم‌گرفته · حفاظت از حذف History.
- دامنهٔ حفاظت: `stage_progress_approvals` و `activity_logs` (Audit History) — یک تابع و دو Binding.
- هماهنگی سه‌لایه حفظ می‌شود: FK `RESTRICT` + مدل Immutable + Trigger دیتابیس.

### Schema متأثر
`stage_progress_approvals` · `activity_logs` — فقط Trigger؛ صفر تغییر ستون

### Migration متأثر
`M-08` (افزودن Trigger حفاظت از حذف)

### وضعیت
- ACTIVE (قطعی — RESOLVED)

---

## DEC-030 — نگاشت `$metadata` در Audit (`OQ-25` = **RESOLVED**)

### تاریخ
2026-09-22

### تصمیم مالک پروژه
ساختار Audit موجود **حفظ می‌شود**. نگاشت:

```text
metadata['reason']  →  activity_logs.reason
سایر Metadata       →  activity_logs.new_values['metadata']
```

**هیچ ستون جدیدی برای Reason ایجاد نمی‌شود.**

### زمینه
`OQ-25`: پارامتر `$metadata` در `AuditServiceInterface::log()` وجود دارد اما `activity_logs` ستون metadata ندارد. گزینهٔ افزودن ستون `metadata JSONB` نیازمند Migration و تغییر Schema بود؛ گزینهٔ نگاشت، صفر تغییر Schema دارد و با ستون موجود `reason TEXT` — که ۵ فراخوانی فعلی فقط همان را می‌فرستند — هم‌خوان است.

### پیامدها
- **`OQ-25` بسته شد.**
- `DatabaseAuditService` (گام صفر V1.8a) این نگاشت را پیاده می‌کند: صفر Migration اضافه، صفر ستون جدید.
- **یادداشت پیاده‌سازی (بدون تصمیم جدید):** ستون‌های موجود `ip_address` و `user_agent` امروز بی‌نویسنده‌اند؛ `DatabaseAuditService` می‌تواند آن‌ها را نیز پر کند — استفادهٔ کامل از Schema موجود، نه تغییر آن.

### Schema متأثر
`activity_logs` — بدون تغییر؛ فقط قرارداد نگاشت

### Migration متأثر
هیچ — صفر تغییر Schema

### وضعیت
- ACTIVE (قطعی — RESOLVED)

---

## DEC-031 — `modules.code` و Unique مرکب (`OQ-02` = **RESOLVED**)

### تاریخ
2026-09-22

### تصمیم مالک پروژه
طراحی فعلی **Unique Composite حفظ می‌شود**. **Partial Unique Index اضافه نمی‌شود.** جملهٔ گمراه‌کنندهٔ مربوط به این موضوع در Documentation **اصلاح می‌شود**.

### زمینه
`OQ-02`: آیا چند ماژول بدون `code` مجاز باشند؟ واقعیت فنی: `UNIQUE (project_id, code)` در PostgreSQL از قبل یعنی «codeهای غیرتهی در هر پروژه یکتا باشند»، چون `NULL`ها در UNIQUE متمایز شمرده می‌شوند. پس Partial Unique Index روی `code IS NOT NULL` **هیچ معنای جدیدی اضافه نمی‌کند**. جملهٔ `§۴.۱` سند Schema («اگر این رفتار نامطلوب است، باید Partial Unique Index استفاده شود») از نظر فنی گمراه‌کننده بود.

### پیامدها
- **`OQ-02` بسته شد.**
- `U-01` = `UNIQUE (project_id, code)` بدون تغییر در `M-01`.
- متن `§۴.۱` اصلاح شد: «رفتار فعلی UNIQUE دقیقاً همان اثر Partial Unique روی codeهای غیرتهی را دارد؛ اگر روزی *الزام* به `code` لازم شود باید به `NOT NULL` ارتقا یابد — تصمیم جداگانه.»
- هیچ قاعدهٔ کسب‌وکاری جدیدی ساخته نشد.

### Schema متأثر
`modules` — `U-01` بدون تغییر

### Migration متأثر
`M-01` — بدون تغییر

### وضعیت
- ACTIVE (قطعی — RESOLVED)

---

## DEC-032 — رویداد Audit `document_uploaded` (`H-6`)

### تاریخ
2026-09-22

### تصمیم مالک پروژه
`document_uploaded` **نباید به‌صورت Observer خودکار** اضافه شود. Audit باید در **Service** صریحاً انجام شود:

```text
DocumentService::uploadDocument() → Persist Document → Audit document_uploaded
```

**از Audit دوبل جلوگیری شود.**

### زمینه
`H-6`: بازخوانی مستقیم کد نشان داد `uploadDocument()` **هیچ فراخوانی Audit ندارد** (برخلاف `deleteDocument()`) و `grep "document_uploaded"` در `app/` صفر نتیجه دارد؛ سند Schema آن را در فهرست «موجود — باید فعال شود» آورده بود که نادرست بود. Observer خودکار رد شد چون `document_deleted` از قبل صریحاً لاگ می‌شود → **لاگ دوبل** — و زمینهٔ دامنه (entity/actor/reason) ناقص می‌ماند.

### پیامدها
- `document_uploaded` از فهرست «موجود (فعال شود)» به فهرست «**باید ساخته شود**» منتقل شد (§۸.۳ سند Schema).
- فراخوانی داخل **همان `DB::transaction`** درج سند انجام می‌شود؛ شکست Audit ⇒ rollback درج، و فایل توسط `catch` موجود پاک می‌شود.
- کار پیاده‌سازی در فاز V1.8a — بدون تصمیم کسب‌وکاری جدید.
- هیچ Observer/Event/Listener خودکار برای Audit ساخته نمی‌شود.

### Schema متأثر
بدون تغییر (`activity_logs` کافی است)

### Migration متأثر
هیچ — تغییر کد در `DocumentService` (V1.8a)، نه Migration

### وضعیت
- ACTIVE (قطعی)

---

## DEC-033 — تثبیت طبقه‌بندی فیلدهای Legacy

### تاریخ
2026-09-22

### تصمیم مالک پروژه
طبقه‌بندی فعلی **حفظ می‌شود**:

| مورد | طبقه | در V1.8 |
|---|---|---|
| `tasks.weight` | **DEPRECATED** | حذف **نمی‌شود** (فقط `DROP NOT NULL` — `M-07`) |
| `wbs_phases.weight` | **LEGACY** | صفر مصرف‌کنندهٔ فعلی؛ حذف **نمی‌شود** مگر با تصمیم صریح بعدی |
| `lock_weight` | **DEPRECATED** | موضوعش مربوط به Task Weight قبلی بود و با مدل جدید Weight **منسوخ** است |
| `weight_change_requests` | **LEGACY** | تا زمانی که Migration نهایی نشده **حذف نمی‌شود** |

### زمینه
طبقه‌بندی در `DEC-014` و `§۱۰` سند Schema ثبت شده بود و بازبینی Migration Review مستقلاً آن را بازتولید و تأیید کرد (`I-1`..`I-4`).

### پیامدها
- هیچ `DROP COLUMN` / `DROP TABLE` در V1.8 — بدون استثنا.
- `lock_weight` یک مصرف‌کنندهٔ UI واقعی دارد (`settings/index.blade.php` + `SettingController:25` + `SystemSettingSeeder:32`)؛ علامت‌زدن «منسوخ» یک کار واقعی روی Blade در V1.8b است، نه «صفر کار».
- بازنشستگی نهایی فقط با تصمیم صریح کتبی بعدی (`AGENTS.md` Rule 3).

### Schema متأثر
`tasks.weight` · `wbs_phases.weight` · `system_settings.lock_weight` · `weight_change_requests`

### Migration متأثر
`M-07` (فقط `DROP NOT NULL`) · هیچ Migration حذفی

### وضعیت
- ACTIVE (قطعی — بازتأیید طبقه‌بندی)

---

## DEC-034 — مدل نهایی Business Truth: Weight · Stage Approval · WBS Completion

### تاریخ
2026-09-22

### تصمیم مالک پروژه

```text
Project
   ├── Modules         →  Module Weight
   └── WBS Phases      →  Completion / Checklist
```

```text
Module
   └── Module Stages   →  Analysis 15% · Design 5% · Coding 35% · Functional Test 3%
                          Penetration Test 5% · Training 7% · Pilot 10%
                          Production 5% · Support 15%   = 100%
```

**قواعد ثابت‌شده:**

- **WBS Phase Weight وجود ندارد.** WBS Phase برای: محدوده · بازهٔ تاریخی · Checklist · تکمیل/عدم‌تکمیل · نظر ناظر.
- **Module Stage** برای: Weight · Proposed Amount · Approved Amount · Approval History · Progress Calculation.
- **Task** برای: اجرای کار · Assignment · Submission · Review · Approval/Rejection · SLA. **Task هیچ Progress Weight مستقلی ندارد.**
- **Stage Approval تجمعی است:** Approval #1=5% + #2=7% + #3=3% → Approved=15% · Remaining=0%. **تأیید جزئی مجاز است:** `proposed=10` · `approved=5` → Approved=5% · Remaining=10%.
- Supervisor باید بتواند: مقدار پیشنهادی را ببیند · مقدار تأییدشده را تغییر دهد · تأیید کند · رد کند · **تأییدهای متعدد برای یک Stage** ثبت کند. **History هرگز overwrite نمی‌شود.**
- **WBS Phase Completion:** `Phase → Checklist Items → Supervisor Review → Completed/Incomplete`. **هیچ Weight محاسباتی از Checklist استخراج نشود** و **هیچ Progress Percentage برای Task ایجاد نشود**. Phase صرفاً می‌گوید خروجی‌های مورد انتظار از دید Supervisor نهایی شده‌اند یا نه.

### زمینه
تثبیت نهایی `BD-01`..`BD-07` (`DEC-009`/`DEC-013`) و پاسخ به بازبینی مدل Weight در Migration Review. تنها نکتهٔ **جدید** نسبت به قبل: دو قاعدهٔ منفی صریح — «Weight محاسباتی از Checklist استخراج نشود» و «Progress Percentage برای Task ایجاد نشود» — که از بازگشت مدل باطل‌شده جلوگیری می‌کنند.

### پیامدها
- هیچ ستون وزن/درصدی روی `tasks` یا `wbs_phase_checklist_items` اضافه نمی‌شود.
- `wbs_phases.weight` و `tasks.weight` طبق `DEC-033` صرفاً Legacy/Deprecated می‌مانند.
- محاسبهٔ پیشرفت فقط از `SUM(approved_amount)` روی Active Approvalهای Stageها می‌آید.
- Checklist فقط `complete/incomplete` + «چه کسی/چه زمانی» نگه می‌دارد.

### Schema متأثر
`modules` · `module_stages` · `stage_progress_approvals` · `wbs_phase_checklist_items` · `tasks` (تصریح منفی) · `wbs_phases` (تصریح منفی)

### Migration متأثر
`M-01`..`M-06` — بدون تغییر؛ این تصمیم مدل را تثبیت می‌کند و ساختاری اضافه نمی‌کند

### وضعیت
- ACTIVE (قطعی — Business Truth نهایی)

---

## DEC-035 — وضعیت نهایی دروازه پس از Design Reconciliation

### تاریخ
2026-09-22

### تصمیم مالک پروژه
پس از بستن تمام تصمیمات کسب‌وکاری و اعمال اصلاحات طراحی:

```text
READY FOR MIGRATION IMPLEMENTATION REVIEW
```

**صراحت:** این عبارت با **`READY TO EXECUTE MIGRATIONS`** اشتباه نشود. Migration Implementation (نوشتن و اجرای فایل‌های Migration) **فقط در مرحلهٔ بعد** مجاز است. `OQ-30 = D` در همین مرحله به معنای نوشتن Migration نیست.

### زمینه
آخرین وضعیت ثبت‌شده `BLOCKED — OWNER DECISION REQUIRED` بود. با پاسخ مالک پروژه به هر ۶ مورد `OWNER DECISION REQUIRED` (`OQ-30` · `OQ-24` · `H-3` · `H-4` · `H-5` · `N-4`) و اعمال ۹ توصیهٔ فنی + ۲ مورد مستنداتی، هیچ پرسش مسدودکنندهٔ کسب‌وکاری باقی نمانده است.

### پیامدها
- هیچ `BLOCKER` باز نیست؛ `B-1` (پنج تصمیم ساختاری) بسته شد.
- **Environment = PASS** (PG 18.6 · `tms` و `tms_testing` موجود · اتصال Laravel تأییدشده · ۲۳/۲۳ Migration).
- **Tests = PARTIAL** — اجرای واقعی Unit: ۱۶ passed / ۱ deprecated / ۵۱ assertions؛ `Feature` اجرا نشد چون `RefreshDatabase` روی PostgreSQL معادل `migrate:fresh` است و مجوز جداگانه می‌خواهد.
- بیس «۱۱۴ تست / ۳۳۴ assertion» **همچنان UNVERIFIED** — نه PASS و نه FAIL.
- تفکیک مراحل: Migration Implementation Review → Migration Design (نوشتن ۹ فایل) → Migration Implementation (اجرا) — هر کدام مجوز جداگانه.
- جزئیات کامل: `docs/V1.8_FINAL_DESIGN_RECONCILIATION.md`.

---

## وضعیت پس از فاز V1.8 Migration Implementation — ۱۴۰۵/۰۶/۳۱ (2026-09-22)

> **این بخش یک Decision جدید ثبت نمی‌کند.** صرفاً وضعیت اجرای تصمیمات موجود را ثبت می‌کند، تا هیچ Registry موازی ساخته نشود.

### ✅ تصمیمات موجود که واقعاً پیاده‌سازی و اثبات شدند

| Decision | ادعا | وضعیت واقعی (با اجرا) |
|---|---|---|
| `DEC-016` | Backfill `task_type = 'development'` | ✅ `M-05` اعمال شد · درج Task بدون `task_type` مقدار `development` گرفت و `weight IS NULL` بود |
| `DEC-018` | `down()` مهاجرت `M-07` آگاهانه `throw` می‌کند | ✅ پیاده شد · `M-07` در **Batch مستقل** (`Batch 2`) اجرا شد و دروازهٔ Rollback هشت مهاجرت دیگر را نمی‌بندد |
| `DEC-020` | ۳ Trigger تک‌ردیفی ساخته شوند؛ ۳ Trigger تجمعی DEFERRED | ✅ (واقعیت: **۴** Trigger روی **۳** Function — `trg_spa_no_delete` و `trg_activity_logs_no_delete` یک تابع مشترک دارند) · Triggerهای تجمعی ساخته **نشدند** |
| `DEC-024` | صفر ماژول فعال معتبر است | ✅ در Service پیاده شد + تست |
| `DEC-025` | Service مالک Business Rule · DB مالک Integrity تک‌ردیفی | ✅ **اثبات تجربی:** درج ماژولی که مجموع را از ۱۰۰ می‌گذراند **توسط DB رد نشد** (طبق تصمیم) و CHECKهای تک‌ردیفی فعال‌اند |
| `DEC-026` | حذف `SUM(...) FOR UPDATE` → قفل والد Stage | ✅ در `StageProgressApprovalService` پیاده شد |
| `DEC-027` | `superseded` وضعیت مشتق است | ✅ **اثبات تجربی:** پس از supersede، ردیف قدیمی **بدون هیچ `UPDATE`** دست‌نخورده ماند و از تجمیع خارج شد (`SUM` صفر شد، صفر دوباره‌شماری) |
| `DEC-029` | Trigger حفاظت از حذف History/Audit | ✅ `DELETE` از `stage_progress_approvals` و `activity_logs` **مسدود شد** |
| `DEC-030` | نگاشت `$metadata` | ✅ `reason → reason` · باقی → `new_values.metadata` — بدون ستون جدید |
| `DEC-031` | Unique مرکب روی `modules.code` کافی است | ✅ **اثبات تجربی:** دو ماژول با `code = NULL` هر دو درج شدند |
| `DEC-032` | `document_uploaded` صریح در Service · بدون Observer | ✅ فراخوانی در همان `DB::transaction` + تست |
| `DEC-011` | `DatabaseAuditService` گام صفر | ✅ ساخته و Bind شد |

### ✅ مانع محیطی `ENV-1` — رفع شد

| ID | مانع | وضعیت |
|---|---|---|
| `ENV-1` | `tms_testing` رمزگذاری **WIN1252** داشت (نه UTF8) و قادر به ذخیرهٔ متن فارسی نبود ⇒ `M-09` و کل Test Suite مسدود | ✅ **رفع شد** — با **مجوز صریح مالک پروژه**، `tms_testing` با `TEMPLATE template0 ENCODING 'UTF8' LC_COLLATE 'C' LC_CTYPE 'C'` بازسازی شد (دیتابیس تست و خالی بود؛ صفر داده از دست رفت). سپس **۳۲/۳۲** مهاجرت اجرا شد و **کل Test Suite سبز شد**: `175 passed · 2 deprecated · 504 assertions · 0 failed`. |

### 🔴 دو نقص منطقی که خود تست‌ها کشف کردند (رفع شدند)

| ID | نقص | رفع |
|---|---|---|
| `BUG-1` | `ModuleStageService::rebalance()` مجموع **map** را با ۱۰۰ مقایسه می‌کرد نه مجموع **نهایی** ماژول ⇒ جابه‌جایی وزن بین دو مرحلهٔ نُه‌گانه غیرممکن بود | مجموع نهایی از کل مجموعهٔ مرحله‌ها محاسبه می‌شود؛ map به مجموعهٔ **جزئی** تبدیل شد |
| `BUG-2` | ردیف‌های **superseded** از «یک pending» و از مسیر تصمیم‌گیری استثنا نمی‌شدند ⇒ قفل همیشگی Stage و امکان تصمیم روی ردیف منقضی | `pendingQuery()` با `NOT EXISTS` فیلتر می‌شود + `assertNotSuperseded()` در `approve`/`reject` |

### 🟡 یادداشت‌های فنی باز (بدون نیاز به تصمیم کسب‌وکاری)

`T-1` (`UNIQUE` روی `supersedes_approval_id`) · `T-2` (حفظ/حذف `DEFAULT 'development'` روی `task_type` — **آگاهانه حفظ شد**) · `T-3`..`T-7`.

### وضعیت دروازه

```text
READY FOR PRODUCTION MIGRATION REVIEW
```

> ⚠️ این دروازه **مجوز اجرا روی `tms` نیست** — مرحلهٔ بعد فقط بازبینی است (§۳۴).

مرجع: `docs/V1.8_MIGRATION_BASELINE_RECONCILIATION.md`

### Schema متأثر
بدون تغییر — وضعیت دروازه

### Migration متأثر
هیچ Migrationی در این مرحله نوشته یا اجرا نشد

### وضعیت
- ACTIVE (قطعی)

---

## 📌 رکورد وضعیت — ۱۴۰۵/۰۶/۳۱ (2026-09-22): Production Migration اجرا شد

> ⚠️ **این یک تصمیم کسب‌وکاری جدید نیست.** رکورد وضعیت اجرا + دو یادداشت فنی باقی‌مانده است.
> هیچ قاعدهٔ کسب‌وکاری در این فاز ابداع یا ثبت نشد.

### Environment

```text
PostgreSQL ......... 18.6
Laravel framework .. v13.31.0 (composer.lock)
PHP ................ 8.5.6
Database ........... tms (UTF8 / C)
```

### Migration متأثر

۹ مهاجرت V1.8 روی `tms` اجرا شدند:

- `M-07` (`2026_09_22_100001`) → **Batch 2** (با `--path`، جدا)
- هشت مهاجرت دیگر → **Batch 3**

**توپولوژی نهایی:** `{1:23 (Baseline), 2:1 (M-07), 3:8}`

۲۳ مهاجرت Baseline **صفر تغییر** کردند (`git diff -- database/migrations` خالی).

### Schema متأثر

```text
Before:  23 migrations · 31 tables · 0 triggers · 0 functions · 4 settings
After:   32 migrations · 35 tables · 4 triggers · 3 functions · 5 settings
```

جدولهای جدید: `modules` · `module_stages` · `stage_progress_approvals` · `wbs_phase_checklist_items`

### Data

**صفر ردیف دادهٔ موجود حذف، بازنویسی یا دگرگون شد.** فقط دو تغییر مورد انتظار:

```text
migrations ......... 23 → 32   (+9)
system_settings .... 4  → 5   (+1: `progress_approval_mode` — طبق طراحی §۴.۱۱ و `BD-15` · `M-09`)
```

پشتیبان پیش از تغییر: `storage/app/backups/tms_pre_v18_20260922_113350.dump` (gitignored).

### دروازه

```text
PRODUCTION MIGRATION SUCCESSFUL
```

### دو یادداشت فنی که همچنان تصمیم می‌خواهند

| ID | وضعیت این فاز | تصمیم مورد نیاز از مالک |
|---|---|---|
| `T-1` | `ACCEPTED AS-IS` · برچسب **`RECOMMENDATION — NOT APPROVED`** | افزودن `UNIQUE(supersedes_approval_id)` در یک Master جدید + مهاجرت `M-10` (Batch مستقل) |
| `T-2` | **`RESOLVED BY EXISTING DECISION` (`DEC-016`)** — Default حذف نشد | اجباری‌کردن `task_type` در لایهٔ FormRequest/Service (گزینهٔ A) |

`T-3`..`T-7` بررسی و بسته **نشده‌اند** و باز می‌مانند.

### مرجع

`docs/V1.8_PRODUCTION_MIGRATION_REPORT.md`

### وضعیت
- ACTIVE (رکورد اجرا — بدون تصمیم جدید)

---

## 📌 رکورد وضعیت — ۱۴۰۵/۰۶/۳۱ (2026-09-22): T-1 Resolution (اصلاح سطح سرویس)

> ⚠️ **این یک تصمیم کسب‌وکاری جدید نیست.** رکورد اجرا + یافتهٔ اثبات‌شده است.
> هیچ قاعدهٔ کسب‌وکاری جدیدی ابداع یا ثبت نشد.

### تصمیم مالک (تأییدشده در این جلسه)

| مورد | تصمیم مالک |
|---|---|
| `T-1` | **اصلاح حداقلی سطح سرویس + تست رگرسیون** (نه UNIQUE دیتابیس · نه فقط گزارش) |
| `R-1` (`tasks.weight` در گزارش‌ها) | **فقط گزارش شود** — خارج از محدودهٔ این فاز |

### یافتهٔ اثبات‌شده (با اجرای واقعی روی PostgreSQL 18.6)

بدون `UNIQUE(supersedes_approval_id)` **هیچ لایه‌ای** invariant «حداکثر یک جانشین» را تضمین نمی‌کرد:

```text
مسیر Database  →  دو جانشین برای یک ردیف، درج شدند
مسیر Service   →  مادامی که جانشین اول pending بود مسدود می‌شد،
                     اما پس از تصمیم جانشین اول  ⇒  مجاز  ⇒  fork
```

نمونهٔ عددی قطعی (که سقف ظرفیت آن را نمی‌گرفت):

```text
A = 5 (approved) → supersede → B = 7 (approved)  ⇒ approvedWeight = 7   ✅
                            → supersede دوبارهٔ A → C = 3
                            ⇒ approvedWeight = 10  ❌  (باید 7 می‌ماند · سقف 15)
```

### اصلاح انجام‌شده

`app/Domain/Services/StageProgressApprovalService.php` — دو خط، از متد **موجود** `assertNotSuperseded()`:

```text
supersede()  →  assertNotSuperseded($source)    (اصلاح T-1)
adjust()     →  assertNotSuperseded($row)       (اصلاح T-1b — نقص هم‌رده)
```

**صفر تغییر Schema · صفر Migration جدید · صفر تصمیم جدید.** هر دو تغییر با Stage 12 (implementation correction) سازگارند: رفتار فعلی با `DEC-027` («History هرگز بازنویسی نمی‌شود») و §۴.۳ سند طراحی مغایرت داشت.

### Schema متأثر
**بدون تغییر.** هیچ `UNIQUE`ی اضافه نشد (طبق `ACCEPTED AS-IS`).

### Migration متأثر
هیچ. `New Migration = 0` · `Existing Migration Modified = 0`.

### Data متأثر
هیچ. `tms` بی‌ت‌به‌بیت دست‌نخورده (۹ سنجه، صفر تغییر). تست‌ها منحصراً روی `tms_testing`.

### تست
```text
Full suite ...... 191 passed · 2 deprecated · 559 assertions · 0 failed   (از 175 → 191)
V1.8 suite ...... 79 passed                                              (از 63 → 79)
StageProgressApprovalSupersedeChainTest (جدید) ... 16 passed · 55 assertions
```

### یافته‌های گزارش‌شده (اصلاح نشده — نیازمند تصمیم مالک)

| ID | یافته |
|---|---|
| `R-1` | `ReportController` هنوز `SUM(tasks.weight)` را گزارش و CSV می‌کند، در حالی که Schema آن را بازنشسته و NULL-پذیر کرده |
| `R-2` | `task_type` هیچ نویسنده‌ای در کد ندارد ⇒ تسک Support هم همیشه `development` ذخیره می‌شود (نیمهٔ باقی‌ماندهٔ `T-2`) |
| `R-3` | تعریف «Active/Superseded» در ۵ نقطه تکرار شده — سازگار ولی شکننده · **`RECOMMENDATION — NOT APPROVED`** |

### مرجع
`docs/V1.8_T1_POST_MIGRATION_RECONCILIATION.md`

### دروازه
```text
READY FOR V1.8 POST-MIGRATION CODE HARDENING
```

### وضعیت
- ACTIVE (رکورد اجرا — بدون تصمیم جدید)

---

## DEC-036 — معناشناسی `tasks.weight = NULL` در گزارش‌ها و سیاست وزن در Create Task

### تاریخ
2026-09-22

### تصمیم مالک پروژه (R-1)

| سؤال | انتخاب مالک |
|---|---|
| معنای `weight = NULL` در گزارش‌ها (`SUM` · درصد · CSV) | **R1-D — NULL در مسیر عادی ممنوع** |
| سیاست وزن در فرم Create Task | **R1-F1 — weight اجباری می‌ماند** |

### محتوا

- `tasks.weight` در Schema **NULL-پذیر می‌ماند** (طبق `M-07` — بدون تغییر). NULL فقط برای سناریوهای legacy / future import / internal مجاز است.
- **مسیر رسمی Create Task هرگز NULL تولید نمی‌کند:** `StoreTaskRequest` وزن را `required` نگه می‌دارد (R1-F1).
- گزینه‌های `R1-A` (NULL=صفر) · `R1-B` (حذف از محاسبه) · `R1-C` (Incomplete) **انتخاب نشدند** — رفتار `ReportController` (SUM و CSV) **تغییر نمی‌کند**؛ صفر کد گزارشی.
- این تصمیم **مدل Progress جدیدی ایجاد نمی‌کند** و با `DEC-034` («Task هیچ Progress Weight مستقلی ندارد») سازگار است — `tasks.weight` همچنان Legacy/Deprecated طبق `DEC-033`.

### پیامدها
- هیچ `SUM` یا خروجی گزارشی عوض نمی‌شود؛ معیار گزارش همان رفتار قبلی را دارد چون مسیر عادی دیگر NULL تولید نمی‌کند.
- اگر در آینده مسیر Import/داخلی وزن NULL تولید کند، مرجع معتبر این سند است: NULL «ممنوع در مسیر عادی» است، نه «صفر» و نه «incomplete».
- تست نگهبان: `TaskTypeAndWeightPolicyTest` — وزنِ اجباری + وزنِ غیرNULL در مسیر HTTP.

### Schema متأثر
**هیچ.** `tasks.weight` nullable باقی می‌ماند (M-07 دست‌نخورده).

### Migration متأثر
**هیچ.** `New Migration = 0` · `Existing Migration Modified = 0`.

### وضعیت
- ACTIVE (قطعی)

---

## DEC-037 — `task_type` به‌عنوان ورودی اجباری مسیر Create Task (بستن نیمهٔ باقی‌ماندهٔ T-2)

### تاریخ
2026-09-22

### تصمیم مالک پروژه (R-2 / T-2)

| سؤال | انتخاب مالک |
|---|---|
| سیاست ورودی `task_type` | **T-2-A — Required در FormRequest** |
| سیاست UI | **T-2-UI-A — فیلد «نوع تسک» در فرم Create Task** |

### محتوا

- `task_type` ورودی **اجباری و صریح** Create Task است و از ورودی معتبر Domain دریافت می‌شود — **بدون silent fallback** به `DEFAULT 'development'`.
- Validation از **Enum موجود `App\Domain\Enums\TaskType`** (`development` · `support`) با `Illuminate\Validation\Rules\Enum` انجام می‌شود — نه یک لیست string تکراری. Vocabulary قفل‌شدهٔ `OQ-04 = 2B` · `DEC-016` · `BD-06` · `chk_tasks_task_type` **تغییر نکرد** و هیچ مقدار جدیدی اضافه نشد.
- مسیر کامل بدون fallback:
  ```text
  Blade form (T-2-UI-A) → StoreTaskRequest (required + Enum) → CreateTaskData (TaskType)
      → TaskService::create (task_type => $data->task_type->value) → tasks.task_type
  ```
- `DEFAULT 'development'` در دیتابیس **حذف نشد** — طبق `DEC-016` فقط نگرانی Backfill است، دیگر هرگز جایگزین Business Logic نیست.
- Backward compatibility: تسک‌های موجود و Seeder بدون تغییر (مقدار آنها از قبل `development` بود). مسیرهای مستقیم Eloquent/DB مستقل از HTTP دست‌نخورده‌اند.

### پیامدها
- `BD-06` («تسک‌های Support جدید صریحاً با `task_type = 'support'` ثبت می‌شوند») اکنون در سطح کد قابل‌دست‌یابی است — پیش از این هر تسک جدید بی‌صدا `development` ذخیره می‌شد و `BD-06` در عمل بی‌اثر بود.
- تست‌های الزامی مالک (§۸ پرامپت) همه پوشش داده شدند: Development → persists · Support → persists · Invalid → validation failure و بدون ایجاد تسک · Missing → validation failure و بدون ایجاد تسک.
- تست نگهبان: `TaskTypeAndWeightPolicyTest` (۱۲ تست · ۳۲ assertion).

### Schema متأثر
**هیچ.**

### Migration متأثر
**هیچ.** `New Migration = 0` · `Existing Migration Modified = 0`.

### وضعیت
- ACTIVE (قطعی — نیمهٔ باقی‌ماندهٔ `T-2` بسته شد)

---

## DEC-038 — تصمیمات نهایی Hardening: T-1 · T-3 · T-5 · T-7

### تاریخ
2026-09-23

### تصمیم
مالک پروژه هر چهار تصمیم باز V1.8 را قطعی پاسخ داد:

| Item | تصمیم | معنا |
|---|---|---|
| **T-1** | `KEEP ACCEPTED AS-IS` | بدون `M-10`؛ گارد Application در ۴ مسیر کافی است |
| **T-3** | گزینهٔ **A** | مدل فعلی `task_approval_recorded` + `needs_rework` + Audit Trail کافی است — رویداد مستقل `task_rejected` **ساخته نمی‌شود** |
| **T-5** | گزینهٔ **A** | حذف/بایگانی Project **قابلیت محصول نیست** — وضعیت فعلی (صفر مسیر حذف + RESTRICT + SoftDeletes بی‌استفاده) رسمی می‌شود |
| **T-7** | **YES** | CI پیاده شد و پس از ۴ فاز fix (PHP 8.4 · Gate 2 zero-match · Frontend build) در Run `35835190435` کاملاً سبز شد: **203 passed · 591 assertions · 0 failed** |

### دلیل
پاسخ صریح مالک در Owner Decision Gate (`docs/V1.8_OWNER_DECISION_GATE_T3_T5_REPORT.md`).

### پیامدها
- T-3 و T-5 به‌عنوان «CLOSED — گزینهٔ A» بسته می‌شوند؛ هیچ رویداد یا مسیر حذف جدیدی ساخته نمی‌شود.
- اگر در آینده نیاز به حذف/بایگانی Project یا رویداد مستقل رد پیدا شود، این DEC باید صریحاً باز شود.
- V1.8 Hardening کامل بسته شد: R-1..R-3 · T-1..T-7 همگی CLOSED یا تصمیم‌گیری‌شده.
- پیاده‌سازی CI مستند در: `docs/V1.8_T7_CI_FRONTEND_BUILD_FIX_AND_REVERIFICATION_REPORT.md`.

### وضعیت
- ACTIVE (قطعی — دروازهٔ V1.8 بسته شد)

---

## DEC-039 — V1.9: UI Scope فاز = Candidate A کامل (DR-1)

### تاریخ
2026-09-26

### تصمیم
مالک پروژه دامنهٔ فاز V1.9 را قطعی تعیین کرد:

| بند | دامنهٔ تأییدشده |
|---|---|
| ۱ | Module CRUD |
| ۲ | مدیریت Stage |
| ۳ | مدیریت وزن Stage (تا قفل موجود — بدون bypass) |
| ۴ | UI تأیید پیشرفت Stage (Stage Approval) |
| ۵ | UI WBS Checklist |
| ۶ | گزارش پیشرفت بر پایهٔ منبع معتبر `approved_amount` |

مقصود: رساندن مدل دامنهٔ تأییدشدهٔ موجود (V1.8) به Web UI — نه افزودن قابلیت جدید دامنه.

**مجاز نیست:** Performance Record/per-contractor · Support Ledger · Financing · محاسبهٔ مالی جدید · API شخص ثالث · sync jobs · AI · اپلیکیشن موبایل · webhook · حذف/بایگانی/بازگردانی Project · Refactoring نامرتبط · Candidate B · Candidate C.

**Migration:** Candidate A انتظار می‌رود **صفر Migration** نیاز داشته باشد — این گزارهٔ scope/معماری است، نه مجوز ساخت Migration در آینده.

### دلیل
تصمیم صریح مالک پروژه در Owner Decision Registration فاز V1.9 (Documentation-Only، 2026-09-26).

### پیامدها
- دروازهٔ تصمیم V1.9 (DR-1) بسته شد؛ پیاده‌سازی V1.9 می‌تواند در فاز اجرای مجزا آغاز شود.
- `DR-2` و `DR-4/OQ-07` به‌ترتیب در `DEC-040` و `DEC-041` حل شدند — پیش‌نیازهای باقی‌ماندهٔ فاز.
- وضعیت پس از این ثبت: دروازهٔ تصمیم V1.9 **PASSED** · پیاده‌سازی V1.9 **NOT STARTED**.

### وضعیت
- ACTIVE (قطعی — مرجع پیاده‌سازی V1.9)

---

## DEC-040 — V1.9: Tasks بدون `module_stage_id` در گزارش‌های stage-based = Exclude (DR-2)

### تاریخ
2026-09-26

### تصمیم
در **گزارش‌های پیشرفت stage-based**، تسک‌هایی که `module_stage_id IS NULL` دارند **حذف می‌شوند**:

```text
WHERE module_stage_id IS NOT NULL
```

در لایهٔ تجمیع گزارش (نه به‌عنوان قاعدهٔ عام).

**این تصمیم به این معنا نیست:** حذف/ردِ آن تسک‌ها · منع ایجاد آن‌ها · تغییر رکورد DB · انتساب خودکار به Stage · حذف از سایر گزارش‌ها · قاعدهٔ جدید lifecycle. دامنهٔ تصمیم دقیقاً **گزارش stage-based** است.

**ممنوع:** ساخت bucket جدید «تسک‌های نامرتبط» · قاعدهٔ وزن جدید برای تسک‌های بی‌stage · هر معنای کسب‌وکاری افزوده.

### دلیل
`module_stage_id` طبق `DEC-013` عمداً nullable است؛ این تصمیم فقط قاعدهٔ **نمایش گزارش‌گیری** را تعیین می‌کند و هیچ تغییری در مدل داده ایجاد نمی‌کند.

### پیامدها
- هر کوئری گزارش stage-based در V1.9 باید دقیقاً همین قاعده را در لایهٔ تجمیع اعمال کند.
- تسک‌های بی-stage در TMS و در گزارش‌های غیر stage-based به قوت خود باقی می‌مانند.
- صفر تغییر Schema؛ صفر Migration.

### وضعیت
- ACTIVE (قطعی — قاعدهٔ گزارش‌گیری V1.9)

---

## DEC-041 — V1.9: برچسب گزارش «آماده پرداخت» → «مبلغ تأییدشدهٔ مراحل» (DR-4/OQ-07)

### تاریخ
2026-09-26

### تصمیم
برچسب Legacy گزارش از «آماده پرداخت» به «مبلغ تأییدشدهٔ مراحل» تغییر می‌کند.

**شفاف‌سازی معنایی:** برچسب به مقدار دامنهٔ معتبر `approved_amount` (مقدار تأییدشدهٔ مرحله — عملیاتی/درصدی وزنی) اشاره دارد و به هیچ معنایی از «قابل پرداخت / محاسبهٔ پرداخت / فاکتور / تسویه / مجوز پرداخت پیمکار» اشاره ندارد. TMS عملیاتی/پیشرفت‌محور باقی می‌ماند؛ محاسبات مالی در Contract System موجود است. V1.9 صرفاً به‌سبب این تغییر برچسب هیچ محاسبهٔ مالی‌ای اضافه نمی‌کند.

### دلیل
رفع گمراه‌کنندگی گزارش (`OQ-07` — ثبت‌شده در `DEC-010`) با تصمیم صریح مالک.

### پیامدها
- مرز مالی TMS (بخش ۱۲ گزارش Recon) دست‌نخورده می‌ماند — تغییر برچسب، مجوز محاسبهٔ مالی نیست.
- برچسب جدید در فاز پیاده‌سازی V1.9 روی گزارش/CSV اعمال می‌شود.

### وضعیت
- ACTIVE (قطعی — برچسب رسمی گزارش V1.9)
