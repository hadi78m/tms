<?php

namespace Tests\Feature\Domain;

use App\Domain\Exceptions\DuplicatePeriodException;
use App\Domain\Exceptions\InvalidWeightException;
use App\Domain\Services\PerformanceRecordService;
use App\Models\PerformanceRecord;
use App\Models\SyncedContract;
use App\Models\SyncedContractor;
use App\Models\SyncedSystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceRecordServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PerformanceRecordService $performanceRecordService;

    protected SyncedContract $contract;

    protected SyncedContractor $contractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->performanceRecordService = new PerformanceRecordService;

        $system = SyncedSystem::create([
            'id' => 1,
            'external_id' => 'SYS-1',
            'source_system' => 'test_system',
            'name' => 'Test System',
            'code' => 'SYS-100',
            'status' => 'active',
            'source_updated_at' => now(),
            'last_synced_at' => now(),
            'sync_status' => 'success',
        ]);

        $this->contractor = SyncedContractor::create([
            'id' => 1,
            'external_id' => 'EXT-CO-1',
            'source_system' => 'test_system',
            'name' => 'Test Company',
            'code' => 'CO-100',
            'status' => 'active',
            'source_updated_at' => now(),
            'last_synced_at' => now(),
            'sync_status' => 'success',
        ]);

        $this->contract = SyncedContract::create([
            'id' => 1,
            'contractor_id' => $this->contractor->id,
            'system_id' => $system->id,
            'external_id' => 'EXT-C-1',
            'source_system' => 'test_system',
            'title' => 'Test Contract',
            'contract_number' => 'C-100',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'amount' => 1000.00,
            'status' => 'active',
            'source_updated_at' => now(),
            'last_synced_at' => now(),
            'sync_status' => 'success',
        ]);
    }

    public function test_it_creates_a_performance_record_successfully()
    {
        $record = $this->performanceRecordService->recordPerformance(
            $this->contract->id,
            $this->contractor->id,
            '2026-01-01',
            '2026-01-31',
            10,
            85.5,
            2,
            0
        );

        $this->assertInstanceOf(PerformanceRecord::class, $record);
        $this->assertEquals(10, $record->tasks_completed);
        $this->assertEquals(85.5, $record->total_weight_completed);

        $this->assertDatabaseHas('performance_records', [
            'id' => $record->id,
            'contract_id' => $this->contract->id,
            'contractor_id' => $this->contractor->id,
            'tasks_completed' => 10,
        ]);
    }

    public function test_it_throws_exception_if_weight_is_invalid()
    {
        $this->expectException(InvalidWeightException::class);

        $this->performanceRecordService->recordPerformance(
            $this->contract->id,
            $this->contractor->id,
            '2026-01-01',
            '2026-01-31',
            10,
            105.0, // Invalid weight > 100
            2,
            0
        );
    }

    public function test_it_throws_exception_on_duplicate_period()
    {
        // First record
        $this->performanceRecordService->recordPerformance(
            $this->contract->id,
            $this->contractor->id,
            '2026-01-01',
            '2026-01-31',
            10,
            85.5,
            2,
            0
        );

        // Attempt second record with exact same contract, contractor, and period
        $this->expectException(DuplicatePeriodException::class);

        $this->performanceRecordService->recordPerformance(
            $this->contract->id,
            $this->contractor->id,
            '2026-01-01',
            '2026-01-31',
            5,
            15.0,
            0,
            0
        );
    }
}
