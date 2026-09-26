<?php

namespace App\Http\Controllers\Web;

use App\Domain\Exceptions\ModuleWeightException;
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
        protected ModuleService $moduleService
    ) {}

    public function index(): View
    {
        $projects = Project::with(['activeModules.stages'])
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
        $project->load(['activeModules.stages.approvals']);

        return view('modules.show', compact('project'));
    }

    /**
     * Define the complete module set of a project (one transaction, R-1).
     */
    public function store(StoreModulesRequest $request): RedirectResponse
    {
        $project = Project::findOrFail($request->input('project_id'));

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
