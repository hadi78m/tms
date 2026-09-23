<?php

namespace Tests\Feature\V18;

use App\Domain\Exceptions\InvalidApprovalException;
use App\Domain\Exceptions\PendingRequestExistsException;
use App\Domain\Exceptions\StageApprovalAuthorizationException;
use App\Domain\Exceptions\StageProgressCapacityExceededException;
use App\Domain\Services\SettingsService;
use App\Domain\Services\StageProgressApprovalService;
use App\Models\ModuleStage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\V18\Concerns\V18Fixtures;
use Tests\TestCase;

/**
 * The cumulative approval ceiling and the immutable approval history.
 *
 * Ceiling: SUM(active approved_amount) <= module_stages.weight, where "active"
 * excludes any approved row superseded by a newer one (DEC-026 · DEC-027).
 * The ceiling relies on a parent lock on module_stages.id — never on
 * `SELECT SUM(...) FOR UPDATE`, which PostgreSQL rejects outright.
 */
class StageProgressApprovalTest extends TestCase
{
    use RefreshDatabase, V18Fixtures;

    private ModuleStage $analysis;

    private $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $project = $this->makeProject();
        $this->supervisor = $this->makeUserWithRole('supervisor');
        $module = $this->makeModules($project, $this->supervisor, [100])->first();

        $this->analysis = $module->stages()->where('stage_code', 'analysis')->first();
        $this->assertEqualsWithDelta(15.0, (float) $this->analysis->weight, 0.001);
    }

    private function service(): StageProgressApprovalService
    {
        return app(StageProgressApprovalService::class);
    }

    public function test_incremental_approvals_accumulate_and_leave_a_remainder(): void
    {
        $first = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $this->service()->approve($first, $this->supervisor, 5);

        $second = $this->service()->propose($this->analysis, $this->supervisor, 7);
        $this->service()->approve($second, $this->supervisor, 7);

        $this->assertEqualsWithDelta(12.0, $this->service()->approvedWeight($this->analysis), 0.001);
        $this->assertEqualsWithDelta(3.0, $this->service()->remainingWeight($this->analysis), 0.001);
    }

    public function test_a_stage_can_be_fully_approved_in_pieces(): void
    {
        foreach ([7, 5, 3] as $amount) {
            $approval = $this->service()->propose($this->analysis, $this->supervisor, $amount);
            $this->service()->approve($approval, $this->supervisor, $amount);
        }

        $this->assertEqualsWithDelta(15.0, $this->service()->approvedWeight($this->analysis), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->service()->remainingWeight($this->analysis), 0.001);
    }

    public function test_approval_zero_is_allowed(): void
    {
        $approval = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $this->service()->approve($approval, $this->supervisor, 0);

        $this->assertEqualsWithDelta(0.0, $this->service()->approvedWeight($this->analysis), 0.001);
        $this->assertSame('approved', $approval->fresh()->status);
    }

    public function test_exceeding_the_allocated_weight_is_rejected(): void
    {
        $first = $this->service()->propose($this->analysis, $this->supervisor, 15);
        $this->service()->approve($first, $this->supervisor, 10);

        $second = $this->service()->propose($this->analysis, $this->supervisor, 15);

        $this->expectException(StageProgressCapacityExceededException::class);

        $this->service()->approve($second, $this->supervisor, 7); // 10 + 7 > 15
    }

    public function test_approved_amount_may_not_exceed_the_proposed_amount(): void
    {
        $approval = $this->service()->propose($this->analysis, $this->supervisor, 5);

        $this->expectException(InvalidApprovalException::class);

        $this->service()->approve($approval, $this->supervisor, 7);
    }

    public function test_approved_amount_may_not_exceed_the_allocated_weight(): void
    {
        $approval = $this->service()->propose($this->analysis, $this->supervisor, 100);

        $this->expectException(InvalidApprovalException::class);

        $this->service()->approve($approval, $this->supervisor, 20);
    }

    public function test_only_one_pending_proposal_may_exist_per_stage(): void
    {
        $this->service()->propose($this->analysis, $this->supervisor, 5);

        $this->expectException(PendingRequestExistsException::class);

        $this->service()->propose($this->analysis, $this->supervisor, 6);
    }

    public function test_a_decided_approval_is_immutable_at_model_and_database_level(): void
    {
        $approval = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $this->service()->approve($approval, $this->supervisor, 5);

        $decided = $approval->fresh();
        $decided->approved_amount = 9;

        $this->assertFalse($decided->save(), 'The model must refuse to update a decided approval.');

        $this->expectException(QueryException::class);
        DB::table('stage_progress_approvals')->where('id', $approval->id)->update(['approved_amount' => 9]);
    }

    public function test_a_pending_proposal_can_be_adjusted_and_is_audited(): void
    {
        $approval = $this->service()->propose($this->analysis, $this->supervisor, 5);

        $this->service()->adjust($approval, $this->supervisor, 7, 'scope increased');

        $this->assertEqualsWithDelta(7.0, (float) $approval->fresh()->proposed_amount, 0.001);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'stage_progress_adjusted',
            'entity_id' => $approval->id,
        ]);
    }

    public function test_superseding_an_approved_row_never_rewrites_it(): void
    {
        $original = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $this->service()->approve($original, $this->supervisor, 5);

        $replacement = $this->service()->supersede($original, $this->supervisor, 7);

        $originalAfter = $original->fresh();

        $this->assertSame('approved', $originalAfter->status, 'History must not be rewritten.');
        $this->assertEqualsWithDelta(5.0, (float) $originalAfter->approved_amount, 0.001);
        $this->assertSame($original->id, $replacement->supersedes_approval_id);

        // The superseded row is no longer active, so nothing is double counted.
        $this->assertEqualsWithDelta(0.0, $this->service()->approvedWeight($this->analysis), 0.001);

        $this->service()->approve($replacement, $this->supervisor, 7);

        $this->assertEqualsWithDelta(7.0, $this->service()->approvedWeight($this->analysis), 0.001);
        $this->assertEqualsWithDelta(8.0, $this->service()->remainingWeight($this->analysis), 0.001);
    }

    public function test_a_pending_row_can_also_be_superseded(): void
    {
        $original = $this->service()->propose($this->analysis, $this->supervisor, 5);

        $replacement = $this->service()->supersede($original, $this->supervisor, 9);

        // The corrected row keeps its STORED status; supersession is derived.
        $this->assertSame('pending', $original->fresh()->status);
        $this->assertSame($original->id, $replacement->supersedes_approval_id);
        $this->assertTrue($original->fresh()->isSuperseded());
        $this->assertFalse($replacement->isSuperseded());

        // Exactly ONE ACTIVE pending proposal — the superseded one does not count.
        $this->assertSame(2, DB::table('stage_progress_approvals')->where('status', 'pending')->count());
        $this->assertSame(1, $this->activePendingCount());
    }

    public function test_superseding_a_pending_row_does_not_block_later_proposals(): void
    {
        $original = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $replacement = $this->service()->supersede($original, $this->supervisor, 9);

        // Regression guard: a superseded PENDING row must not lock the stage
        // forever. Deciding the replacement frees the stage again.
        $this->service()->approve($replacement, $this->supervisor, 9);

        $next = $this->service()->propose($this->analysis, $this->supervisor, 6);

        $this->assertSame('pending', $next->fresh()->status);
        $this->assertEqualsWithDelta(9.0, $this->service()->approvedWeight($this->analysis), 0.001);

        $this->service()->approve($next, $this->supervisor, 6);

        $this->assertEqualsWithDelta(15.0, $this->service()->approvedWeight($this->analysis), 0.001);
    }

    public function test_a_superseded_row_can_no_longer_be_decided(): void
    {
        $original = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $this->service()->supersede($original, $this->supervisor, 9);

        $this->expectException(InvalidApprovalException::class);

        $this->service()->approve($original, $this->supervisor, 5);
    }

    private function activePendingCount(): int
    {
        return DB::table('stage_progress_approvals as t')
            ->where('t.status', 'pending')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('stage_progress_approvals as superseding')
                    ->whereColumn('superseding.supersedes_approval_id', 't.id');
            })
            ->count();
    }

    public function test_only_a_supervisor_may_approve(): void
    {
        $approval = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $employer = $this->makeUserWithRole('employer');

        $this->expectException(StageApprovalAuthorizationException::class);

        $this->service()->approve($approval, $employer, 5);
    }

    public function test_proposer_role_follows_the_configured_mode(): void
    {
        app(SettingsService::class)->set('progress_approval_mode', 'employer_then_supervisor', 'string');

        // In this mode the supervisor may no longer propose directly.
        $this->expectException(StageApprovalAuthorizationException::class);

        $this->service()->propose($this->analysis, $this->supervisor, 5);
    }

    public function test_employer_may_propose_under_employer_then_supervisor(): void
    {
        app(SettingsService::class)->set('progress_approval_mode', 'employer_then_supervisor', 'string');
        $employer = $this->makeUserWithRole('employer');

        $approval = $this->service()->propose($this->analysis, $employer, 5);

        $this->assertSame('pending', $approval->fresh()->status);

        // ...but the supervisor still makes the final decision.
        $this->service()->approve($approval, $this->supervisor, 5);

        $this->assertSame('approved', $approval->fresh()->status);
    }

    public function test_supervisor_can_propose_and_approve_in_one_flow_under_supervisor_only(): void
    {
        $this->assertSame('supervisor_only', app(SettingsService::class)->get('progress_approval_mode'));

        $approval = $this->service()->propose($this->analysis, $this->supervisor, 15);
        $this->service()->approve($approval, $this->supervisor, 15);

        $this->assertEqualsWithDelta(15.0, $this->service()->approvedWeight($this->analysis), 0.001);
    }

    public function test_approving_writes_a_real_audit_row_with_the_cumulative_total(): void
    {
        $approval = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $this->service()->approve($approval, $this->supervisor, 5);

        $log = DB::table('activity_logs')
            ->where('action', 'stage_progress_approved')
            ->where('entity_id', $approval->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame((string) $this->supervisor->id, (string) $log->user_id);

        $newValues = json_decode($log->new_values, true);
        $this->assertEqualsWithDelta(5.0, (float) $newValues['cumulative_approved'], 0.001);
        $this->assertSame('supervisor', $newValues['actor_role']);
    }
}
