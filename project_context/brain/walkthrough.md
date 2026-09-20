# Walkthrough — TMS V1.7 Stabilization Implementation Phase

## Overview
This phase strictly stabilized the approved TMS V1.7 implementation across Web, Service, and Domain layers based on the recent READ-ONLY audit findings.
**The Weight domain remained completely frozen** (no task_type, no weight nullability changes, no WBS weight changes, no progress formulas, and no connection to performance or finance).

---

## Changes Implemented

### 1. Fix #1: Task Assignment Web Flow
- **DTO Mapping Alignment**: In `app/Http/Requests/Web/AssignTaskRequest.php`, named parameters in `toDto()` were precisely matched with the constructor signature of `App\Domain\DTOs\AssignTaskData`:
  - `task_id` (int)
  - `user_id` (int)
  - `assigned_by` (int)
  - `reason` (?string)
- **Controller Invocation**: In `app/Http/Controllers/Web/TaskAssignmentController.php`, replaced nonexistent method calls with the actual domain service method:
  ```php
  $this->assignmentService->assign($request->toDto(), $user);
  ```
- **Contractor Isolation**: Enforced contractor scope checks in both `AssignTaskRequest::authorize()` and `TaskAssignmentController`.

### 2. Fix #2: Task Submission Web Flow
- **Domain State Machine Alignment**: In `app/Http/Controllers/Web/TaskController.php`, replaced nonexistent `updateStatus()` and invalid `completed` status with the approved domain workflow:
  ```php
  $this->taskService->submitForReview($task, $user);
  ```
- **State Transition**: Safely transitions tasks from `in_progress` to `submitted_for_review`.
- **SLA Integration**: Preserved the automatic resolution SLA stopping behavior via `app(SlaService::class)->stopResolutionSla($task)`.

### 3. Fix #3: SLA Display on Task Details
- **Elimination of Nonexistent Properties**: In `resources/views/tasks/show.blade.php`, removed accesses to nonexistent properties `$task->sla_started_at` and `$task->sla_stopped_at`.
- **Domain Relationship Usage**: Leveraged `$task->slaRecords` eager-loaded collection.
- **Detailed SLA Metrics**: Separated Response SLA (`response`) and Resolution SLA (`resolution`), showing:
  - SLA Type Badge
  - Current status (Active / Stopped / Breached)
  - Started and Stopped timestamps in Persian/Jalali format (`jdate()`)
  - Consumed / Elapsed duration in minutes
  - Target duration in minutes
- **Conditional Submission Button**: Displayed the submission button only when `$task->status === 'in_progress'` and Resolution SLA is not stopped.

### 4. Fix #4: Dependency Removal Conformance to OWASP
- **HTTP Method Conformance**: Replaced `DELETE tasks/{task}/dependencies/{dependency}` with POST route:
  ```php
  Route::post('tasks/{task}/dependencies/{dependency}/remove', [TaskController::class, 'removeDependency'])
      ->name('tasks.dependencies.destroy');
  ```
- **Form Update**: Removed `@method('DELETE')` in `resources/views/tasks/show.blade.php`.
- **Model Event Unblocking**: Removed `static::deleting(function () { return false; });` in `app/Models/TaskDependency.php` which was erroneously blocking Eloquent `$dependency->delete()` calls triggered by `TaskService::removeDependency`.

---

## Test Execution Results

All tests were executed against the project's configured **PostgreSQL** testing environment (`tms_testing`):

```bash
vendor/bin/pest
```

### Results Summary:
- **Total Tests**: 114
- **Passed**: 114 (100% green)
- **Assertions**: 334
- **Failures**: 0
- **Duration**: ~18.6s
- **Database**: PostgreSQL (No SQLite)

### Feature Tests Added in `tests/Feature/Web/WebTaskStabilizationTest.php`:
1. `test_valid_task_assignment_via_web`: Verified draft -> assigned, active assignment record, and SLA initiation.
2. `test_task_reassignment_to_another_user`: Verified previous assignment termination (`ended_at`) and new assignment creation.
3. `test_idempotent_task_assignment`: Verified idempotency when assigning same worker.
4. `test_unauthorized_contractor_cannot_assign_other_contractor_task`: Verified 403 HTTP status for cross-contractor attempts.
5. `test_assign_task_request_to_dto_mapping`: Verified exact DTO constructor argument mapping.
6. `test_contractor_can_submit_eligible_task`: Verified in_progress -> submitted_for_review and SLA resolution stopping.
7. `test_task_submission_rejected_for_invalid_state`: Verified invalid state submissions are rejected.
8. `test_unauthorized_contractor_cannot_submit_task`: Verified contractor isolation on submission (403).
9. `test_sla_records_display_correctly_on_show_page`: Verified rendering of SLA records with Jalali dates and durations.
10. `test_authorized_manager_can_remove_dependency_via_post`: Verified POST dependency removal and DB deletion.
11. `test_contractor_cannot_remove_dependency`: Verified contractor cannot remove dependencies (403).
12. `test_removing_dependency_of_different_task_fails`: Verified 404 when dependency does not belong to task.

---

## Weight Domain Verification
- `tasks.weight`: **UNTOUCHED**
- WBS Weight: **UNTOUCHED**
- `task_type`: **NOT ADDED**
- Progress formulas: **NOT INTRODUCED**
- Performance / Finance connections: **NOT TOUCHED**
