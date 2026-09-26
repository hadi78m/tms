<?php

namespace Tests\Feature\V19;

use App\Domain\Services\StageProgressApprovalService;
use App\Models\Project;
use App\Models\StageProgressApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V19\Concerns\V19Fixtures;
use Tests\TestCase;

use function app;

/**
 * V1.9 — Stage Approval UI (DEC-039).
 *
 * All rules (mode-based proposal authorization, supervisor-only decisions,
 * one pending per stage, cumulative ceiling, immutability, supersede chain,
 * audit) live in StageProgressApprovalService — the UI calls it. These tests
 * exercise the HTTP surface against those rules.
 */
class StageApprovalUiTest extends TestCase
{
    use RefreshDatabase, V19Fixtures;

    private Project $project;

    private User $supervisor;

    private User $employer;

    private User $contractor;

    private $stage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makeProject(1);
        $this->supervisor = $this->makeUserWithRole('supervisor');
        $this->employer = $this->makeUserWithRole('employer');
        $this->contractor = $this->makeUserWithRole('contractor');

        $module = $this->makeSingleModule($this->project, $this->supervisor);
        $this->stage = $module->stages()->firstWhere('stage_code', 'analysis');
    }

    private function service(): StageProgressApprovalService
    {
        return app(StageProgressApprovalService::class);
    }

    public function test_contractor_cannot_propose(): void
    {
        $this->actingAs($this->contractor)
            ->post(route('stages.progress-approvals.store', $this->stage->id), [
                'proposed_amount' => 5,
            ])
            ->assertForbidden();
    }

    public function test_supervisor_can_propose_in_default_mode(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('stages.progress-approvals.store', $this->stage->id), [
                'proposed_amount' => 5,
                'reason' => 'first increment',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('stage_progress_approvals', [
            'module_stage_id' => $this->stage->id,
            'proposed_amount' => 5,
            'status' => 'pending',
        ]);
    }

    public function test_second_pending_proposal_is_refused(): void
    {
        $this->service()->propose($this->stage, $this->supervisor, 5);

        $this->actingAs($this->supervisor)
            ->post(route('stages.progress-approvals.store', $this->stage->id), [
                'proposed_amount' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, StageProgressApproval::where('module_stage_id', $this->stage->id)->count());
    }

    public function test_employer_can_adjust_pending_proposal(): void
    {
        $approval = $this->service()->propose($this->stage, $this->supervisor, 5);

        $this->actingAs($this->employer)
            ->post(route('stages.progress-approvals.adjust', $approval->id), [
                'proposed_amount' => 4,
                'reason' => 'adjusted after review',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('stage_progress_approvals', [
            'id' => $approval->id,
            'proposed_amount' => 4,
        ]);
    }

    public function test_supervisor_approves_within_ceiling(): void
    {
        $approval = $this->service()->propose($this->stage, $this->supervisor, 5);

        $this->actingAs($this->supervisor)
            ->post(route('stages.progress-approvals.decide', $approval->id), [
                'decision' => 'approve',
                'approved_amount' => 5,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('stage_progress_approvals', [
            'id' => $approval->id,
            'status' => 'approved',
            'approved_amount' => 5,
        ]);
    }

    public function test_approved_amount_may_not_exceed_proposed(): void
    {
        $approval = $this->service()->propose($this->stage, $this->supervisor, 3);

        $this->actingAs($this->supervisor)
            ->post(route('stages.progress-approvals.decide', $approval->id), [
                'decision' => 'approve',
                'approved_amount' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('stage_progress_approvals', [
            'id' => $approval->id,
            'status' => 'pending',
        ]);
    }

    public function test_cumulative_ceiling_is_enforced_through_http(): void
    {
        $service = $this->service();

        $first = $service->propose($this->stage, $this->supervisor, 10);
        $service->approve($first, $this->supervisor, 10);

        $second = $service->propose($this->stage, $this->supervisor, 10);

        // Analysis weight is 15; 10 already approved → 10 more exceeds it.
        $this->actingAs($this->supervisor)
            ->post(route('stages.progress-approvals.decide', $second->id), [
                'decision' => 'approve',
                'approved_amount' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('stage_progress_approvals', ['id' => $second->id, 'status' => 'pending']);
    }

    public function test_employer_cannot_decide(): void
    {
        $approval = $this->service()->propose($this->stage, $this->supervisor, 5);

        $this->actingAs($this->employer)
            ->post(route('stages.progress-approvals.decide', $approval->id), [
                'decision' => 'approve',
                'approved_amount' => 5,
            ])
            ->assertForbidden();
    }

    public function test_rejection_requires_reason(): void
    {
        $approval = $this->service()->propose($this->stage, $this->supervisor, 5);

        $this->actingAs($this->supervisor)
            ->post(route('stages.progress-approvals.decide', $approval->id), [
                'decision' => 'reject',
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('stage_progress_approvals', ['id' => $approval->id, 'status' => 'pending']);
    }

    public function test_supervisor_can_reject_with_reason(): void
    {
        $approval = $this->service()->propose($this->stage, $this->supervisor, 5);

        $this->actingAs($this->supervisor)
            ->post(route('stages.progress-approvals.decide', $approval->id), [
                'decision' => 'reject',
                'reason' => 'evidence insufficient',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('stage_progress_approvals', [
            'id' => $approval->id,
            'status' => 'rejected',
        ]);
    }

    public function test_supersede_creates_replacement_row_and_keeps_history(): void
    {
        $service = $this->service();

        $source = $service->propose($this->stage, $this->supervisor, 5);
        $service->approve($source, $this->supervisor, 5);

        $this->actingAs($this->supervisor)
            ->post(route('stages.progress-approvals.store', $this->stage->id), [
                'supersedes_approval_id' => $source->id,
                'proposed_amount' => 3,
                'reason' => 'correction',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        // History untouched...
        $this->assertDatabaseHas('stage_progress_approvals', [
            'id' => $source->id,
            'status' => 'approved',
            'approved_amount' => 5,
        ]);

        // ...and the replacement pending row points back at it.
        $replacement = StageProgressApproval::where('supersedes_approval_id', $source->id)->firstOrFail();
        $this->assertSame('pending', $replacement->status);
        $this->assertEquals(3, (float) $replacement->proposed_amount);
    }

    public function test_decided_row_cannot_be_approved_again(): void
    {
        $service = $this->service();

        $source = $service->propose($this->stage, $this->supervisor, 5);
        $service->approve($source, $this->supervisor, 5);

        $this->actingAs($this->supervisor)
            ->post(route('stages.progress-approvals.decide', $source->id), [
                'decision' => 'approve',
                'approved_amount' => 5,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('stage_progress_approvals', ['id' => $source->id, 'approved_amount' => 5]);
    }

    public function test_approval_actions_are_audited(): void
    {
        $approval = $this->service()->propose($this->stage, $this->supervisor, 5);

        $this->actingAs($this->supervisor)
            ->post(route('stages.progress-approvals.decide', $approval->id), [
                'decision' => 'approve',
                'approved_amount' => 5,
            ]);

        $this->assertDatabaseHas('activity_logs', ['action' => 'stage_progress_proposed']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'stage_progress_approved']);
    }
}
