# TMS Project Progress Tracker

**Project:** Task Management System (TMS)  
**Technology:** Laravel 13.31, PostgreSQL target  
**Document purpose:** Single source of progress context for continuing the project across conversations.

---

## 1. How to use this file

- `[x]` = completed and verified.
- `[ ]` = not completed.
- `[~]` = in progress, partially completed, or requires verification.
- Every major change must update this file.
- Do not mark an item complete based only on a design decision; implementation and tests must be verified.
- At the start of a new conversation, provide this file together with the latest agent report and relevant error log.

### Update convention

For each completed item, record:
- Date
- Files changed
- Tests run
- Result
- Remaining issues, if any

---

# 2. Project Mission and Scope

## Objective

Build a dedicated TMS for operational management of tasks associated with contracts.

The existing contract/system application remains the financial and contractual Source of Truth.

TMS is responsible for:

- Project/task operational management
- WBS phases for development work
- Task assignment
- Contractor/operator interaction
- Review and approval history
- SLA tracking
- Audit history for important and finance-related changes
- Performance snapshots
- Document attachment and history
- Management dashboards and configurable visibility

## Explicitly out of scope for V1

- [x] No Gantt management.
- [x] No RFP management.
- [x] No person-hour-based calculation.
- [x] No financial payment formula.
- [x] No independent contract source of truth.
- [x] No contractor-created tasks.
- [x] No contractor final approval.

---

# 3. Architecture Decisions

## Core entity flow

```text
SyncedContract
      |
      v
Project
      |
      v
WBS Phase (development only)
      |
      v
Task
```

Support tasks may be attached directly to Project without a WBS Phase.

## Important decisions

- [x] One Project per Contract: `projects.contract_id` is unique.
- [x] Project is a management container, not an independent business project.
- [x] Project code removed; Project name retained for display/internal use.
- [x] Development tasks may have a WBS Phase.
- [x] Support tasks may have `wbs_phase_id = null`.
- [x] Every Task belongs to one Contract context through its Project.
- [x] Controlled denormalization exists for contract and contractor references.
- [x] Main application is the contractual/financial Source of Truth.
- [x] TMS records operational events and finance-related audit information.
- [x] No direct financial calculation in V1.

---

# 4. Technology and Environment

## Target stack

- [x] Laravel 13.31.
- [~] PostgreSQL target database.
- [~] PostgreSQL test environment.
- [~] MySQL was considered for future infrastructure, but current V1.7 design uses PostgreSQL-specific features.
- [x] Eloquent models created.
- [x] Migrations created.
- [~] Full migration execution against a live PostgreSQL instance not yet verified.
- [~] Tests must not silently depend on SQLite because migrations use PostgreSQL-specific features.

## PostgreSQL-specific features

- Identity primary keys
- JSONB
- PostgreSQL regular-expression CHECK constraints
- Partial unique index for active assignments
- TIMESTAMPTZ

---

# 5. Completed Foundation Work

## Discovery and documentation

- [x] Repository discovery completed.
- [x] Initial architecture and business requirements documented.
- [x] Database V1.7 designed.
- [x] Model Layer designed and implemented.
- [x] Model Layer audit completed.
- [x] Service Layer architecture blueprint reviewed.
- [~] Service Layer blueprint requires the corrections listed in Section 8.

## Existing project notes

- [x] `AGENTS.md` identified as an important repository instruction file.
- [x] `Tasks.md` identified as project task tracking.
- [x] `error_log.md` identified as project error history.
- [x] `ACTION_TRACKER.md` identified as project action tracking.
- [~] This file should become the cross-conversation master tracker.

---

# 6. Database V1.7 Status

## Migrations created

- [x] `synced_systems`
- [x] `synced_contractors`
- [x] `users`
- [x] `synced_contracts`
- [x] `projects`
- [x] `wbs_phases`
- [x] `tasks`
- [x] `weight_change_requests`
- [x] `task_assignments`
- [x] `task_dependencies`
- [x] `documents`
- [x] `comments`
- [x] `approvals`
- [x] `sla_records`
- [x] `sla_events`
- [x] `performance_records`
- [x] `activity_logs`

## Database rules

- [x] Global primary keys use BIGINT identity.
- [x] Operational timestamps use TIMESTAMPTZ.
- [x] Calendar periods use DATE where defined by V1.7.
- [x] Most foreign keys use `ON UPDATE NO ACTION` and `ON DELETE RESTRICT`.
- [x] Parent task/comment references may use SET NULL where required.
- [x] History/audit records are not cascade-deleted.
- [x] Active assignment has a partial unique index.
- [x] WBS phase total weight of 100 is an application/service rule.
- [x] Task weight is constrained to 0–100.
- [x] Performance completed weight is constrained to 0–100.
- [x] Performance records have unique contract/contractor/period boundaries.
- [x] Synced entities preserve TMS history when source records are deleted.
- [x] Document checksum is indexed but not globally unique.
- [~] Live PostgreSQL migration verification remains outstanding.

---

# 7. Eloquent Model Layer Status

## Models created

- [x] `SyncedSystem`
- [x] `SyncedContractor`
- [x] `User`
- [x] `SyncedContract`
- [x] `Project`
- [x] `WbsPhase`
- [x] `Task`
- [x] `WeightChangeRequest`
- [x] `TaskAssignment`
- [x] `TaskDependency`
- [x] `Document`
- [x] `Comment`
- [x] `Approval`
- [x] `SlaRecord`
- [x] `SlaEvent`
- [x] `PerformanceRecord`
- [x] `ActivityLog`

## Model rules verified

- [x] Models use guarded primary key strategy.
- [x] Required SoftDeletes are present.
- [x] Date, datetime, decimal, integer, JSON and hashed password casts are defined where needed.
- [x] User uses Spatie Permission's `HasRoles`.
- [x] User username is synchronized with national code through the Eloquent saving path.
- [x] Task relationships cover Project, WBS Phase, Contract, Contractor, creator, parent/children, assignments, comments, documents, approvals, SLA, weight requests and dependencies.
- [x] Immutable-history models disable normal update/delete paths through model behavior.
- [~] Direct SQL/query-builder writes can bypass Eloquent hooks and immutability protections; this limitation must remain documented.
- [~] Morph Map decision is outstanding.

## User identity rules

- [x] National code: exactly 10 digits.
- [x] Username: exactly 10 digits.
- [x] Username equals national code.
- [x] Mobile: exactly 11 digits.
- [x] Email may be nullable.
- [x] No fixed `user_type` or `role_type`.
- [x] Roles and permissions are configurable through Spatie Permission.

---

# 8. Service Layer Blueprint — Required Corrections

The initial Service Layer design exists, but the following corrections are mandatory before implementation is considered complete.

## Authorization and scope

- [ ] Enforce business authorization inside Services, not only in Controllers.
- [ ] Keep Policies/Middleware for entry-point authorization.
- [ ] Add shared Contractor Scope checks.
- [ ] Prevent a contractor from accessing another contractor's tasks.
- [ ] Prevent a non-assigned contractor from performing assigned-contractor actions.
- [ ] Do not use fixed `user_type` logic.

## State Machine

- [ ] Create a central Task State Machine/State Rule.
- [ ] Define all allowed transitions.
- [ ] Reject illegal transitions through a Domain Exception.
- [ ] Prevent transitions from terminal states unless an explicit future rule is approved.
- [ ] Prevent scattered direct status assignments.

## SLA

- [ ] Define the official meaning of a valid contractor response.
- [ ] Make Response SLA start on assignment.
- [ ] Make Resolution SLA start on assignment.
- [ ] Stop Response SLA only on the first valid contractor response.
- [ ] Stop Resolution SLA exactly at `submitted_for_review`.
- [ ] Make SLA stop/start idempotent and race-safe.

## Transactions

- [ ] Assignment rotation, SLA changes and Audit must share a transaction.
- [ ] Approval creation and Task status change must share a transaction.
- [ ] Weight request creation and Audit must share a transaction.
- [ ] Weight request approval, Task weight update and Audit must share a transaction.
- [ ] Important Audit failure must roll back the business operation.

## Audit

- [ ] Keep important Audit events synchronous in V1.
- [ ] Do not silently swallow failures for finance-related changes.
- [ ] Prevent sensitive data from entering Audit metadata.
- [ ] Keep ActivityLog immutable.

## Concurrency

- [ ] Lock Task and active Assignment rows during reassignment.
- [ ] Use the database unique constraint as a second line of protection.
- [ ] Lock pending WeightChangeRequest rows during review.
- [ ] Translate database unique violations into Domain Exceptions.

## Documents

- [ ] Design temporary/permanent file storage flow.
- [ ] Handle orphan files if DB commit fails.
- [ ] Audit soft deletion of documents.
- [ ] Define cleanup strategy for temporary/orphan files.

## Morph Map

- [ ] Decide whether to enforce a Morph Map.
- [ ] If selected, define the allowed aliases and register them centrally.

## PostgreSQL testing

- [ ] Configure a real PostgreSQL test environment.
- [ ] Run migrations against PostgreSQL.
- [ ] Do not make PostgreSQL migrations SQLite-compatible merely to make tests pass.

---

# 9. Service Layer Components

## Planned services

- [ ] `TaskService`
- [ ] `TaskAssignmentService`
- [ ] `ApprovalService`
- [ ] `WeightChangeRequestService`
- [ ] `SlaService`
- [ ] `AuditService`
- [ ] `PerformanceRecordService`
- [ ] `DocumentService`

## Additional supporting components

- [ ] `TaskStateService` or equivalent central State Machine.
- [ ] `TaskScopeService` or equivalent Contractor Scope Rule.
- [ ] Domain Exceptions.
- [ ] DTOs for service inputs.
- [ ] Optional Rule objects for weight and valid contractor response.

---

# 10. Task Statuses and Business Rules

## Statuses

- [x] `draft`
- [x] `assigned`
- [x] `in_progress`
- [x] `submitted_for_review`
- [x] `under_review`
- [x] `approved`
- [x] `needs_rework`
- [x] `cancelled`

## Proposed transition table — pending final confirmation

| Current | Allowed next status |
|---|---|
| draft | assigned, cancelled |
| assigned | in_progress, cancelled |
| in_progress | submitted_for_review, cancelled |
| submitted_for_review | under_review |
| under_review | approved, needs_rework |
| needs_rework | in_progress, cancelled |
| approved | none |
| cancelled | none |

- [ ] Confirm whether this transition table is final.
- [ ] Confirm whether managers can cancel from additional statuses.
- [ ] Confirm whether approved tasks can ever be reopened.
- [ ] Confirm whether a task may move directly from draft to in_progress.
- [ ] Confirm whether under_review is entered automatically or explicitly.

---

# 11. Assignment Rules

- [x] Only one active assignment per Task.
- [x] Previous active assignment receives `ended_at`.
- [x] New assignment receives a permanent record.
- [x] Contractor cannot create Tasks.
- [~] Assignment race-condition handling still needs implementation.
- [~] Same-assignee idempotency behavior needs final confirmation.
- [ ] Confirm whether reassigning the same active user creates no new record and no duplicate SLA/Audit.
- [ ] Confirm whether a changed assignment reason should create a separate event.

---

# 12. Approval Rules

- [x] Approval is historical and separate from Task Status.
- [x] Approval records are immutable.
- [x] Contractor cannot perform Final Approval.
- [~] Technical Approval status effects need final definition.
- [ ] Define whether Technical Approval is mandatory before Final Approval.
- [ ] Define whether multiple Technical Approvals are allowed.
- [ ] Define whether Final Approval can be recorded more than once.
- [ ] Define whether Approval is forbidden after Task becomes approved.
- [ ] Define exact roles/permissions allowed for Final Approval.

---

# 13. Weight Change Rules

- [x] Direct weight changes after assignment are not allowed.
- [x] Weight changes use a request workflow.
- [x] Only one Pending request should exist per Task.
- [x] Weight must remain between 0 and 100.
- [~] Transaction and row-lock implementation remains.
- [ ] Define who may request a weight change.
- [ ] Define who may approve/reject a weight change.
- [ ] Define whether weight changes are allowed after `submitted_for_review`.
- [ ] Define whether a rejected request can be resubmitted.

---

# 14. SLA Rules

## Response SLA

- [x] Starts when Task is assigned.
- [x] Uses minutes as the duration unit.
- [~] Valid response definition pending confirmation.
- [ ] Record only the first valid contractor response.
- [ ] Ignore responses from non-assigned contractors.
- [ ] Ignore operator/supervisor actions as contractor responses.
- [ ] Ensure duplicate response events are not recorded.

## Resolution SLA

- [x] Starts when Task is assigned.
- [x] Stops exactly at `submitted_for_review`.
- [x] Excludes review and approval time.
- [~] Implementation remains.
- [ ] Define pause/resume rules, if any, before implementing them.

---

# 15. Audit Requirements

The following events must be auditable:

- [ ] `task_created`
- [ ] `task_updated_sensitive_field`
- [ ] `task_assigned`
- [ ] `task_unassigned`
- [ ] `task_status_changed`
- [ ] `task_submitted_for_review`
- [ ] `task_approval_recorded`
- [ ] `task_final_approved`
- [ ] `task_weight_change_requested`
- [ ] `task_weight_changed`
- [ ] `document_deleted`
- [ ] Manual Project change
- [ ] Manual WBS Phase change

## Audit policy

- [ ] Audit important business events synchronously.
- [ ] Roll back important business transactions if Audit fails.
- [ ] Do not store passwords, tokens or unnecessary sensitive data.
- [ ] Include actor, timestamp, entity, old values, new values and relevant metadata.
- [ ] Prevent updates/deletes to ActivityLog through normal application paths.

---

# 16. Dashboards and Configurable Visibility

Initial dashboard concepts:

- [x] Employer/Beneficiary dashboard.
- [x] Contractor dashboard.
- [x] Supervisor dashboard.
- [x] Management dashboard.

Rules:

- [x] Dashboards are conceptual information views, not fixed user types.
- [x] Visibility and widgets must be controlled by Roles, Permissions and Settings.
- [ ] Define dashboard widgets.
- [ ] Define dashboard metrics.
- [ ] Define report access by Permission.
- [ ] Define configurable visibility settings.

---

# 17. Implementation Roadmap

## Phase S0 — Foundation

- [x] Inspect current repository before changes.
- [x] Create/verify Domain Enums.
- [x] Create/verify Domain Exceptions.
- [x] Create `CreateTaskData`.
- [x] Create central Task State Rule/State Machine.
- [x] Create Contractor Scope Rule/Service.
- [x] Define `AuditServiceInterface` or equivalent contract.
- [x] Document Transaction conventions.
- [x] Add Unit Tests for Foundation.
- [x] Verify PostgreSQL test-environment status.

## Phase S1 — Task Core

- [x] Implement `TaskStateService`.
- [x] Implement `TaskService::create`.
- [x] Implement controlled Task update.
- [x] Implement `submitForReview`.
- [x] Implement cancellation rules.
- [x] Add Task Service tests.

## Phase S2 — Assignment

- [ ] Implement `TaskAssignmentService`.
- [ ] Implement active-assignment rotation.
- [ ] Implement idempotency.
- [ ] Add row locking.
- [ ] Integrate SLA start.
- [ ] Add Audit.
- [ ] Add concurrency tests.

## Phase S3 — Approval and Weight

- [x] Implement `ApprovalService`.
- [x] Implement Final Approval restriction.
- [x] Implement Technical Approval rules.
- [x] Implement `WeightChangeRequestService`.
- [x] Implement pending-request locking.
- [x] Add Audit.
- [x] Add transaction tests.

## Phase S4 — SLA

- [x] Implement `SlaService`.
- [x] Implement valid contractor response detection.
- [x] Implement Response SLA stop.
- [x] Implement Resolution SLA stop at submission.
- [x] Implement breach calculation.
- [x] Add idempotency tests.
- [x] Add timing tests.

## Phase S5 — Supporting Services

- [x] Implement `PerformanceRecordService`.
- [x] Implement duplicate-period handling.
- [x] Implement `DocumentService`.
- [x] Implement file checksum.
- [x] Implement temporary/permanent storage handling.
- [x] Implement document deletion audit.

## Phase S6 — Integration and Quality

- [x] Run complete PostgreSQL migrations.
- [x] Run Feature Tests.
- [x] Run Permission Tests.
- [x] Run Contractor Isolation Tests.
- [x] Run Transaction Rollback Tests.
- [x] Run Unique Constraint Tests.
- [x] Run Immutable Record Tests.
- [x] Review all unresolved decisions.
- [x] Update this tracker.

## Phase V1.1 — UI & Presentation Layer (Blade & Tailwind CSS)

- [x] Implement custom `AuthController` (10-digit National Code / Username login & logout).
- [x] Implement Web Controllers: `TaskController`, `TaskAssignmentController`, `ApprovalController`, `DocumentController`, `DashboardController`.
- [x] Implement FormRequests with automatic validation and conversion to Domain DTOs (`StoreTaskRequest`, `AssignTaskRequest`, `SubmitApprovalRequest`, `UploadDocumentRequest`).
- [x] Publish and execute Spatie permissions migrations (`roles`, `permissions`, etc.) on PostgreSQL.
- [x] Define Web Routes with permission middlewares and role separation.
- [x] Design modern RTL Blade views with Tailwind CSS (`layouts/app`, `tasks/index`, `tasks/create`, `tasks/show`, `dashboard`).
- [x] Add Web Feature tests (`WebAuthControllerTest`, `WebTaskControllerTest`).
- [x] Run complete test suite on PostgreSQL test environment (64 tests, 170 assertions, 100% green).

## Phase V1.2 — User & Access Management

- [x] Configure Spatie Middleware Aliases in `bootstrap/app.php`.
- [x] Implement `StoreUserRequest` and `UpdateUserRequest` (10-digit national code, 11-digit mobile, username sync, Spatie roles).
- [x] Implement `UserController` in `app/Http/Controllers/Web/UserController.php`.
- [x] Register Admin-protected resource routes in `routes/web.php`.
- [x] Design Blade views (`users/index`, `users/create`, `users/edit`) with Tailwind CSS and RTL.
- [x] Add Users Management link to Sidebar in `layouts/app.blade.php` for Admin.
- [x] Add Web Feature tests (`WebUserControllerTest`).
- [x] Run full test suite on PostgreSQL (69 tests, 195 assertions, 100% green).

## Phase V1.3 — Two-Tier Approval & Employer Workflow

- [x] Add 4 new Spatie roles: `employer`, `project_manager`, `management`, `viewer` to `InitialTmsSeeder`.
- [x] Add `TaskStatus::SupervisorApproved` enum case.
- [x] Update `TaskStateTransition`: `under_review → supervisor_approved → approved`.
- [x] Create migration `add_supervisor_approved_status_to_tasks` (adds `supervisor_approved_at` column).
- [x] Refactor `ApprovalService` with `recordTechnicalApproval()` (supervisor) and `recordFinalApproval()` (employer).
- [x] Update `ApprovalServiceTest` to match new state machine.
- [x] Add `TwoTierApprovalWorkflowTest` (6 tests, all green).
- [x] Run full PostgreSQL test suite — 100% green.

## Phase V1.4 — Evidence Hashing & Claim Tracking

- [x] **Database Schema**: Add `file_hash`, `claimed_at`, `verified_at`, `recorded_at` to `documents` table.
- [x] **Domain Service**: Update `DocumentService` and `TaskService` to capture SHA-256 hash and timestamps.
- [x] **Web View**: Modify `tasks/show.blade.php` to include `claimed_at` input and show system/claim time difference.
- [x] **Testing**: Comprehensive tests for Hashing and Timestamp isolation.

## Phase V1.5 — Subtasks & Dependency Blocking

- [x] Implement subtask creation and parent-child validation.
- [x] Implement dependency-blocking: task cannot move to `in_progress` if a blocking dependency is incomplete.
- [x] Add UI for dependency graph.
- [x] Add Feature tests.

## Phase V1.6 — Dynamic Settings & Export Reports

- [x] Implement configurable system settings (dashboard widgets, visibility rules).
- [x] Implement PDF/Excel export for task lists and performance reports.
- [x] Role-based report access control.
- [x] Add Feature tests.

## Phase V1.7 — Jalali/Persian Date Integration

- [x] Implement `JalaliDate` utility and `SafeJalali` decorator in `app/Support/JalaliDate.php` (null-safe formatting returning `-`, Persian/Arabic digit normalization, year range detection).
- [x] Create global helper functions `jdate()`, `to_jalali()`, and `jalali_to_gregorian()` in `app/Support/helpers.php` (registered via `composer.json` and `AppServiceProvider`).
- [x] Integrate `persianDatepicker` CSS and JS in `resources/views/layouts/app.blade.php` for `.datedown` and `.datetop` input classes.
- [x] Convert task creation dates (`planned_start_date`, `planned_due_date`) and document upload date (`claimed_at`) to Jalali datepicker inputs with Persian placeholders and calendar icons.
- [x] Display formatted Jalali dates across task tables (`tasks/index.blade.php`), task details view (`tasks/show.blade.php`), and CSV export files (`ReportController.php`).
- [x] Add automated conversion of incoming Jalali dates to Gregorian in `StoreTaskRequest` and `StoreDocumentRequest` via `prepareForValidation()`.
- [x] Implement unit tests in `tests/Unit/JalaliDateTest.php` (7 tests) and feature tests in `tests/Feature/Web/WebTaskJalaliDateTest.php` (5 tests).
- [x] Run and verify full PostgreSQL test suite: 102 tests, 281 assertions, 100% green.

---

# 18. Current Next Action

**Current phase: Completed V1.7 (Jalali/Persian Date Integration)**

Immediate next action:

- [x] Phase V1.4: Evidence Hashing & Claim Tracking.
- [x] Phase V1.5: Subtasks & Dependency Blocking.
- [x] Phase V1.6: Dynamic Settings & Export Reports.
- [x] Phase V1.7: Jalali/Persian Date Integration (102 tests, 281 assertions — 100% green).
- [ ] Preparation for Production Deployment and final system hardening.

---

# 19. Open Decisions Register

| ID | Decision | Status |
|---|---|---|
| D-01 | Final Task State Transition table | Resolved (V1.3) |
| D-02 | Definition of valid contractor response | Pending |
| D-03 | Technical Approval effect on status | Resolved (V1.3 — supervisor_approved) |
| D-04 | Final Approval permissions | Resolved (employer only, V1.3) |
| D-05 | Whether approved Tasks can reopen | Pending |
| D-06 | Synchronous Audit for important events | Proposed |
| D-07 | Document temporary/permanent storage strategy | Pending |
| D-08 | Morph Map | Pending |
| D-09 | PostgreSQL test environment | Resolved |
| D-10 | Same-assignee Assignment idempotency details | Pending |
| D-11 | SLA pause/resume business rules | Pending |
| D-12 | Dashboard widgets and configurable visibility | Resolved (V1.1) |

---

# 20. Change Log

| Date | Change |
|---|---|
| 2026-09-18 | Created this master project tracker from the existing TMS architecture, database, model and Service Layer decisions. |
| 2026-09-18 | Marked Service Layer as requiring corrections before implementation. |
| 2026-09-18 | Set current work phase to S0 — Foundation. |
| 2026-09-18 | Completed Phase S0 Foundation (Enums, Rules, DTOs, Exceptions). |
| 2026-09-18 | Created permanent tracking and context files in `project_context/`. |
| 2026-09-18 | Completed Phase S1 (Task Core) and configured PostgreSQL tests. Moved to Phase S2. |
| 2026-09-18 | Completed Phase S2 (Assignment) and S4 (SLA) with Feature tests on PostgreSQL. |
| 2026-09-18 | Completed Phase S3 (Approval and Weight) with Feature tests, Row Locking, and Audit transactions. |
| 2026-09-18 | Completed Phase S5 (Supporting Services) and successfully ran all 55 Feature tests. Moved to Phase S6. |
| 2026-09-18 | Completed Phase S6 (Integration and Quality) - 100% tests passed. Marked project as Completed V1. |
| 2026-09-18 | Completed Phase V1.1 (UI & Presentation Layer): Custom Auth, Web Controllers, FormRequests, Blade/Tailwind RTL views, Spatie migrations, and 100% green test suite (64 tests, 170 assertions). |
| 2026-09-18 | Completed Phase V1.2 (User & Access Management): Admin UserController, FormRequests, Blade RTL management views, Spatie role guard middleware, WebUserControllerTest, and 100% green test suite (69 tests, 195 assertions). |
| 2026-09-19 | Completed Phase V1.3 (Two-Tier Approval & Employer Workflow): Added 4 new Spatie roles (employer, project_manager, management, viewer), SupervisorApproved status enum case, state machine update, supervisor_approved_at migration, refactored ApprovalService with separate tier methods, updated ApprovalServiceTest, added TwoTierApprovalWorkflowTest (6 tests green). Registered Phases V1.3–V1.6 in roadmap. |
| 2026-09-19 | Completed Phase V1.4 (Evidence Hashing & Claim Tracking): Added hash and timestamp fields to documents, implemented hashing in DocumentService, updated Blade view, comprehensive testing. |
| 2026-09-19 | Completed Phase V1.5 (Subtasks & Dependency Blocking): Enabled subtasks via parent_task_id, enforced blocking rules via fs TaskDependency and TaskBlockedException, created TaskDependencyTest (green). |
| 2026-09-19 | Completed Phase V1.6 (Dynamic Settings & Export Reports): Added system_settings schema, SettingsService, UI settings and reports dashboards, CSV streamed export for reports, and tested successfully (90 tests passing). |
| 2026-09-20 | Completed Phase V1.7 (Jalali/Persian Date Integration): Implemented SafeJalali decorator and JalaliDate converter in app/Support, global helpers jdate() & to_jalali(), Persian Datepicker integration in Blade layout, converted task & document forms to Jalali inputs, added automatic conversion in StoreTaskRequest & StoreDocumentRequest, updated ReportController CSV export to Jalali, created JalaliDateTest and WebTaskJalaliDateTest (100% green: 102 tests, 281 assertions). |

---

# 21. Agent Handoff Checklist

When giving this project to an agent in a new conversation, provide:

- [ ] This file.
- [ ] `AGENTS.md`.
- [ ] Latest `Tasks.md`.
- [ ] Latest `error_log.md`.
- [ ] Latest `ACTION_TRACKER.md`.
- [ ] Latest architecture/design documents.
- [ ] Exact requested phase and allowed scope.

The agent must:

1. Read the tracker first.
2. Inspect the repository.
3. Identify completed versus unverified work.
4. Avoid repeating completed work.
5. Avoid making unapproved business decisions.
6. Update the tracker only after verified changes.
7. Report tests and remaining issues.
