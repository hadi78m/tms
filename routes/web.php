<?php

use App\Http\Controllers\Web\ApprovalController;
use App\Http\Controllers\Web\AuditController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\ModuleController;
use App\Http\Controllers\Web\ModuleStageController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\SettingController;
use App\Http\Controllers\Web\StageProgressApprovalController;
use App\Http\Controllers\Web\TaskAssignmentController;
use App\Http\Controllers\Web\TaskController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\WbsPhaseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Task routes. V1.10 (DEC-044 — F-2 FIX NOW): create/store were
    // previously reachable by ANY authenticated user (Authorization Audit
    // F-2). V1.11 (DEC-055 — DR-TASK-04 = A Align-down): Task Creation is
    // business-permitted ONLY for admin + project_manager — the role list is
    // now aligned with the Business Role Matrix (supervisor/employer were
    // carried over by the V1.10 implementation and are removed here; the
    // permission `create tasks` is still enforced in TaskPolicy::create —
    // middleware stays coarse-grained, permission lives in the Policy).
    Route::middleware('role:admin|project_manager')->group(function () {
        Route::get('tasks/create', [TaskController::class, 'create'])->name('tasks.create');
        Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
    });

    Route::resource('tasks', TaskController::class)->except(['create', 'store']);

    // Task sub-routes. V1.10 (DEC-044 — F-3 FIX NOW): tasks.assign now
    // carries role enforcement. V1.11 (DEC-054 — DR-TASK-03 = A Align-down):
    // Task Assignment is business-permitted ONLY for admin + project_manager
    // (supervisor's domain is Stage Assignment — DEC-042/043 — which is a
    // DIFFERENT concept and stays supervisor-only; the permission
    // `assign tasks` remains enforced in TaskPolicy::assign).
    Route::middleware('role:admin|project_manager')->group(function () {
        Route::post('tasks/{task}/assign', [TaskAssignmentController::class, 'store'])->name('tasks.assign');
    });

    // V1.10 — DEC-042/043: stage reassignment (supervisor only at the HTTP
    // layer; TaskService::assignStage re-checks the role as a business
    // invariant — DEC-044 Hybrid).
    Route::middleware('role:supervisor')->group(function () {
        Route::post('tasks/{task}/stage', [TaskController::class, 'setStage'])->name('tasks.stage');
    });

    // V1.10 — DEC-045: read-only Audit Visibility UI (supervisor + employer).
    // Masking is applied in the view layer; there is no write path anywhere.
    Route::middleware('role:supervisor|employer')->group(function () {
        Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('tasks/{task}/audit', [AuditController::class, 'show'])->name('audit.task');
    });
    Route::post('tasks/{task}/start', [TaskController::class, 'start'])->name('tasks.start');
    Route::post('tasks/{task}/submit', [TaskController::class, 'submit'])->name('tasks.submit');
    Route::post('tasks/{task}/approvals', [ApprovalController::class, 'store'])->name('approvals.store');
    Route::post('tasks/{task}/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::post('tasks/{task}/dependencies', [TaskController::class, 'addDependency'])->name('tasks.dependencies.store');
    Route::post('tasks/{task}/dependencies/{dependency}/remove', [TaskController::class, 'removeDependency'])->name('tasks.dependencies.destroy');

    // V1.9 — Module / Stage structure (definition is admin/PM/employer territory;
    // read access for management/supervisor). No delete UI anywhere (DEC-038/039).
    Route::middleware('role:admin|project_manager|employer|management|supervisor|viewer')->group(function () {
        Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::get('projects/{project}/modules', [ModuleController::class, 'show'])->name('modules.show');
        Route::get('stages/{stage}', [ModuleStageController::class, 'show'])->name('modules.stages.show');
        Route::get('wbs-phases', [WbsPhaseController::class, 'index'])->name('wbs-phases.index');
        Route::get('wbs-phases/{phase}', [WbsPhaseController::class, 'show'])->name('wbs-phases.show');
    });

    Route::middleware('role:admin|project_manager|employer')->group(function () {
        Route::post('modules', [ModuleController::class, 'store'])->name('modules.store');
        Route::post('projects/{project}/modules/rebalance', [ModuleController::class, 'rebalance'])->name('modules.rebalance');
        Route::post('modules/{module}/update', [ModuleController::class, 'update'])->name('modules.update');
        Route::post('modules/{module}/stages/rebalance', [ModuleStageController::class, 'rebalance'])->name('modules.stages.rebalance');
        Route::post('wbs-phases', [WbsPhaseController::class, 'store'])->name('wbs-phases.store');
        Route::post('wbs-phases/{phase}/checklist-items', [WbsPhaseController::class, 'addChecklistItem'])->name('wbs-phases.checklist-items.store');
        Route::post('wbs-phases/{phase}/checklist-items/{item}/complete', [WbsPhaseController::class, 'completeChecklistItem'])->name('wbs-phases.checklist-items.complete');
        Route::post('wbs-phases/{phase}/checklist-items/{item}/reopen', [WbsPhaseController::class, 'reopenChecklistItem'])->name('wbs-phases.checklist-items.reopen');
    });

    // V1.9 — Stage approval workflow. Proposal visibility follows the configured
    // progress_approval_mode (supervisor/employer) — enforced in the service.
    Route::middleware('role:admin|project_manager|employer|supervisor')->group(function () {
        Route::post('stages/{stage}/progress-approvals', [StageProgressApprovalController::class, 'store'])->name('stages.progress-approvals.store');
        Route::post('progress-approvals/{approval}/adjust', [StageProgressApprovalController::class, 'adjust'])->name('stages.progress-approvals.adjust');
    });

    Route::middleware('role:supervisor')->group(function () {
        Route::post('progress-approvals/{approval}/decide', [StageProgressApprovalController::class, 'decide'])->name('stages.progress-approvals.decide');
        Route::post('wbs-phases/{phase}/decide', [WbsPhaseController::class, 'decide'])->name('wbs-phases.decide');
    });

    // Settings (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    });

    // Reports (Roles with access)
    Route::middleware('role:admin|management|employer|project_manager')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

    // User management (Admin only)
    Route::resource('users', UserController::class)->middleware('role:admin');
});
