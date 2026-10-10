# Phase 3 — Board Design / Business Mapping Gate

> **Type:** Read-Only Design & Business Mapping Gate. **ZERO implementation.** No code, no view, no route, no controller, no model, no policy, no service, no migration, no DB operation, no test change, no commit, no push. Existing documentation untouched.
> **Purpose:** Before any Board is built, define exactly what the Board shows and how real TMS statuses map onto it — without letting template business rules leak into TMS.
> **Baseline:** working tree as inspected at gate open (`git status` verified; pre-existing Phase 1/2 artifacts and V1.11 WBS work left untouched).

---

## A. Executive Summary

The repository fully supports a Task Board, but **not the board the templates depict**. Every element the three reference templates put on a card — progress bars, SLA countdowns, checklists, remaining-time badges — has **no data basis in TMS** and is excluded by construction.

What the repository *does* provide is unusually clean:

1. **9 task statuses, exactly as believed**, declared in enum order at `app/Domain/Enums/TaskStatus.php:7-15` (`draft → assigned → in_progress → submitted_for_review → under_review → supervisor_approved → approved → needs_rework → cancelled`).
2. **A complete, enforced state machine** at `app/Domain/Rules/TaskStateTransition.php:10-20` — the columns of a board can be the statuses themselves, with zero invented mapping.
3. **A canonical 9-status Persian label + color map already in production** at `resources/views/tasks/show.blade.php:28-49` — the board can reuse it verbatim.
4. **Authorization already wired**: `ProjectScopeService::applyProjectScope()` + contractor isolation are *already applied* to the task index query (`TaskController::43-50`). A board reuses the exact same two layers and invents no permission.
5. **No update path exists** — deliberately. `TaskPolicy` has no `update`/`viewAny`/`delete` ability (`app/Policies/TaskPolicy.php:32-35`), `TaskController` has no `update()`/`edit()`/`destroy()`, and `UpdateTaskRequest` is a dead stub (`authorize()` returns `false`). Status mutation exists only through four service-guarded routes. **Any drag-and-drop status change would bypass the central state machine — a business-rule violation, not a missing feature.**

Therefore the board is specified as **read-only**, and the only remaining blockers are Owner business/UX decisions, not repository evidence. **Gate outcome: READY FOR OWNER DECISION.**

---

## B. Repository Evidence

### B.1 Task Status — verified 9/9

Source of truth: `app/Domain/Enums/TaskStatus.php:7-15` (backed string enum).

| # | Status | Exists | Meaning (canonical Persian label, `show.blade.php:39-49`) | Evidence |
|---|---|---|---|---|
| 1 | `draft` | Yes | پیش‌نویس | `TaskStatus.php:7` |
| 2 | `assigned` | Yes | ارجاع شده | `TaskStatus.php:8` |
| 3 | `in_progress` | Yes | در حال انجام | `TaskStatus.php:9` |
| 4 | `submitted_for_review` | Yes | ارائه شده برای بررسی | `TaskStatus.php:10` |
| 5 | `under_review` | Yes | در حال بررسی | `TaskStatus.php:11` |
| 6 | `supervisor_approved` | Yes | تایید ناظر | `TaskStatus.php:12` |
| 7 | `approved` | Yes | تایید نهایی (terminal) | `TaskStatus.php:13` |
| 8 | `needs_rework` | Yes | نیاز به اصلاح | `TaskStatus.php:14` |
| 9 | `cancelled` | Yes | لغو شده (terminal) | `TaskStatus.php:15` |

**Lifecycle order = enum declaration order.** Terminal states: `approved`, `cancelled` — proven twice: empty transition arrays (`TaskStateTransition.php:18-19`) and `TaskService::assertStageChangeAllowed()` lock (`app/Domain/Services/TaskService.php:313-324`).

**State machine** (`app/Domain/Rules/TaskStateTransition.php:10-20`):

| From | Allowed next |
|---|---|
| `draft` | `assigned`, `cancelled` |
| `assigned` | `in_progress`, `cancelled` |
| `in_progress` | `submitted_for_review`, `cancelled` |
| `submitted_for_review` | `under_review` |
| `under_review` | `supervisor_approved`, `needs_rework` |
| `supervisor_approved` | `approved`, `needs_rework` |
| `needs_rework` | `in_progress`, `cancelled` |
| `approved` | *(terminal — none)* |
| `cancelled` | *(terminal — none)* |

Findings that shape the board:

- **Every transition is a board adjacency.** A 9-column board reproduces the state machine 1:1 — a card's column *is* its status.
- **`cancelled` is reachable from only 4 states** (`draft`, `assigned`, `in_progress`, `needs_rework`). The state machine **forbids cancellation during review** — not from `submitted_for_review`, `under_review`, or `supervisor_approved`. There is **no un-cancel path**.
- **`needs_rework` has TWO entry points** — from `under_review` *and* from `supervisor_approved`. Rework can occur *after* technical approval; it is not merely an early rejection.
- ⚠️ **The tracker's §10 transition table is stale (pre-V1.3)**: it lists `under_review → approved` and lacks `supervisor_approved` as a source. The code above is authoritative (verified this gate).

### B.2 Task Card Data

Only real repository data. "Partial" = relation exists and is derivable, but no accessor/convenience exists.

| Field | Available | Source |
|---|---|---|
| Task ID | **Yes** | `tasks.id` |
| Title | **Yes** | `tasks.title` (varchar 255) |
| Project | **Yes** | `Task::project()` (`app/Models/Task.php:27`); `project.name` |
| Module / Stage | **Partial** | `Task::moduleStage()` (`:51`), `Task::module()` (`:61`), `Task::wbsPhase()` (`:42`) — all nullable; stages exist only for `development`-type tasks |
| Status | **Yes** | `tasks.status` + canonical label/color map (`show.blade.php:28-49`) |
| Priority | **Yes** | `tasks.priority`; `TaskPriority` enum (`app/Domain/Enums/TaskPriority.php:7-10`): `low`/`normal`/`high`/`urgent` |
| Assignee | **Partial** | No `assignee`/`user_id` column. Via `Task::activeAssignment()` (`:86`, `whereNull('ended_at')`) → `->user`. **Null for `draft` tasks** (assignment starts at `assigned`) |
| Due Date | **Partial** | `tasks.planned_due_at` — a **TIMESTAMPTZ**, not a DATE. Jalali helpers exist (`jdate()`, `app/Support/JalaliDate.php`) |
| Created Date | **Yes** | `tasks.created_at` |
| Dependencies | **Partial** | `dependenciesAsSuccessor`/`dependenciesAsPredecessor` (`:111-116`); count via relation — no accessor |
| Documents | **Partial** | `documents()` morphMany (`:121`); count via relation — no accessor |
| SLA | **Partial** | `slaRecords()` (`:106`); `is_breached`/`target_duration` live on `SlaRecord` rows (`app/Models/SlaRecord.php:11-18`). **No Task-level `isBreached`/current-SLA accessor; no stored `due_at`** (due = `started_at + target_duration`, computed on the fly in `SlaService`) |
| Approval state | **Partial** | `approvals()` (`:101`); `ApprovalStatus` enum — no accessor |
| **Progress** | **NO — verified absent** | No column in any of the 4 task migrations; no accessor on `Task`; no calculation anywhere. See B.5 |

Also absent (do not invent): any `order`/`sort_order`/kanban-position column, any `rework_count` on `tasks`, any `group`/`tag` concept, any `estimate`/`effort`/`worked hours` fields.

### B.3 Filters

No filter logic exists in the controller today — `TaskController::index` has **zero** filtering/search/ordering wiring, and the current index view's search box is decorative (no `name`, no form, `index.blade.php:10-13`). The table below judges **data support**, not code support.

| Filter | Supported | Source |
|---|---|---|
| Search | **Partial** | `tasks.title` column exists; no filter code |
| Project | **Partial** | `project_id` exists; visibility already governed by `ProjectScopeService`; a selector would need the accessible-projects list |
| Status | **Yes (data)** | `tasks.status` + enum; no filter code |
| Priority | **Yes (data)** | `tasks.priority` + enum; no filter code |
| Assignee | **Partial** | No direct column — requires join through `activeAssignment.user` |
| Module / Stage | **Partial** | `module_stage_id` nullable; applies to `development` tasks only |
| Due Date | **Partial** | `planned_due_at` exists; no filter code |

Only filters with real data may appear in the recommendation; all require new (but additive) query code in a later phase.

### B.4 Authorization — what the Board must reuse

Two-layer model, both already in production on the task index query. **The Board adds no permission and no scope.**

1. **Route gate** — `auth` middleware only, exactly as `tasks.index` has today (`routes/web.php:45`). No role middleware on the list surface.
2. **Query scope** — mirror `TaskController::index` (`app/Http/Controllers/Web/TaskController.php:31-50`):
   - `ProjectScopeService::applyProjectScope($query, $user)` (`app/Domain/Rules/ProjectScopeService.php:98-107`) — restricts **supervisor** to active-membership projects (`supervisorBranch() :133-140`, `ProjectMembership` where `ended_at IS NULL`, OD-6-a); **admin/management** unrestricted (`isUnrestricted() :44-47`); **PM/viewer/employer/contractor** intentionally *not* scoped (Q1 preserve-current-behavior decision; `employerBranch()` DEFERRED per OD-2).
   - Contractor isolation: `whereHas('project', contractor_id === user->contractor_id)` (`TaskController:46-50`), backed row-level by `TaskScopeService::canAccess()` (`app/Domain/Rules/TaskScopeService.php:16-20`).
3. **Row-level gate (drawer/detail)** — `TaskPolicy::view()` (`app/Policies/TaskPolicy.php:52-55`) = `withinScope()` = `canAccessProject` **and** `taskScope->canAccess`. This is the ability the card-click target must satisfy.

Available abilities on `TaskPolicy`: `view`, `create`, `startProgress`, `submitForReview`, `manageStages`, `assign`. **Deliberately absent: `update`, `viewAny`, `delete`** (`TaskPolicy.php:32-35`). Permission strings in the catalog are space-separated Spatie names — `create tasks`, `assign tasks`, `technical_approval`, `final_approval`, `submit rework`, `view reports`, `export reports`, `upload evidence`, `manage users`, `manage settings`, `manage projects` (`database/seeders/InitialTmsSeeder.php:23-41`); **no dot-notation `tasks.view` exists and none may be invented.**

Roles (exactly 7, `InitialTmsSeeder.php:33-41`): `admin`, `project_manager`, `supervisor`, `employer`, `contractor`, `management`, `viewer`.

⚠️ **Risk (reported, not fixed):** the index query eager-loads `project, wbsPhase, activeAssignment.user` but **not** `contractor`, while `index.blade.php:38` reads `contractor->name` (N+1). A board reusing that query and showing contractor must add the eager load. Also `index.blade.php:43-50` uses stale status keys (`active`/`completed`) that do not exist in the enum, so every task currently falls to the fallback pill.

### B.5 Progress — verified absent (decisive negative finding)

- No progress/percent column in any task migration; no `progress` accessor or calculation on `Task` (full-file read + grep).
- Task advancement is only inferable from **status**. `tasks.weight` is **deprecated** (`2026_09_22_100001`: "a Task has no Business Weight").
- WBS phase completion is **not** computed from checklist items — `WbsPhaseService::complete()` (`app/Domain/Services/WbsPhaseService.php:122-152`) is an explicit supervisor declaration ("no checklist completeness rule is imposed here"); checklist items carry "no computational weight" (`:22-23`).
- Stage-level progress is `StageProgressApproval.approved_amount` — belongs to **Module Stage**, not Task.

> **Consequence: a TMS Board card must NOT carry a progress bar.** All three templates put one on the card (checklist bar / % bar / SLA quota bar); none has a data basis here.

---

## C. Template UX Findings

Templates are **visual/UX references only** — not a business source of truth. Column names below are template-only.

### C.1 rahgostar (`template/rahgostar/src/pages/Board.tsx`, 544 lines)

| Pattern | Verdict |
|---|---|
| 4-column grid (`todo/doing/review/done`) with sticky headers, count + WIP badges, dashed empty-column placeholder (`:292-350`) | **Useful** — layout/visual pattern only |
| Card click → **left slide-over drawer** (`TaskDrawer :362-543`, `max-w-[430px]`) | **Useful** — matches our Phase 1 Drawer + preferred UX |
| Priority edge-stripe on card; group chip; attachment/dependency counts; assignee avatar + name (`TaskCard :36-176`) | **Useful** — minus the group concept (absent in TMS) |
| 4 generic statuses incl. WIP limits (`data.ts:352-357`) | **Not applicable** — TMS has 9 real statuses |
| Project Health banner — done-ratio %, allocated/spent hours, sprint countdown (`:210-260`) | **Not applicable** — invented KPIs, no data basis |
| Checklist progress bar on card (`:107-122`) | **Business risk** — Task has no checklist |
| Live 1-second SLA countdown box (`:124-143, 192-195`) | **Business risk** — no stored SLA `due_at`; would be fabricated |
| Prev/next "move" buttons mutate status (`:145-173`) | **Business risk** — bypasses `TaskStateTransition` |
| Native HTML5 DnD + framer-motion | **Not applicable** — Blade/Alpine stack, and read-only board |

### C.2 taskyar (`template/taskyar/src/pages/Board.tsx`, 907 lines)

| Pattern | Verdict |
|---|---|
| Richest filter bar: search, member select, project select, "فقط تسک‌های من" toggle, priority chips, live "N تسک در نما" counter (`:251-306`) | **Useful** — the filter UX shape (data support verified in B.3) |
| Board ↔ "دیوار یادداشت چسبانه" view switch via segmented tabs (`:226-247`) | **Useful** — our `x-ui.segmented` already renders board/list options (`tests/Feature/Ui/UiFoundationTest.php:143`) |
| Small dependency/subtask count badges, SLA alert only when tone≠ok (`:399-506`) | **Useful** — restraint principle: show SLA only when abnormal |
| SLA compliance ring + tone legend in cockpit (`:111-225`) | **Not applicable** — cockpit is a personal dashboard, not a project board |
| 5 statuses incl. `blocked` (`types.ts:51`) | **Not applicable** — TMS has no `blocked` status; blocking is dependency-based (`TaskBlockedException`) |
| % progress bar + slider (`:399-506`, modal) | **Business risk** — no progress in repo |
| "تحویل شد / X تأخیر" remaining-time badge | **Business risk** — no estimate/effort fields |
| Sticky Notes free-position wall (`:509-651`) | **Not applicable** — no data model for positions |
| Card click → centered modal (`ui.tsx:328-394`) | **Not applicable** — conflicts with the Drawer decision |

### C.3 rayka (`template/rayka/index.html`, 1264 lines, vanilla JS)

| Pattern | Verdict |
|---|---|
| Sticky-card aesthetic — rotation from id hash, tape, hover-straighten (`:69-70, 810-831`) | **Useful** — optional visual flavor |
| "تایید شد" stamp + line-through on done (`:810-831`) | **Useful** — clear terminal affordance for `approved` |
| **Blocked warning when dependencies unfinished** in detail (`:1154-1219`) | **Useful** — dependency blocking is real in TMS |
| Board ↔ list view toggle (`:457-458`) | **Useful** |
| Mobile: modals become **bottom sheets** (`:1016`) | **Useful** — mobile detail pattern |
| Search + group chips + "mine" toggle (`:777-779`) | **Partly useful** — group concept absent in TMS |
| 4 generic statuses (`:252-257`) | **Not applicable** |
| SLA mini bar with %, group×priority SLA matrix (`:936-965, slaMini`) | **Business risk** — quota semantics not in repo; SLA is fixed 60/120 min per type |
| Log-work hours input; worked/estimate tiles | **Business risk** — no effort model |
| 20-second countdown polling (`:1261`) | **Not applicable** — read-only board needs no live ticking |

**Cross-template consensus:** all three stack to a single column on mobile (`grid-cols-1`) — none uses horizontal scrolling columns. All three use native HTML5 drag (no DnD library). Detail UI differs: rahgostar = left drawer (our choice), taskyar/rayka = centered modal.

---

## D. Board Options

### Option A — 9 columns, one per `TaskStatus` (1:1)

```
پیش‌نویس | ارجاع شده | در حال انجام | ارائه‌شده | در حال بررسی | تایید ناظر | تایید نهایی | نیاز به اصلاح | لغو شده
```

- **Repository evidence: FULL.** Enum order = column order; state-machine adjacency = column adjacency; canonical labels/colors at `show.blade.php:28-49` reusable verbatim. **Zero invented semantics.**
- **UX advantage:** a card's column *is* its status — no translation layer that can drift from the enum; workflow stage and "who acts next" are exactly visible (contractor acts in `assigned`/`in_progress`; supervisor in `under_review`; employer after `supervisor_approved`).
- **Complexity:** lowest — no grouping map to maintain.
- **Information loss:** NONE.
- **Business risk:** width. 9 columns is wide on desktop; some columns are transient/sparse. Mitigated by scope filters (a supervisor sees only their projects) and collapsible columns.
- **Recommendation: default.**

### Option B — Grouped workflow (7 columns)

```
Backlog(draft) | Assigned(assigned) | In Progress(in_progress) | Review(submitted_for_review + under_review + supervisor_approved) | Needs Rework(needs_rework) | Approved(approved) | Cancelled(cancelled)
```

- **Repository evidence: PARTIAL.** The constituent statuses are real; the *groups* are a UX invention — the repo defines no such grouping.
- **UX advantage:** narrower, less sparse.
- **Complexity:** medium — a status→group map must be kept in sync with the enum (second source of truth).
- **Information loss: MEDIUM.** Which review gate is hidden. `supervisor_approved` is a real compliance milestone (own migration column `supervisor_approved_at`, `2026_09_19_055251:21`; BD-14 makes the supervisor the exclusive final approver) — burying it in "Review" obscures an approval milestone that gates employer action.
- **Business risk:** an employer looking at "Review" cannot tell whether technical approval is done and final approval is now theirs.
- **Recommendation:** only if the Owner explicitly prefers fewer columns.

### Option C — Compact grouped (5 columns)

```
To Do(draft+assigned) | In Progress(in_progress+needs_rework) | Review(submitted+under+supervisor_approved) | Done(approved) | Cancelled(cancelled)
```

- **Repository evidence: PARTIAL — heaviest invention.**
- **UX advantage:** fits most screens.
- **Information loss: HIGH.** Conflates unassigned draft with assigned (contractor loses "is this mine?"); hides `needs_rework` (a quality signal) inside In Progress; merges three approval gates.
- **Business risk: HIGH** — losing the visible rework signal and the approval-gate distinction.
- **Recommendation: NOT recommended.**

### D.1 Intermediate statuses — keep separate

`submitted_for_review`, `under_review`, `supervisor_approved` are **sequential, mandatory, single-path gates** (`submitted_for_review → under_review` is the *only* exit from `submitted`). They map 1:1 onto distinct role permissions under DEC-050 (`technical_approval` = supervisor, `final_approval` = employer): **the column tells you whose action is pending.** Merging them loses actor ownership and the `supervisor_approved_at` compliance milestone. **Repository evidence is sufficient to keep them separate; grouping is an Owner decision.**

### D.2 `needs_rework` — independent, visually distinct column

- Distinct status with canonical red styling (`bg-red-100 text-red-800`, `show.blade.php:36`).
- **Two entry points** (`under_review` *and* `supervisor_approved`) — rework can follow technical approval; not merely early rejection. An independent column preserves that.
- **Rework history/count: NOT directly available.** No `rework_count` on `tasks`; the field exists only on `performance_records` (contractor-level aggregate, `recordPerformance()` has no application caller). Rounds are only *indirectly* derivable from `ActivityLog` (`task_status_changed` with reason) or `Approval` rows — **no accessor exists**.
- **Card must NOT show a rework-count badge** — it would be fabricated. Counting is a deliberate build (Owner decision), not a display.

### D.3 `cancelled` — keep, but filterable

- Terminal with **no un-cancel path**; reachable from only 4 states (not during review).
- No volume evidence (the production DB holds 3 tasks; cancelled volume unknown).
- Dropping it from the board loses management oversight of terminal tasks; keeping it permanently adds a usually-sparse column.
- **Recommendation:** keep as a real column, collapsible/filterable — default-visible for admin/management, hidden for contractor. **Owner decision** on exact treatment.

---

## E. Owner Decisions Required

| # | Decision | Why it matters | Recommended default |
|---|---|---|---|
| 1 | **Board route** | No board route exists today ("board" appears only in a `segmented` test fixture, `UiFoundationTest.php:143`). Placement determines regression risk | **New dedicated route** (`GET /tasks/board`, name `tasks.board`, `auth` only), `tasks.index` table untouched; board/list switcher added later. Replacing `tasks.index` would inherit its stale status-pill bug and decorative filters |
| 2 | **Number of columns** | Determines information density vs. width | **Option A — 9 columns**, 1:1 with `TaskStatus` (zero invented semantics) |
| 3 | **Status mapping** | Any grouping becomes a second source of truth vs. the enum | **No mapping** (Option A). If grouped, Option B never C |
| 4 | **Intermediate statuses** | Hiding gates hides *whose action is pending* (supervisor vs. employer) | **Three separate columns** — matches `technical_approval`/`final_approval` split |
| 5 | **`needs_rework` treatment** | Two entry points; a distinct column preserves the post-technical-approval rework path | **Independent column**, canonical red styling; **no rework-count badge** (no accessor) |
| 6 | **`cancelled` treatment** | Terminal, no return; management oversight vs. clutter | **Keep as column, collapsible/filterable**; hidden for contractor |
| 7 | **Read-only vs interactive** | No `update` ability/policy/route exists; DnD would bypass `TaskStateTransition` | **READ-ONLY.** No DnD, no status/assignment/priority/due-date mutation from the board |
| 8 | **Card click behavior** | Determines the detail surface | **Card → left Task Drawer** (Phase 1 `elements/drawer` shell; shell API ready — `open-drawer.window` event, `:32`). Detail satisfies `TaskPolicy::view`. Drawer content is a **future phase** |
| 9 | **Mobile behavior** | Templates all stack; TMS already uses stacked mobile patterns | **Option B — stacked single column** (matches all three templates + existing mobile sidebar precedent). Horizontal-scroll columns rejected for RTL orientation risk |
| 10 | **Filters** | Data exists for most; code exists for none | **Ship only data-backed filters:** search (title), status, priority, project, mine-toggle. No filter without real data |
| 11 | **Visibility / scope** | Board must not redefine scope | **Reuse the two existing layers exactly** — `ProjectScopeService::applyProjectScope()` + contractor `whereHas`. Zero new permission, zero new scope |
| 12 | **Progress display** | All three templates put progress on the card; TMS has none | **NO progress bar.** Verified absent — a bar would be fabricated |

---

## F. Recommended Initial Board

> ⚠️ **IMPLEMENTATION RECOMMENDATION — NOT YET OWNER-APPROVED**

- **Read-only** — no drag & drop, no status mutation, no assignment/priority/due-date change. Cards are for viewing and navigation only.
- **9 columns, 1:1 with `TaskStatus`** (Option A), reusing the canonical label/color map from `show.blade.php:28-49`.
- **Card shows only real data:** task ID, title, project name, status (canonical styling), priority (`TaskPriority`), assignee via `activeAssignment.user` (shown only when present), `planned_due_at` in Jalali, and — as small count badges — dependency and document counts (relations, no accessors). **No progress bar, no SLA countdown, no rework count, no group chips.**
- **Authorization unchanged:** `auth` route gate; query mirrors `TaskController::index` (`applyProjectScope` + contractor isolation); card-click target satisfies `TaskPolicy::view`. No new permission string, no new ability.
- **Card → left Task Drawer** (Phase 1 shell, `elements/drawer.blade.php`) in the *next* phase — read-only detail reusing `Task`'s existing but currently unrendered relations (assignment history, approvals, comments, activity logs).
- **UI Foundation reuse:** `panel`, `badge`, `status-indicator`, `avatar`, `chip`, `empty-state`, `segmented`, `key-val`, `ring` + the Phase 1 border system and design tokens. No new dependency, no npm install.
- **Known must-fix-at-build (reported, not fixed here):** add `contractor` to eager loads if the card shows contractor; do not inherit `index.blade.php`'s stale status-pill keys; no `lang/` layer exists, so labels must come from a single shared map (consolidate the `show.blade.php` arrays).
- **Architecture proposal (§20 of the brief) is sound** — Board Page → Header (search / filters / view controls) → Board → Column (header + cards) → Card → Task Drawer (future). One clarification: the query/scope layer lives in the controller mirroring `tasks.index`, not in the view.

---

## G. Final Gate

### **READY FOR OWNER DECISION**

Repository evidence is sufficient on every point the board depends on:

- 9 statuses + lifecycle order — **verified** (`TaskStatus.php:7-15`)
- State machine + terminal states — **verified** (`TaskStateTransition.php:10-20`)
- Card data availability — **verified** (B.2), including the decisive negative on progress
- Authorization/scope to reuse — **verified** and already in production (`TaskController:43-50`)
- Filter data support — **verified** (B.3)
- Template UX patterns — **verified** (C)

The only remaining blockers are **Business/UX decisions belonging to the Owner** (E.1–E.12), principally: column count/mapping, `cancelled` treatment, route placement, mobile behavior, and confirmation of the read-only constraint.

**`READY FOR IMPLEMENTATION` is deliberately NOT declared.** Implementation begins only after the Owner reviews and approves the decisions in §E, per the phase contract.

---

## Appendix — Risks & Caveats (reported, zero action taken)

1. `index.blade.php:43-50` uses non-existent status keys (`active`/`completed`) — every task hits the fallback pill. A board must source styling from the canonical map.
2. `TaskController::index` eager-loads `project, wbsPhase, activeAssignment.user` but **not** `contractor` while the view reads `contractor->name` (N+1).
3. `TMS_PROJECT_TRACKER.md §10` transition table is **stale (pre-V1.3)** — it lacks the two-tier `supervisor_approved` path. `TaskStateTransition.php` is authoritative.
4. No `lang/` directory exists — `__('status.*')` resolves to `null`; labels are hardcoded in `show.blade.php` only.
5. `UpdateTaskRequest` is a dead stub (`authorize() → false`, empty rules); `TaskService::update()` exists but is unreachable — the "update not complete" premise of this gate is confirmed.
