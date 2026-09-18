<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\SyncedContract;
use App\Models\SyncedContractor;
use App\Models\SyncedSystem;
use App\Models\User;
use App\Models\WbsPhase;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class InitialTmsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Spatie Roles
        $roles = ['admin', 'supervisor', 'contractor'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // 2. Initial Admin User
        $admin = User::firstOrCreate(
            ['national_code' => '0123456789'],
            [
                'username' => '0123456789',
                'name' => 'System Admin',
                'mobile' => '09123456789',
                'email' => 'admin@tms.local',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        // Supervisor User
        $supervisor = User::firstOrCreate(
            ['national_code' => '1111111111'],
            [
                'username' => '1111111111',
                'name' => 'Supervisor User',
                'mobile' => '09111111111',
                'email' => 'supervisor@example.com',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]
        );

        if (! $supervisor->hasRole('supervisor')) {
            $supervisor->assignRole('supervisor');
        }

        // 3. Base Synced Entities
        $system = SyncedSystem::firstOrCreate(
            ['source_system' => 'ERP_SYSTEM', 'external_id' => 'SYS-001'],
            [
                'name' => 'سامانه جامع اتوماسیون',
                'code' => 'SYS-MAIN',
                'status' => 'active',
                'source_updated_at' => now(),
                'last_synced_at' => now(),
                'sync_status' => 'synced',
                'sync_error' => null,
            ]
        );

        $contractor = SyncedContractor::firstOrCreate(
            ['source_system' => 'ERP_SYSTEM', 'external_id' => 'CONT-001'],
            [
                'name' => 'شرکت مهندسی داده‌پردازان راهکار',
                'code' => 'CONT-1001',
                'status' => 'active',
                'source_updated_at' => now(),
                'last_synced_at' => now(),
                'sync_status' => 'synced',
                'sync_error' => null,
            ]
        );

        // Contractor User (connected to SyncedContractor)
        $contractorUser = User::firstOrCreate(
            ['national_code' => '2222222222'],
            [
                'username' => '2222222222',
                'name' => 'Contractor User',
                'mobile' => '09222222222',
                'email' => 'contractor@example.com',
                'password' => Hash::make('password123'),
                'contractor_id' => $contractor->id,
                'is_active' => true,
            ]
        );

        if (! $contractorUser->hasRole('contractor')) {
            $contractorUser->assignRole('contractor');
        }

        $contract = SyncedContract::firstOrCreate(
            ['source_system' => 'ERP_SYSTEM', 'external_id' => 'CTR-2026-001'],
            [
                'contractor_id' => $contractor->id,
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
                'sync_error' => null,
            ]
        );

        // 4. Operational Project & WBS Phase
        $project = Project::firstOrCreate(
            ['contract_id' => $contract->id],
            [
                'contractor_id' => $contractor->id,
                'name' => 'پروژه سامانه مدیریت وظایف (TMS)',
                'description' => 'پروژه عملیاتی متناظر با قرارداد CNT-1405-01 جهت پایش وظایف و کنترل SLA',
                'status' => 'active',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ]
        );

        WbsPhase::firstOrCreate(
            [
                'project_id' => $project->id,
                'name' => 'فاز ۱ - تحلیل و پیاده‌سازی اولیه',
            ],
            [
                'expected_output' => 'مستندات معماری، هسته دامین و سرویس‌های پایه',
                'weight' => 50.00,
                'planned_duration' => 30,
                'duration_unit' => 'day',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
                'sort_order' => 1,
                'status' => 'active',
            ]
        );
    }
}
