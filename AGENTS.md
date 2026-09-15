# AGENTS.md - Agent Behavioral Rules
<!-- قوانین رفتار ایجنت - این فایل در تمام پروژه‌ها یکسان است و تغییر نمی‌کند -->
## Project Initialization
- If `MEMORY.md` or `tasks.md` contain default placeholder values (e.g., `[Project Name]`, `[Framework]`, etc.), scan the project codebase before executing any tasks, extract information about structure, framework, and APIs, and populate these two files.
- If `MEMORY.md` and `tasks.md` do not exist, create them.
- If required by the project, create auxiliary files `ERROR_LOG.md` and `ACTION_TRACKER.md` based on standard templates.
<!-- گام راه‌اندازی پروژه جدید: بررسی و تکمیل فایل‌های اولیه، حافظه و لگ‌ها -->

---
<!-- رفتار کلی: پاسخ‌های کوتاه و مستقیم، ارائه برنامه پیش از اجرا و تایید کاربر، استفاده از ساب‌ایجنت -->
## General Behavior
- Responses must be concise, useful, and strictly focused on the task. Avoid conversational fillers.
- Present an execution plan before making changes and wait for user approval.
- **Destructive Database Actions**: Any request involving dropping, truncating, or altering structural database tables requires an explicit user confirmation prompt before execution.
- Use sub-agents for complex tasks.

---
## Memory Management
- Read `MEMORY.md` at the start of every session.
- Log new tasks in `MEMORY.md` before execution.
- Update task status in `MEMORY.md` upon completion.
- If an environmental error (Server/Config Error) or complex bug is resolved during development, log it in `ERROR_LOG.md` to prevent recurrence in future sessions.
- After rewriting or refactoring large sections of the project, list modified files and a summary of fixes in `ACTION_TRACKER.md`.
<!-- مدیریت حافظه: خواندن و بروزرسانی مداوم MEMORY.md و ثبت خطاها در ERROR_LOG.md -->

---
## Quality Assurance & Automated Testing
- **Mandatory Execution**: Never finalize or report a task as completed without running and logging its validation/unit test.
- **Test-Driven Refactoring**: After making any modifications, run relevant integration and unit tests (PHPUnit/WP-CLI tests or designated mock execution scripts).
- **Failure Protocol**: If any test fails:
  1. Mark the task as in-progress (`[ ]`) in `MEMORY.md`.
  2. Log the detailed error in `ERROR_LOG.md`.
  3. Fix the issue and re-run tests.
  4. Only mark as completed (`[x]`) when all tests pass with zero errors.
  5. **Safe Test Execution**: Never run destructive migration commands (e.g., `php artisan migrate:fresh`, `migrate:refresh`, `migrate:reset`) during automated test execution. Tests MUST run using isolated test databases (e.g., SQLite in-memory DB via `phpunit.xml` configuration) to protect production and development databases from wiping.

---
## Technical Rules
- Display Persian text with proper RTL orientation.
- Use Jalali format for dates.
- Adhere strictly to the pre-defined project structure.
- Check `.gitignore` and `.agentignore` before creating files or scanning the project; NEVER edit, read, or commit security keys (`.env`), temporary databases, or heavy package folders.
<!-- قوانین فنی: پشتیبانی از RTL و تاریخ جلالی، رعایت ساختار و عدم دسترسی به فایل‌های حساس -->
---
## UI/UX & Styling Guidelines
- **Framework**: Use Tailwind CSS exclusively for all styling. Do NOT install or import Bootstrap, Ant Design, or Carbon packages.
- **RTL & Localization**: All generated UI pages, components, and forms must natively support RTL (`dir="rtl"`) and use Persian text for labels/placeholders.
- **Design Principles**: Replicate layout patterns, form spaces, and accessibility standards from leading design systems (like IBM Carbon or Ant Design) purely using Tailwind utility classes.
- **Typography**: Apply standard Persian web fonts (e.g., Vazirmatn or Yekan Bakh) via Tailwind font-family configurations.
<!-- راهنمای UI/UX: استفاده اختصاصی از Tailwind CSS، پشتیبانی کامل RTL و الگوبرداری از سیستم‌های دیزاین -->

---
## File Reference Guide
<!-- راهنمای فایل‌ها -->

| File                             | Purpose                                                       | When to Use                                          |
| :------------------------------- | :------------------------------------------------------------ | :--------------------------------------------------- |
| `AGENTS.md`                      | Static behavioral rules                                       | Every session                                        |
| `MEMORY.md`                      | Live project memory & task tracking                           | Continuous read/write                                |
| `tasks.md`                       | Technical documentation, APIs, and clean structure            | During technical tasks                               |
| `config.js`                      | Project configurations                                        | When updating settings                               |
| `ERROR_LOG.md` *(Optional)*      | Logging resolved environment errors to prevent bug repetition | Upon encountering/resolving complex technical errors |
| `ACTION_TRACKER.md` *(Optional)* | Detailed report of code modifications made in a session       | After heavy code refactoring                         |

---
## Execution Workflow
<!-- جریان کاری -->

```
Start Session → Read MEMORY.md → Read AGENTS.md
    ↓
Receive Request → Log in MEMORY.md → Check tasks.md
    ↓
Execute → Apply config.js → Follow AGENTS.md
    ↓
Complete → Update MEMORY.md → Update tasks.md
```
---
## Strictly Forbidden Errors 

- **Rule 1:** NEVER store password fields in plaintext or without encryption in the database. 
- **Rule 2:** Avoid executing unnecessary `DB::commit()` calls inside batch processing loops. 
- **Rule 3:** NEVER execute database table deletions (`DROP TABLE`, `TRUNCATE`, destructive migrations, or DB drop commands) without prior explicit written confirmation from the user.
- **Rule 4:** NEVER execute `php artisan migrate:fresh`, `migrate:refresh`, or destructive DB testing traits (like `RefreshDatabase` without in-memory SQLite config) on non-testing environments.
---
## Security & OWASP Guidelines

- **Strict OWASP Compliance**: Adhere to OWASP Top 10 security standards across all API endpoints, controllers, and input processing logic.
    
- **Forbidden HTTP Methods**: Strictly avoid using `PUT` and `DELETE` HTTP methods for state mutation. Use `POST` with specific endpoint routes or request bodies instead.
    
- **Input Sanitization & Validation**: Sanitize and validate all incoming request parameters to prevent Injection attacks (SQLi, XSS, Command Injection).
    
- **Authentication & Secrets**: Never expose credentials, API keys, or sensitive user data in logs, error tracebacks, or frontend components.
    
- **Broken Access Control**: Ensure strict authorization checks (Policies/Gateways) are applied at the controller level for every route.


---
# LARAMINA AUTOMATION WORKFLOW

You are an AI developer assistant. Whenever working on a Laravel project, follow this rule:

## 1. Package Installation Check
1. Read `composer.json` in the root directory.
2. Check if `hadii/laramina` exists under `dependencies` or `require`.
3. If `hadii/laramina` is NOT installed:
   - Ask the user: 
     "پکیج Laramina روی این پروژه نصب نیست. آیا تمایل دارید بر اساس فایل README.md آن را نصب و راه‌اندازی اولیه کنم؟"
   - If the user agrees:
     - Read `README.md` (Quick Start section).
     - Run `composer require hadii/laramina`.
     - Run the publish commands:
       `php artisan vendor:publish --tag=laramina-config`
       `php artisan vendor:publish --tag=laramina-assets`
       `php artisan vendor:publish --tag=laramina-lang`
       `php artisan vendor:publish --tag=laramina-views`
     - Read section 2.3 in `INSTALL.md` to ensure jQuery, CSRF token, FontAwesome, and `@include('laramina::adminPlatform')` are set up in the layout.

## 2. CRUD and Table Management Check
1. If the package is already installed (or after you install it):
   - Whenever asked to create a new module, table, or CRUD management for a model:
     - Ask the user: 
       "آیا تمایل دارید ساخت جدول و مدیریت این بخش بر اساس مستندات INSTALL.md و با پکیج Laramina انجام شود یا بدون در نظر گرفتن پکیج؟"
2. If the user chooses Laramina:
   - Read `INSTALL.md` thoroughly.
   - Verify the Model exists (or offer to create one with `AdminTableTrait` and `adminTransform()`).
   - Run `php artisan laramina:make-ui <ModelName> --force`.
   - Update Controller, Routes, and JS files (`table.js`, `actions.js`, `create-form.js`) strictly as specified in `INSTALL.md`.