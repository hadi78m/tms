---
trigger: always_on
---

# TMS Project Specific Rules & Priorities

## 1. Project Context Priority
Before executing tasks or making code changes, read context files in this exact order:
1. `TMS_PROJECT_TRACKER.md` (Primary project-status and architecture reference)
2. `project_context/ANTIGRAVITY_HANDOFF.md`
3. `project_context/ANTIGRAVITY_CHANGELOG.md`
4. `project_context/ANTIGRAVITY_DECISIONS.md`
5. `project_context/ANTIGRAVITY_SESSION_LOG.md`
6. `AGENTS.md`
7. `error_log.md`
8. `ACTION_TRACKER.md`
9. `Tasks.md`
10. `Memory.md`

## 2. Role Separation (Multi-Agent Workflow)
- **Architect/Manager Agent:**
  - Manages `TMS_PROJECT_TRACKER.md` and ADRs in `project_context/ANTIGRAVITY_DECISIONS.md`.
  - Verifies test logs and inspects git/changelog status.
  - Prepares next instructions by updating "اقدام بعدی دقیق" and "دستور پیشنهادی برای عامل بعدی" in `project_context/ANTIGRAVITY_HANDOFF.md`.
  - NEVER writes PHP code.

- **Developer Agent:**
  - Reads next instructions from `project_context/ANTIGRAVITY_HANDOFF.md`.
  - Implements application code, tests, and configuration.
  - Documents changes in `project_context/ANTIGRAVITY_CHANGELOG.md` and `project_context/ANTIGRAVITY_SESSION_LOG.md`.
  - NEVER modifies `TMS_PROJECT_TRACKER.md` or business architecture rules independently.

## 3. Task Verification Rule
Repository code and tests must always be executed and checked to verify completion. SQLite memory tests are strictly forbidden; PostgreSQL testing connection must be used.

## Artifacts and Planning Location Rule
- All planning documents, tasks, walkthroughs, and status files MUST be stored inside the repository at `project_context/` or the project root.
- Do NOT save `implementation_plan.md`, `task.md`, or `walkthrough.md` in external global paths (such as `~/.gemini/antigravity-ide/brain/`).
- Standard file locations:
  - Implementation Plans: `project_context/plans/` (or project root `implementation_plan.md`)
  - Execution Walkthroughs: `project_context/walkthroughs/` (or project root `walkthrough.md`)
  - Task Trackers: `project_context/tasks.md`
  - Session Context: `project_context/ANTIGRAVITY_SESSION_LOG.md` and `project_context/ANTIGRAVITY_HANDOFF.md`

