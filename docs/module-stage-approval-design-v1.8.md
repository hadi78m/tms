# Module Stage Approval Design — V1.8

**وضعیت:** DESIGN FREEZE (تأیید مالک پروژه در انتظار)
**دامنه:** مدل ثبت تأییدهای درصدی تجمعی، اختیارات Employer/Supervisor، کنترل ظرفیت، تاریخچه
**وابستگی:** `docs/weight-module-design-v1.8.md` (موجودیت Module/Stage) و BD-10 تا BD-17
**⚠️ این سند طراحی است. هیچ Migration، Model یا Service ساخته نشده است.**

---

## ۱. مسئله

پیش از V1.8، تنها راه ابراز «پیشرفت» در سیستم، تأیید کیفی یک **Task** بود:

```text
Task → under_review → supervisor_approved → approved
```

این جریان **هیچ مقدار عددی حمل نمی‌کند.** جدول `approvals` صفر ستون عددی دارد.

الزام جدید (§۷ پرامپت) یک جریان متفاوت می‌خواهد:

```text
Stage: Analysis (weight = 15)
   Approval #1 → 5   →  باقیمانده 10
   Approval #2 → 7   →  باقیمانده 3
   Approval #3 → 3   →  باقیمانده 0   →  Stage Completed
```

یعنی پاسخ به سؤال «Analysis چند درصد پیشرفت کرده؟» از **جمع تجمعی** می‌آید، نه از یک فیلد روی Task.

---

## ۲. تصمیم معماری: جدول مستقل

### ۲.۱ چرا `approvals` توسعه نمی‌یابد

| معیار | `approvals` فعلی | کفایت برای مدل جدید؟ |
|---|---|---|
| FK به مالک | `task_id BIGINT NOT NULL FK` | 🚫 قید سخت به Task. nullable کردنش هر کوئری و تست را تضعیف می‌کند |
| ستون عددی | هیچ | 🚫 مفهوم «۵٪ از ۱۵٪» بی‌بازنمایی |
| Update | `static::updating(fn() => false)` | 🚫 گذار `pending → approved` نیازمند Update است |
| معنا | Quality Gate چرخهٔ Task (`technical`/`final`) | 🚫 متفاوت از Ledger پیشرفت |
| تعداد رکورد در هر مالک | ۱ به‌ازای هر مرحلهٔ چرخه | 🚫 مدل جدید چند رکورد تجمعی می‌خواهد |

**نتیجه: جدول `stage_progress_approvals` جداگانه ساخته می‌شود. `approvals` بدون تغییر می‌ماند (KEEP).**

### ۲.۲ تفکیک مفهومی اجباری (BD-11)

| مفهوم | جدول | مقدار دارد؟ | تجمعی است؟ | موضوع |
|---|---|---|---|---|
| تأیید فنی/نهایی **تسک** | `approvals` | 🚫 خیر | 🚫 خیر | کیفیت اجرای یک تسک |
| تأیید درصد **Stage** | `stage_progress_approvals` | ✅ بله | ✅ بله | مقدار پیشرفت یک مرحله |

**الزام UI:** این دو **نباید** با یک واژه نمایش داده شوند.

| جدول | برچسب فارسی پیشنهادی |
|---|---|
| `approvals` (technical) | «تأیید فنی ناظر» |
| `approvals` (final) | «تأیید نهایی تسک» |
| `stage_progress_approvals` | «تأیید پیشرفت مرحله» |

---

## ۳. طرح جدول پیشنهادی

```sql
stage_progress_approvals
─────────────────────────────────────────────────────────────────────────────
id                    BIGINT IDENTITY PK
module_id             BIGINT NOT NULL  FK → modules       ON DELETE RESTRICT
module_stage_id       BIGINT NOT NULL  FK → module_stages ON DELETE RESTRICT

proposed_amount       NUMERIC(5,2) NOT NULL    -- پیشنهاد Employer یا Supervisor
approved_amount       NUMERIC(5,2) NULL        -- مقدار نهایی (ناظر می‌تواند تغییر دهد)

status                VARCHAR(50) NOT NULL     -- pending | approved | rejected | superseded

proposed_by           BIGINT NOT NULL  FK → users  ON DELETE RESTRICT
final_approved_by     BIGINT NULL      FK → users  ON DELETE RESTRICT
decided_at            TIMESTAMPTZ NULL
reason                TEXT NULL

created_at            TIMESTAMPTZ NOT NULL
updated_at            TIMESTAMPTZ NOT NULL

INDEX (module_stage_id, status)
INDEX (module_id, status)
INDEX (proposed_by)
INDEX (final_approved_by)

CONSTRAINT chk_spa_proposed_amount
  CHECK (proposed_amount > 0 AND proposed_amount <= 100)
CONSTRAINT chk_spa_approved_amount
  CHECK (approved_amount IS NULL OR (approved_amount > 0 AND approved_amount <= 100))
CONSTRAINT chk_spa_decided_consistency
  CHECK (
    (status = 'pending'  AND approved_amount IS NULL AND final_approved_by IS NULL AND decided_at IS NULL)
    OR
    (status <> 'pending' AND final_approved_by IS NOT NULL AND decided_at IS NOT NULL)
  )
```

### ۳.۱ چرا `module_id` به‌صورت Denormalized

`module_id` از طریق `module_stage_id` قابل استنتاج است، اما ذخیرهٔ مستقیم آن باعث می‌شود:

- Rollup پیشرفت ماژول بدون `JOIN` روی `module_stages` انجام شود،
- گزارش‌های سطح ماژول سریع‌تر باشند،
- و الگوی Denormalization موجود در Repository تکرار شود — همان کاری که در `tasks` با `contract_id` و `contractor_id` انجام شده است (`create_tasks_table.php`).

⚠️ ریسک: واگرایی مقادیر. باید در Service تضمین شود که `module_id` همیشه با `module_stage.module_id` مطابق است. **گزینهٔ جایگزین:** حذف `module_id` و اتکا به JOIN. → بخش ۹، `OQ-06-a`.

### ۳.۲ چرا `status` تنها `superseded` را اضافه می‌کند

سه وضعیت `pending` / `approved` / `rejected` از `WeightChangeRequestStatus` موجود الگو گرفته می‌شود. وضعیت چهارم `superseded` برای حالتی است که یک درخواست pending با درخواست جدیدتر جایگزین می‌شود — پیشنهاد، نه الزام. اگر `OQ-06` گزینهٔ (الف) «فقط یکی pending» را انتخاب کند، `superseded` فقط زمانی معنا دارد که درخواست قبلی **لغو** شود.

### ۳.۳ چرا `approved_amount` جدا از `proposed_amount`

الزام BD-16: اگر Employer مقدار ۵٪ ثبت کند و Supervisor مقدار ۷٪ را نهایی کند:

- `proposed_amount = 5` → **حفظ می‌شود**
- `approved_amount = 7` → مقدار نهایی
- مقدار ۵٪ **بازنویسی نمی‌شود** (BD-21)

یک ستون واحد نمی‌تواند این را حمل کند، چون تاریخچه از بین می‌رود.

---

## ۴. مدل جریان تأیید

### ۴.۱ جریان مفهومی (§۹ پرامپت)

```text
Contractor Work
      ↓
Evidence / Task Result              ← documents موجود (چندریختی)
      ↓
Employer / Supervisor Assessment
      ↓
proposed_amount
      ↓
Supervisor Final Confirmation
      ↓
approved_amount
```

### ۴.۲ حالت‌های قابل تنظیم — الزام BD-15

کلید تنظیمات: `system_settings.progress_approval_mode`

| حالت | تعیین‌کننده | تأیید نهایی | الزام به ورود Employer؟ |
|---|---|---|---|
| **`supervisor_only`** | Supervisor | **همان Supervisor** | 🚫 **هیچ — الزام صریح BD-15** |
| `employer_then_supervisor` | Employer | Supervisor | ✅ بله |
| `either_then_supervisor` | Employer یا Supervisor | Supervisor | 🚫 خیر |

**مقدار پیش‌فرض پیشنهادی:** `supervisor_only` — چون پرامپت §۹ صریحاً می‌گوید «اگر ناظر مستقیماً درصد را تعیین کند، نباید محدود شود.»

**این نخستین مصرف‌کنندهٔ واقعی `SettingsService` است.** امروز `SettingsService` تنها در `SettingController` تزریق شده و هیچ سرویس دیگری آن را نمی‌خواند.

### ۴.۳ حالت `supervisor_only` — پیاده‌سازی اتمیک

توصیهٔ فنی: حتی در این حالت، رکورد با `status = 'pending'` درج و سپس گذار داده شود (نه درج مستقیم با `approved`).

دلایل:

1. الگوی موجود `WeightChangeRequestService::approveRequest` (خطوط ۵۷–۵۸) دقیقاً همین است: `lockForUpdate()` روی رکورد pending، سپس گذار.
2. تاریخچهٔ Audit یکنواخت می‌ماند — همهٔ تأییدها یک مسیر واحد طی می‌کنند.
3. گزارش «تأییدهای انجام‌شده» یک مسیر کوئری واحد دارد.
4. اگر مالک پروژه بعداً تنظیمات را به `employer_then_supervisor` تغییر داد، هیچ رکورد تاریخی ناسازگار باقی نمی‌ماند.

```php
DB::transaction(function () {
    // 1. lockForUpdate() روی رکورد pending
    // 2. درج رکورد با proposed_amount = supervisor مقدار، status = 'pending'
    // 3. بلافاصله گذار: approved_amount = proposed_amount, status = 'approved'
});
```

### ۴.۴ نمودار حالت

```text
                    [ ثبت پیشنهاد ]
                          │
                    proposed_by تعیین می‌کند
                          │
                          ▼
                     ┌─────────┐
                     │ pending │
                     └────┬────┘
                          │
        ┌─────────────────┼─────────────────┐
        │                 │                 │
   Supervisor        Supervisor        (اختیاری)
   تأیید همان مقدار   تعدیل مقدار       رد کردن
        │                 │                 │
        ▼                 ▼                 ▼
   approved          approved          rejected
   approved_amount   approved_amount   approved_amount = NULL
   = proposed        ≠ proposed
        │                 │
        └────────┬────────┘
                 ▼
        activity_logs                ← هر گذار یک رویداد
        stage_progress_approval_*
```

---

## ۵. اختیارات (Authority)

### ۵.۱ ماتریس اختیارات

| عمل | Employer | Supervisor | Contractor | Management |
|---|---|---|---|---|
| ثبت `proposed_amount` | ✅ (BD-12) | ✅ (**بدون محدودیت** — BD-15) | 🚫 | 🚫 |
| تغییر مقدار خودش | ✅ | ✅ | 🚫 | 🚫 |
| تغییر مقدار دیگری | ⚠️ امکان‌پذیر اما **نتیجهٔ نهایی با ناظر است** | ✅ (BD-16) | 🚫 | 🚫 |
| تأیید نهایی | 🚫 (BD-14) | ✅ **انحصاری** | 🚫 | 🚫 |
| رد کردن | ✅ | ✅ | 🚫 | 🚫 |
| مشاهده | ✅ | ✅ | ⚠️ فقط Stageهای مرتبط با تسک‌های خودش | ✅ |

### ۵.۲ قاعدهٔ تعارض — BD-16

```text
Employer:  Analysis = 5%
Supervisor: Analysis = 7%
────────────────────────────
نتیجه نهایی:
  proposed_amount = 5      ← حفظ می‌شود (BD-21)
  approved_amount = 7      ← مقدار نهایی (BD-16)
```

مقدار ناظر **غالب** است، اما مقدار قبلی **پاک نمی‌شود**. هر دو در رکورد می‌مانند و تغییر در `activity_logs` با `old_values`/`new_values` ثبت می‌شود.

### ۵.۳ وضعیت تشخیص مجوز در کد موجود

```php
// app/Domain/Services/ApprovalService.php — خط ۳۰
if ($actor->contractor_id !== null) {
    throw new InvalidApprovalException('Contractor cannot perform Technical Approval.');
}
```

**مشاهده:** تشخیص «ناظر بودن» در لایه Domain بر اساس **کلید داده** (`users.contractor_id`) است، در حالی که مجوز واقعی در `ApprovalController:31` با `$actor->can('technical_approval')` بررسی می‌شود.

**برای `stage_progress_approvals`:** توصیه می‌شود از **Permission** استفاده شود تا با تصمیم «داشبورد بر اساس Role + Permission + Settings» هم‌خوان باشد:

```text
stage_progress_propose          ← Employer + Supervisor
stage_progress_final_approve    ← Supervisor
```

→ پرسش باز `OQ-11`.

---

## ۶. کنترل ظرفیت

### ۶.۱ قاعده

```text
SUM(approved_amount)
WHERE module_stage_id = S AND status = 'approved'
                                        ≤   S.weight
```

### ۶.۲ الگوی پیاده‌سازی موجود

الگوی لازم در Repository **از قبل وجود دارد** — نیازی به اختراع نیست:

```php
// app/Domain/Services/WeightChangeRequestService.php — خطوط ۵۵-۸۴
DB::transaction(function () use ($request, $actor) {
    $request = WeightChangeRequest::where('id', $request->id)
        ->lockForUpdate()->firstOrFail();          // ← قفل رکورد

    if ($request->status !== 'pending') { ... }     // ← بررسی گذار

    $task = Task::where('id', $request->task_id)
        ->lockForUpdate()->firstOrFail();          // ← قفل مالک

    // ... عملیات + audit
});
```

همین ساختار برای `StageProgressApprovalService::finalApprove()` کافی است:

```text
DB::transaction
  ├── lockForUpdate()  روی رکورد stage_progress_approvals
  ├── lockForUpdate()  روی ردیف module_stages  ← برای جلوگیری از Race
  ├── محاسبهٔ SUM(approved_amount)
  ├── اگر SUM + مقدار جدید > stage.weight → Exception
  └── گذار به approved + audit
```

### ۶.۳ Exception پیشنهادی

```php
App\Domain\Exceptions\StageProgressCapacityExceededException
    extends DomainException
```

هم‌خوان با `InvalidWeightException` و `PendingRequestExistsException` موجود در `app/Domain/Exceptions/`.

---

## ۷. Audit و تاریخچه (BD-17، BD-21)

### ۷.۱ ⛔ مانع بحرانی

```php
// app/Providers/AppServiceProvider.php — خطوط ۱۹-۲۲
$this->app->bind(
    AuditServiceInterface::class,
    NullAuditService::class        // ← در محیط اجرا
);
```

```php
// app/Domain/Services/NullAuditService.php
public function log(...): ActivityLog
{
    // Do nothing for testing
    return new ActivityLog;        // ← بدون save()
}
```

**نتیجه:** `activity_logs` هرگز توسط برنامه پر نمی‌شود. الزامات BD-17 و BD-21 **بدون رفع این مانع غیرقابل تحقق‌اند.**

### ۷.۲ جدول `activity_logs` کافی است (KEEP)

| الزام §۱۳ پرامپت | ستون موجود |
|---|---|
| Module | `entity_type` + `entity_id` (چندریختی) |
| Stage | `new_values` JSONB |
| Previous Approved % | `old_values` JSONB |
| New Approved % | `new_values` JSONB |
| Changed By | `user_id` |
| Changed At | `created_at` |
| Reason | `reason` TEXT |
| Final Approved By | `new_values` JSONB |

**هیچ تغییر ساختاری لازم نیست.** مشکل در **نویسنده** است، نه در **مخزن**.

### ۷.۳ رویدادهای لازم

```text
stage_progress_proposed
stage_progress_adjusted_by_supervisor     ← تعارض BD-16
stage_progress_approved
stage_progress_rejected
stage_baseline_changed                    ← وابسته به OQ-05
```

### ۷.۴ تفکیک دو لایه

| لایه | نقش | تغییرپذیری |
|---|---|---|
| `stage_progress_approvals` | **وضعیت جاری** — Ledger عملیاتی | قابل Update (گذار حالت) |
| `activity_logs` | **تاریخچهٔ کامل** — هر تغییر | Immutable (`updating`/`deleting` → false) |

---

## ۸. تست‌های لازم

| # | سناریو | مرجع |
|---|---|---|
| ۱ | تأیید تدریجی: ۵+۷+۳ = ۱۵ → Stage Completed | BD-10 |
| ۲ | تأیید جزئی: ۷.۵ از ۱۵ → Remaining = 7.5، NOT Completed | §۷ |
| ۳ | نقض سقف Stage: تلاش برای عبور از `stage.weight` → Exception | بخش ۶ |
| ۴ | حالت `supervisor_only`: ناظر تعیین و همان را تأیید کند | BD-15 |
| ۵ | حالت `employer_then_supervisor` | BD-12 |
| ۶ | **تعارض BD-16:** Employer=5، Supervisor=7 → Final=7، مقدار ۵ **حفظ** شود | BD-16 |
| ۷ | `approved_amount` کمتر از `proposed_amount` (اگر `OQ-06-b` تأیید شود) | بخش ۳.۳ |
| ۸ | دروازهٔ مجوز (Permission) برای تعیین/تأیید | بخش ۵.۳ |
| ۹ | **تأیید یک Task هیچ اثری بر Progress ندارد (صفر)** | BD-01، BD-11 |
| ۱۰ | Audit: هر تغییر رکورد `activity_logs` با `old_values`/`new_values` بسازد | BD-17 |
| ۱۱ | Concurrency: دو تأیید هم‌زمان روی یک Stage → قفل و سقف درست | بخش ۶.۲ |
| ۱۲ | `chk_spa_decided_consistency`: رکورد approved بدون `final_approved_by` رد شود | بخش ۳ |
| ۱۳ | فقط یک `pending` هم‌زمان (اگر `OQ-06-a`) | بخش ۳.۲ |

**⛔ مانع پیش‌نیاز:** PostgreSQL روی `127.0.0.1:5432` در حال اجرا نیست. بیس تست باید بازتأیید شود.

---

## ۹. پرسش‌های باز

| ID | پرسش |
|---|---|
| `OQ-05` | معنای Weight Lock — آیا پس از اولین تأیید، تغییر وزن Stage ممنوع می‌شود؟ |
| `OQ-06` | تعداد مجاز `pending` هم‌زمان روی یک Stage |
| `OQ-06-a` | نگه‌داشتن `module_id` Denormalized در جدول تأییدها یا حذف و اتکا به JOIN؟ |
| `OQ-06-b` | آیا `approved_amount` می‌تواند **کمتر** از `proposed_amount` باشد؟ |
| `OQ-09` | آیا تأیید نهایی می‌تواند بعداً کسر شود (Negative Approval)؟ |
| `OQ-10` | آیا تکمیل WBS Phase بر Progress اثری دارد؟ |
| `OQ-11` | مرجع مجوز: Permission جدید یا نقش‌های فعلی؟ |

---

**END OF DOCUMENT**
