<?php

namespace App\Http\Controllers\Web;

use App\Domain\Enums\TaskPriority;
use App\Domain\Exceptions\TaskBlockedException;
use App\Domain\Exceptions\UnauthorizedTaskOperationException;
use App\Domain\Rules\ProjectScopeService;
use App\Domain\Services\DocumentService;
use App\Domain\Services\TaskService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\AddDependencyRequest;
use App\Http\Requests\Web\SetTaskStageRequest;
use App\Http\Requests\Web\StoreTaskRequest;
use App\Models\Module;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\WbsPhase;
use App\Support\TaskBoard\BoardColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function __construct(
        protected TaskService $taskService,
        protected DocumentService $documentService,
        protected ProjectScopeService $projectScope
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();

        // Basic query
        $query = Task::with(['project', 'wbsPhase', 'activeAssignment.user']);

        // V1.11 (DEC-049 I-3 · DR-TASK-02=A): Project Scope retrieval filter —
        // supervisors see only tasks of projects with an ACTIVE supervisor
        // membership; admin/management unrestricted (scope only); PM/viewer
        // Q1-preserved (null = untouched). Contractor filter below stays
        // ADDITIVE — the two layers compose, neither replaces the other.
        $query = $this->projectScope->applyProjectScope($query, $user);

        // Contractor isolation: if user is a contractor, only show their tasks
        if ($user->contractor_id) {
            $query->whereHas('project', function ($q) use ($user) {
                $q->where('contractor_id', $user->contractor_id);
            });
        }

        // ---- Filters (Phase 5B-1): real, server-side, deep-linkable ----
        // Same semantics as the Phase 3 Board — shared, not duplicated.
        $filters = $this->applyTaskFilters($query, $request);

        $tasks = $query->latest()->paginate(15)->withQueryString();

        $projects = $this->projectScope
            ->applyProjectScope(Project::query(), $user, 'id')
            ->orderBy('name')
            ->pluck('name', 'id');

        return view('tasks.index', compact('tasks', 'projects', 'filters'));
    }

    /**
     * Phase 5B-1 — Task Index filters (real, server-side).
     *
     * The filtering SEMANTICS are taken verbatim from the established Phase 3
     * Board query ({@see self::board()}) — nothing is invented here:
     *   - search   → tasks.title (ILIKE, trimmed)
     *   - project  → tasks.project_id
     *   - status   → the REAL domain status, never the presentation group
     *   - priority → a TaskPriority enum value
     *
     * Authorization stays the caller's responsibility: this only narrows an
     * already-scoped query, so it can never widen a result set.
     *
     * @param  Builder<Task>  $query
     * @return array{search: string|null, project: string|null, status: string|null, priority: string|null}
     */
    private function applyTaskFilters($query, Request $request): array
    {
        if ($search = $request->query('search')) {
            $query->where('title', 'ilike', '%'.trim((string) $search).'%');
        }

        if ($project = $request->query('project')) {
            $query->where('project_id', (int) $project);
        }

        // Status filters on the REAL status — never the presentation group.
        if ($status = $request->query('status')) {
            if (in_array($status, BoardColumns::filterableStatuses(), true)) {
                $query->where('status', $status);
            }
        }

        if ($priority = $request->query('priority')) {
            if (in_array($priority, array_column(TaskPriority::cases(), 'value'), true)) {
                $query->where('priority', $priority);
            }
        }

        return [
            'search' => $request->query('search'),
            'project' => $request->query('project'),
            'status' => $request->query('status'),
            'priority' => $request->query('priority'),
        ];
    }

    /**
     * Phase 3 — Read-only Kanban Board (a second view over tasks.index data).
     *
     * This is a PRESENTATION layer only (gate §27): query + filtering +
     * presentation mapping. No status transition, no assignment, no SLA, no
     * progress, no permission calculation — business rules stay in the
     * Domain/Policy/Service layers untouched.
     *
     * Authorization reuses the existing surfaces VERBATIM (gate §4):
     *   - ProjectScopeService::applyProjectScope  (same as tasks.index)
     *   - actor-side contractor isolation          (same as tasks.index)
     * No policy is added, no permission is created, nothing is edited.
     */
    public function board(Request $request)
    {
        $user = Auth::user();

        $query = Task::with([
            'project',
            'moduleStage',
            'activeAssignment.user',
        ])->withCount([
            'documents',
            'dependenciesAsSuccessor',
        ]);

        // Same canonical Project Scope as tasks.index — never re-implemented.
        $query = $this->projectScope->applyProjectScope($query, $user);

        // Same contractor isolation as tasks.index — additive, unchanged.
        if ($user->contractor_id) {
            $query->whereHas('project', function ($q) use ($user) {
                $q->where('contractor_id', $user->contractor_id);
            });
        }

        // ---- Filters (gate §14/§15): real, server-side, deep-linkable ----
        // Shared with tasks.index through applyTaskFilters() — one definition.
        $filters = $this->applyTaskFilters($query, $request);

        // Ordering: planned_due_at ascending, NULLs last (PostgreSQL
        // convention — the column is nullable and the DB is pgsql per
        // phpunit.xml / project rule §3). No urgency/SLA sorting is invented.
        $query->orderByRaw('planned_due_at IS NULL, planned_due_at ASC, id ASC');

        $tasks = $query->paginate(50)->withQueryString();

        // Group the (already scope+filter refined) collection by canonical
        // board column. Dataset is page-bounded, so this PHP grouping is
        // bounded and evidence-based (gate §26).
        $grouped = collect(BoardColumns::COLUMNS)
            ->keys()
            ->mapWithKeys(fn (string $key) => [$key => collect()])
            ->all();

        foreach ($tasks as $task) {
            $column = BoardColumns::columnOf($task->status);
            if ($column !== null) {
                $grouped[$column]->push($task);
            }
        }

        $projects = $this->projectScope
            ->applyProjectScope(Project::query(), $user, 'id')
            ->orderBy('name')
            ->pluck('name', 'id');

        return view('tasks.board', [
            'tasks' => $tasks,
            'grouped' => $grouped,
            'projects' => $projects,
            'filters' => $filters,
            'boardColumns' => BoardColumns::COLUMNS,
        ]);
    }

    public function create(Request $request)
    {
        $projects = Project::all();
        $wbsPhases = WbsPhase::all();
        $parentId = $request->query('parent_id');
        $modules = Module::with('stages')->get();

        return view('tasks.create', compact('projects', 'wbsPhases', 'parentId', 'modules'));
    }

    public function store(StoreTaskRequest $request)
    {
        // V1.11 (DEC-055 · I-2): resource authorization = permission
        // `create tasks` AND Project Scope AND actor contractor isolation —
        // TaskPolicy::create. Business invariants remain in TaskService.
        $targetProject = Project::findOrFail($request->input('project_id'));
        $this->authorize('create', [Task::class, $targetProject]);

        $task = $this->taskService->create(
            $request->toDto(),
            Auth::user()
        );

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $this->documentService->uploadDocument(
                $task,
                $file,
                Auth::user()
            );
        }

        return redirect()->route('tasks.index')->with('status', 'وظیفه جدید با موفقیت ایجاد شد.');
    }

    public function show(Request $request, Task $task)
    {
        $user = Auth::user();

        // Contractor isolation check
        if ($user->contractor_id && $task->contractor_id !== $user->contractor_id) {
            abort(403, 'شما دسترسی به این وظیفه ندارید.');
        }

        // V1.11 (DEC-049 I-3): direct-ID enumeration protection — Project
        // Scope is enforced via TaskPolicy::view (ProjectScopeService), so a
        // supervisor cannot reach another project's task by guessing the URL.
        // Same canonical source as the index query — no divergent logic.
        $this->authorize('view', $task);

        // Phase 4 — Read-only Task Drawer (Drawer Gate §10/§29/§30).
        // The drawer REUSES this exact route, controller and authorization as
        // its sole data source: no second endpoint, no second authorization
        // layer, no duplicated business rules. When requested with
        // ?view=drawer it returns ONLY the read-only drawer presentation of
        // the SAME authorized task — strictly a subset of the full detail
        // page: no forms, no mutations, no action surface.
        if ($request->query('view') === 'drawer') {
            $task->load([
                'project',
                'contractor',
                'moduleStage',
                'activeAssignment.user',
                'wbsPhase',
                'slaRecords',
                'dependenciesAsSuccessor.predecessor',
                'approvals.actor',
                'activityLogs.user',
                'documents',
            ]);

            return view('components.tasks.task-drawer-content', compact('task'));
        }

        $task->load(['project', 'contractor', 'documents', 'activeAssignment.user', 'wbsPhase', 'slaRecords']);

        return view('tasks.show', compact('task'));
    }

    /**
     * V1.10 — DEC-042/043: stage reassignment endpoint (supervisor only).
     * All business rules (role, timing, consistency, audit) live in
     * TaskService::assignStage; the controller only delegates.
     */
    public function setStage(Task $task, SetTaskStageRequest $request)
    {
        $user = Auth::user();

        if ($user->contractor_id && $task->contractor_id !== $user->contractor_id) {
            abort(403, 'شما دسترسی به این وظیفه ندارید.');
        }

        // V1.11 (I-3): Project Scope for stage management (role mw + service
        // invariants unchanged — the policy adds scope ONLY).
        $this->authorize('manageStages', $task);

        try {
            $this->taskService->assignStage($task, $request->toStageId(), $user);

            return redirect()->route('tasks.show', $task->id)->with('status', 'مرحلهٔ وظیفه با موفقیت به‌روزرسانی شد.');
        } catch (UnauthorizedTaskOperationException|\InvalidArgumentException $e) {
            return redirect()->route('tasks.show', $task->id)->with('error', $e->getMessage());
        }
    }

    public function start(Request $request, Task $task)
    {
        $user = Auth::user();

        if ($user->contractor_id && $task->contractor_id !== $user->contractor_id) {
            abort(403, 'شما دسترسی به این وظیفه ندارید.');
        }

        // V1.11 (I-3): Project Scope for starting a task.
        $this->authorize('startProgress', $task);

        try {
            $this->taskService->startProgress($task, $user);

            return redirect()->route('tasks.show', $task->id)->with('status', 'اجرای وظیفه آغاز شد.');
        } catch (TaskBlockedException $e) {
            return redirect()->route('tasks.show', $task->id)->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->route('tasks.show', $task->id)->with('error', 'خطایی در شروع وظیفه رخ داد.');
        }
    }

    public function submit(Request $request, Task $task)
    {
        $user = Auth::user();

        // Contractor isolation check
        if ($user->contractor_id && $task->contractor_id !== $user->contractor_id) {
            abort(403, 'شما دسترسی به این وظیفه ندارید.');
        }

        // V1.11 (I-3): Project Scope for submitting a task.
        $this->authorize('submitForReview', $task);

        try {
            $this->taskService->submitForReview($task, $user);

            return redirect()->route('tasks.show', $task->id)->with('status', 'وظیفه با موفقیت جهت بررسی ثبت شد.');
        } catch (\Exception $e) {
            return redirect()->route('tasks.show', $task->id)->with('error', $e->getMessage());
        }
    }

    public function addDependency(Task $task, AddDependencyRequest $request)
    {
        $user = Auth::user();

        if ($user->contractor_id && $task->contractor_id !== $user->contractor_id) {
            abort(403, 'شما دسترسی به این وظیفه ندارید.');
        }

        // V1.11 (I-3): Project Scope for dependency writes (view-level scope).
        $this->authorize('view', $task);

        try {
            $dependsOnTask = Task::findOrFail($request->input('depends_on_task_id'));
        } catch (ModelNotFoundException $e) {
            return back()->with('error', $e->getMessage());
        }

        // V1.11 (DEC-049 I-4): the PREDECESSOR task is a second Task retrieval
        // surface — direct-ID enumeration must not reveal that a guessed task
        // exists in a foreign project. Scope is enforced through the same
        // canonical Policy layer as tasks.show; the service-level same-project
        // invariant stays as the business backstop (never the sole gate).
        // The authorize call sits OUTSIDE the try below — AuthorizationException
        // must surface as 403, never be swallowed into a redirect.
        $this->authorize('view', $dependsOnTask);

        try {
            $this->taskService->addDependency($task, $dependsOnTask, $user);

            return back()->with('status', 'پیش‌نیاز با موفقیت اضافه شد.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function removeDependency(Task $task, TaskDependency $dependency)
    {
        $user = Auth::user();

        if ($user->contractor_id && $task->contractor_id !== $user->contractor_id) {
            abort(403, 'شما دسترسی به این وظیفه ندارید.');
        }

        // V1.11 (I-3): Project Scope for dependency writes (view-level scope).
        $this->authorize('view', $task);

        if ($dependency->successor_task_id !== $task->id) {
            abort(404, 'این پیش‌نیاز متعلق به این وظیفه نیست.');
        }

        try {
            $this->taskService->removeDependency($task, $dependency, $user);

            return back()->with('status', 'پیش‌نیاز با موفقیت حذف شد.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
