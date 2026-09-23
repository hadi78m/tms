<?php

namespace Tests\Feature\V18;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\Exceptions\WbsPhaseTransitionException;
use App\Domain\Services\DocumentService;
use App\Domain\Services\WbsPhaseService;
use App\Models\Task;
use App\Models\User;
use App\Models\WbsPhase;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\V18\Concerns\V18Fixtures;
use Tests\TestCase;

/**
 * WBS Phase completion (no weight anywhere) and the real audit writer.
 */
class WbsPhaseAndAuditTest extends TestCase
{
    use RefreshDatabase, V18Fixtures;

    private WbsPhase $phase;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $project = $this->makeProject();
        $this->supervisor = $this->makeUserWithRole('supervisor');
        $this->makeModules($project, $this->supervisor, [100]);

        $this->phase = app(WbsPhaseService::class)->createPhase($project, [
            'name' => 'Phase 1',
            'expected_output' => 'checklist placeholder',
            'weight' => 50,
            'planned_duration' => 30,
            'duration_unit' => 'day',
            'status' => 'active',
        ], $this->supervisor);
    }

    private function service(): WbsPhaseService
    {
        return app(WbsPhaseService::class);
    }

    public function test_dec017_default_expected_output_is_the_approved_fixed_text(): void
    {
        $this->assertSame('مشاهدهٔ Checklist', WbsPhaseService::DEFAULT_EXPECTED_OUTPUT);
    }

    public function test_a_new_phase_starts_pending_and_carries_no_completion(): void
    {
        $phase = $this->phase->fresh();

        $this->assertSame('pending', $phase->completion_status);
        $this->assertNull($phase->actual_completion);
        $this->assertNull($phase->supervisor_approved_at);
        $this->assertNull($phase->supervisor_approved_by);
    }

    public function test_checklist_items_track_who_and_when(): void
    {
        $item = $this->service()->addChecklistItem($this->phase, $this->supervisor, 'SRS', 'requirements');

        $this->assertFalse((bool) $item->is_completed);
        $this->assertNull($item->completed_at);

        $this->service()->completeChecklistItem($item, $this->supervisor);

        $item = $item->fresh();
        $this->assertTrue((bool) $item->is_completed);
        $this->assertNotNull($item->completed_at);
        $this->assertSame($this->supervisor->id, $item->completed_by);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'wbs_phase_checklist_item_completed',
            'entity_id' => $item->id,
        ]);

        $this->service()->reopenChecklistItem($item, $this->supervisor, 'needs revision');

        $item = $item->fresh();
        $this->assertFalse((bool) $item->is_completed);
        $this->assertNull($item->completed_at);
        $this->assertNull($item->completed_by);
    }

    public function test_checklist_completion_consistency_is_enforced_by_the_database(): void
    {
        $item = $this->service()->addChecklistItem($this->phase, $this->supervisor, 'ERD');

        $this->expectException(QueryException::class);

        DB::table('wbs_phase_checklist_items')->where('id', $item->id)->update(['is_completed' => true]);
    }

    public function test_supervisor_can_complete_a_phase(): void
    {
        $this->service()->complete($this->phase, $this->supervisor, 'all checklist items reviewed');

        $phase = $this->phase->fresh();
        $this->assertSame('completed', $phase->completion_status);
        $this->assertNotNull($phase->actual_completion);
        $this->assertNotNull($phase->supervisor_approved_at);
        $this->assertSame($this->supervisor->id, $phase->supervisor_approved_by);
        $this->assertDatabaseHas('activity_logs', ['action' => 'wbs_phase_completed', 'entity_id' => $phase->id]);
    }

    public function test_phase_completion_is_independent_of_task_approval_state(): void
    {
        // A task of the phase can be fully approved while the phase stays pending.
        $task = Task::create([
            'project_id' => $this->phase->project_id,
            'contract_id' => $this->phase->project_id,
            'contractor_id' => $this->phase->project_id,
            'wbs_phase_id' => $this->phase->id,
            'title' => 'task of phase',
            'weight' => 5,
            'priority' => 'normal',
            'status' => 'approved',
            'created_by' => $this->supervisor->id,
        ]);

        $this->assertSame('approved', $task->fresh()->status);
        $this->assertSame('pending', $this->phase->fresh()->completion_status);
    }

    public function test_declaring_a_phase_not_completed_requires_a_comment(): void
    {
        $this->expectException(WbsPhaseTransitionException::class);

        $this->service()->markNotCompleted($this->phase, $this->supervisor, '   ');
    }

    public function test_supervisor_can_declare_a_phase_not_completed_with_a_comment(): void
    {
        $this->service()->markNotCompleted($this->phase, $this->supervisor, 'Gantt document missing');

        $phase = $this->phase->fresh();
        $this->assertSame('not_completed', $phase->completion_status);
        $this->assertSame('Gantt document missing', $phase->supervisor_comment);
        $this->assertDatabaseHas('activity_logs', ['action' => 'wbs_phase_not_completed', 'entity_id' => $phase->id]);
    }

    public function test_reopening_a_phase_clears_the_decision_but_keeps_it_in_the_audit_trail(): void
    {
        $this->service()->complete($this->phase, $this->supervisor, 'looked complete');

        $this->service()->reopen($this->phase, $this->supervisor, 'evidence withdrawn');

        $phase = $this->phase->fresh();
        $this->assertSame('pending', $phase->completion_status);
        $this->assertNull($phase->actual_completion);
        $this->assertNull($phase->supervisor_approved_at);
        $this->assertNull($phase->supervisor_approved_by);
        $this->assertNull($phase->supervisor_comment);

        $log = DB::table('activity_logs')->where('action', 'wbs_phase_reopened')->where('entity_id', $phase->id)->first();
        $this->assertNotNull($log);

        $oldValues = json_decode($log->old_values, true);
        $this->assertSame('completed', $oldValues['completion_status']);
        $this->assertSame($this->supervisor->id, $oldValues['supervisor_approved_by']);
    }

    public function test_reopening_an_already_pending_phase_is_rejected(): void
    {
        $this->expectException(WbsPhaseTransitionException::class);

        $this->service()->reopen($this->phase, $this->supervisor);
    }

    public function test_database_audit_service_writes_a_real_row_and_maps_metadata(): void
    {
        $module = \App\Models\Module::firstOrFail();

        $log = app(AuditServiceInterface::class)->log(
            'module_created',
            $module,
            $this->supervisor,
            ['weight' => 1],
            ['weight' => 100],
            ['reason' => 'initial definition', 'note' => 'from planning']
        );

        $this->assertDatabaseHas('activity_logs', [
            'id' => $log->id,
            'action' => 'module_created',
            'entity_type' => 'App\\Models\\Module',
            'entity_id' => $module->id,
            'reason' => 'initial definition',
        ]);

        $stored = DB::table('activity_logs')->where('id', $log->id)->first();
        $newValues = json_decode($stored->new_values, true);

        $this->assertSame(100, $newValues['weight']);
        $this->assertSame('from planning', $newValues['metadata']['note']);
        $this->assertArrayNotHasKey('reason', $newValues['metadata']);
        $this->assertSame('supervisor', $newValues['actor_role']);
        $this->assertNull($stored->ip_address, 'CLI/console runs must not fabricate an IP address.');
    }

    public function test_audit_failures_are_not_swallowed(): void
    {
        $module = \App\Models\Module::firstOrFail();

        // A user id that does not exist violates the FK on activity_logs.user_id.
        $ghost = new User;
        $ghost->id = 987654;

        $this->expectException(QueryException::class);

        app(AuditServiceInterface::class)->log('module_created', $module, $ghost);
    }

    public function test_uploading_a_document_emits_document_uploaded(): void
    {
        Storage::fake('local');

        $task = Task::create([
            'project_id' => $this->phase->project_id,
            'contract_id' => $this->phase->project_id,
            'contractor_id' => $this->phase->project_id,
            'title' => 'evidence task',
            'weight' => 5,
            'priority' => 'normal',
            'status' => 'draft',
            'created_by' => $this->supervisor->id,
        ]);

        $document = app(DocumentService::class)->uploadDocument(
            $task,
            UploadedFile::fake()->create('evidence.pdf', 10),
            $this->supervisor
        );

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'document_uploaded',
            'entity_type' => 'App\\Models\\Document',
            'entity_id' => $document->id,
        ]);
    }
}
