<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\SyncedContract;
use App\Models\SyncedContractor;
use App\Models\SyncedSystem;
use App\Models\User;
use App\Models\WbsPhase;
use App\Models\Task;
use App\Models\TaskDependency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class InitialTmsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Spatie Roles and Permissions
        $permissions = [
            'manage users', 'manage settings', 'manage projects', 'create tasks', 'assign tasks',
            'technical_approval', 'final_approval', 'submit rework',
            'view reports', 'export reports', 'upload evidence'
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $rolesWithPerms = [
            'admin' => $permissions,
            'project_manager' => ['manage projects', 'create tasks', 'assign tasks', 'view reports'],
            'supervisor' => ['technical_approval', 'submit rework', 'view reports'],
            'employer' => ['final_approval', 'submit rework', 'view reports', 'export reports'],
            'contractor' => ['upload evidence'],
            'management' => ['view reports', 'export reports'],
            'viewer' => ['view reports']
        ];

        foreach ($rolesWithPerms as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }

        // 2. Base Synced Entities
        $system = SyncedSystem::firstOrCreate(
            ['source_system' => 'ERP_SYSTEM', 'external_id' => 'SYS-001'],
            [
                'name' => 'سامانه جامع اتوماسیون',
                'code' => 'SYS-MAIN',
                'status' => 'active',
                'source_updated_at' => now(),
                'last_synced_at' => now(),
                'sync_status' => 'synced',
            ]
        );

        $contractor1 = SyncedContractor::firstOrCreate(
            ['source_system' => 'ERP_SYSTEM', 'external_id' => 'CONT-001'],
            [
                'name' => 'شرکت الف',
                'code' => 'CONT-1001',
                'status' => 'active',
                'source_updated_at' => now(),
                'last_synced_at' => now(),
                'sync_status' => 'synced',
            ]
        );

        $contractor2 = SyncedContractor::firstOrCreate(
            ['source_system' => 'ERP_SYSTEM', 'external_id' => 'CONT-002'],
            [
                'name' => 'شرکت ب',
                'code' => 'CONT-1002',
                'status' => 'active',
                'source_updated_at' => now(),
                'last_synced_at' => now(),
                'sync_status' => 'synced',
            ]
        );

        $contract = SyncedContract::firstOrCreate(
            ['source_system' => 'ERP_SYSTEM', 'external_id' => 'CTR-2026-001'],
            [
                'contractor_id' => $contractor1->id,
                'system_id' => $system->id,
                'contract_number' => 'CNT-1405-01',
                'title' => 'قرارداد توسعه و نگهداری سامانه مدیریت وظایف',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'amount' => 500000000.00,
                'status' => 'active',
                'source_updated_at' => now(),
                'last_synced_at' => now(),
                'sync_status' => 'synced',
            ]
        );

        // 3. Operational Project & WBS Phase
        $project = Project::firstOrCreate(
            ['contract_id' => $contract->id],
            [
                'contractor_id' => $contractor1->id,
                'name' => 'پروژه سامانه مدیریت وظایف (TMS)',
                'description' => 'پروژه عملیاتی متناظر با قرارداد CNT-1405-01',
                'status' => 'active',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ]
        );

        $wbs = WbsPhase::firstOrCreate(
            [
                'project_id' => $project->id,
                'name' => 'فاز ۱ - تحلیل و پیاده‌سازی اولیه',
            ],
            [
                'expected_output' => 'مستندات معماری',
                'weight' => 50.00,
                'planned_duration' => 30,
                'duration_unit' => 'day',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
                'sort_order' => 1,
                'status' => 'active',
            ]
        );

        // 4. Users
        $usersData = [
            ['national_code' => '0123456789', 'role' => 'admin', 'name' => 'Admin'],
            ['national_code' => '1000000001', 'role' => 'project_manager', 'name' => 'Project Manager'],
            ['national_code' => '1111111111', 'role' => 'supervisor', 'name' => 'Supervisor'],
            ['national_code' => '3333333333', 'role' => 'employer', 'name' => 'Employer'],
            ['national_code' => '4444444444', 'role' => 'management', 'name' => 'Management'],
            ['national_code' => '5555555555', 'role' => 'viewer', 'name' => 'Viewer'],
            ['national_code' => '2222222222', 'role' => 'contractor', 'name' => 'Contractor 1', 'contractor_id' => $contractor1->id],
            ['national_code' => '2222222223', 'role' => 'contractor', 'name' => 'Contractor 2', 'contractor_id' => $contractor2->id],
        ];

        foreach ($usersData as $ud) {
            $u = User::firstOrCreate(
                ['national_code' => $ud['national_code']],
                [
                    'username' => $ud['national_code'],
                    'name' => $ud['name'],
                    'mobile' => '09' . substr($ud['national_code'], 1, 9),
                    'email' => $ud['role'] . '@tms.local',
                    'password' => Hash::make('password123'),
                    'is_active' => true,
                    'contractor_id' => $ud['contractor_id'] ?? null,
                ]
            );
            if (! $u->hasRole($ud['role'])) {
                $u->assignRole($ud['role']);
            }
        }

        // 5. Sample Tasks
        // Task 1: under_review for supervisor approval test
        $task1 = Task::firstOrCreate(
            ['title' => 'تسک در حال بررسی (تست تایید فنی)'],
            [
                'project_id' => $project->id,
                'wbs_phase_id' => $wbs->id,
                'contract_id' => $contract->id,
                'contractor_id' => $contractor1->id,
                'created_by' => User::where('national_code', '1000000001')->first()->id,
                'status' => 'under_review',
                'priority' => 'high',
                'weight' => 20.00,
                'description' => 'این تسک منتظر بررسی ناظر است.',
            ]
        );

        // Task 2: Blocked by Dependency
        $task2_parent = Task::firstOrCreate(
            ['title' => 'تسک پیش‌نیاز (در حال انجام)'],
            [
                'project_id' => $project->id,
                'wbs_phase_id' => $wbs->id,
                'contract_id' => $contract->id,
                'contractor_id' => $contractor1->id,
                'created_by' => User::where('national_code', '1000000001')->first()->id,
                'status' => 'in_progress',
                'priority' => 'normal',
                'weight' => 10.00,
                'description' => 'تسک پیش‌نیاز که هنوز تمام نشده است.',
            ]
        );

        $task2_blocked = Task::firstOrCreate(
            ['title' => 'تسک مسدود شده (تست وابستگی)'],
            [
                'project_id' => $project->id,
                'wbs_phase_id' => $wbs->id,
                'contract_id' => $contract->id,
                'contractor_id' => $contractor1->id,
                'created_by' => User::where('national_code', '1000000001')->first()->id,
                'status' => 'assigned',
                'priority' => 'normal',
                'weight' => 10.00,
                'description' => 'این تسک مسدود است و نباید به حالت in_progress برود.',
            ]
        );

        TaskDependency::firstOrCreate([
            'successor_task_id' => $task2_blocked->id,
            'predecessor_task_id' => $task2_parent->id,
            'dependency_type' => 'fs',
            'created_by' => User::where('national_code', '1000000001')->first()->id,
        ]);
    }
}
