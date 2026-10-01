<?php

namespace App\Http\Controllers\Web;

use App\Domain\Exceptions\ModuleWeightException;
use App\Domain\Rules\ProjectScopeService;
use App\Domain\Services\ModuleService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\RebalanceModulesRequest;
use App\Http\Requests\Web\StoreModulesRequest;
use App\Http\Requests\Web\UpdateModuleRequest;
use App\Models\Module;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * V1.9 (DEC-039) — Module UI.
 *
 * Thin HTTP surface over ModuleService. The SUM(active modules.weight) = 100
 * invariant, the parent-lock doctrine, and all audit events live exclusively in
 * the service. There is no delete UI: module lifecycle reduction is the
 * service's archive/restore path, and no approved business rule exposes it
 * through the Web (project/module deletion explicitly rejected — DEC-038).
 */
class ModuleController extends Controller
{
    public function __construct(
        protected ModuleService $moduleService,
        protected ProjectScopeService $projectScope
    ) {}

    public function index(): View
    {
        // V1.11 (DEC-060 · DEC-062 · I-2): Project Scope retrieval filter.
        // Supervisors see only projects with an ACTIVE supervisor membership;
        // admin/management are unrestricted (project-scope bypass ONLY);
        // PM/viewer keep current behavior, employer stays fail-open pending
        // DR-EMP-01 (all null = query untouched). The role boundary stays in
        // the route middleware — this filter adds Resource-scope only.
        // Column is `id`: the projects table has no `project_id` column.
        $projects = $this->projectScope
            ->applyProjectScope(Project::query(), Auth::user(), 'id')
            ->with(['activeModules.stages'])
            ->withCount('tasks')
            ->orderBy('id')
            ->get();

        return view('modules.index', compact('projects'));
    }

    /**
     * Show the management page of one project's module structure.
     */
    public function show(Project $project): View
    {
        // V1.11 (DEC-060 · DEC-062 · I-2): ModulePolicy::viewProject —
        // Project Scope over the bound project (direct-ID enumeration
        // protection; same canonical scope source as the index query).
        $this->authorize('viewProject', [Module::class, $project]);

        $project->load(['activeModules.stages.approvals']);

        return view('modules.show', compact('project'));
    }

    /**
     * Define the complete module set of a project (one transaction, R-1).
     */
    public function store(StoreModulesRequest $request): RedirectResponse
    {
        $project = Project::findOrFail($request->input('project_id'));

        // V1.11 (DEC-060 · DEC-062 · I-2): the request-body project_id is
        // authorized BEFORE creation via ModulePolicy::create — the resolved
        // Project instance is passed through the policy so Project Scope
        // applies. Creation is never authorized against the persisted Module.
        $this->authorize('create', [Module::class, $project]);

        try {
            $this->moduleService->createModules(
                $project,
                $request->moduleDefinitions(),
                Auth::user()
            );
        } catch (ModuleWeightException $e) {
            return redirect()
                ->route('modules.show', $project->id)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('modules.show', $project->id)
            ->with('status', 'ساختار ماژول پروژه با موفقیت تعریف شد.');
    }

    /**
     * Apply a complete new weight distribution to the active modules (R-2).
     */
    public function rebalance(Project $project, RebalanceModulesRequest $request): RedirectResponse
    {
        // V1.11 (DEC-060 · DEC-062 · I-2): ModulePolicy::rebalance — the route
        // binds a Project (modules.rebalance), scope over that project.
        $this->authorize('rebalance', [Module::class, $project]);

        try {
            $this->moduleService->rebalance($project, $request->weightsByModuleId(), Auth::user());
        } catch (ModuleWeightException $e) {
            return redirect()
                ->route('modules.show', $project->id)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('modules.show', $project->id)
            ->with('status', 'توزیع وزن ماژول‌ها با موفقیت به‌روزرسانی شد.');
    }

    /**
     * Update non-weight attributes of a module. Weight changes must go through
     * rebalance() — the service rejects any weight key passed here.
     */
    public function update(Module $module, UpdateModuleRequest $request): RedirectResponse
    {
        // V1.11 (DEC-060 · DEC-062 · I-2): ModulePolicy::update — resource is
        // the bound Module; scope resolves via Module → Project (fail-closed
        // when the relation is missing).
        $this->authorize('update', $module);

        try {
            $this->moduleService->update($module, $request->moduleAttributes(), Auth::user());
        } catch (ModuleWeightException $e) {
            return redirect()
                ->route('modules.show', $module->project_id)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('modules.show', $module->project_id)
            ->with('status', 'اطلاعات ماژول با موفقیت به‌روزرسانی شد.');
    }
}
