# TMS Domain Transaction Conventions

This document outlines the standard boundaries and rules for database transactions within the TMS Service Layer (Phase S1+).

## 1. General Rule
Domain Services MUST own the database transactions for business operations. Controllers or Commands should NOT manage DB transactions directly unless orchestrating multiple distinct services in a rare overarching saga.

## 2. Status & SLA 
Any operation that changes a `Task`'s status AND triggers an SLA start/stop (e.g. `submitForReview`) MUST be wrapped in a single `DB::transaction()`. If the SLA update fails, the status change must rollback.

## 3. Assignment
Assigning a new user to a task MUST be in a single transaction that:
- Sets `ended_at = now()` on the previous active assignment.
- Creates the new `TaskAssignment`.
- Triggers SLA changes if necessary.
- Logs the Audit event.

## 4. Approvals
Recording an `Approval` and modifying the `Task` status (e.g., from `under_review` to `approved`) MUST be executed in the same transaction.

## 5. Weight Changes
Approving a `WeightChangeRequest` and updating the actual `Task->weight` MUST be executed within a single transaction.

## 6. Audit Logging (ActivityLog)
- For critical, finance-related, or state-changing operations, `AuditService::log()` MUST execute synchronously within the parent service's transaction. 
- If the business operation fails and rolls back, the audit log for that specific operation should also roll back (or explicitly log a failure if caught outside).
- Do NOT use asynchronous Queues for core transactional audits unless explicitly required by performance bottlenecks in Phase S2+.

## 7. Non-Transactional Components
- **State Transition Rules** (`TaskStateTransition`) do NOT create transactions. They only assert validity.
- **DTOs** do NOT manage transactions.
- **Access Rules** (`TaskScopeService`) do NOT manage transactions.
