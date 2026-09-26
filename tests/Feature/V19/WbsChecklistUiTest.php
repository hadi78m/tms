<?php

namespace Tests\Feature\V19;

use App\Domain\Services\WbsPhaseService;
use App\Models\Project;
use App\Models\User;
use App\Models\WbsPhase;
use App\Models\WbsPhaseChecklistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V19\Concerns\V19Fixtures;
use Tests\TestCase;

/**
 * V1.9 — WBS Phase / Checklist UI (DEC-039).
 *
 * Checklist items carry no computational weight; supervisor decisions go
 * through WbsPhaseService (pending → completed / not_completed, reopen).
 */
class WbsChecklistUiTest extends TestCase
{
    use RefreshDatabase, V19Fixtures;

    private Project $project;

    private User $manager;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makeProject(1);
        $this->manager = $this->makeUserWithRole('project_manager');
        $this->supervisor = $this->makeUserWithRole('supervisor');
    }

    public function test_index_requires_authentication(): void
    {
        $this->get(route('wbs-phases.index'))->assertRedirect(route('login'));
    }

    public function test_index_forbidden_for_contractor(): void
    {
        $contractor = $this->makeUserWithRole('contractor');

        $this->actingAs($contractor)->get(route('wbs-phases.index'))->assertForbidden();
    }

    public function test_store_creates_phase_with_default_expected_output(): void
    {
        $this->actingAs($this->manager)
            ->post(route('wbs-phases.store'), [
                'project_id' => $this->project->id,
                'name' => 'Phase One',
                'weight' => 0,
                'planned_duration' => 30,
                'duration_unit' => 'day',
            ])
            ->assertRedirect(route('wbs-phases.index'))
            ->assertSessionHas('status');

        $phase = WbsPhase::where('name', 'Phase One')->firstOrFail();
        $this->assertSame('مشاهدهٔ Checklist', $phase->expected_output);
        $this->assertSame('pending', $phase->completion_status);
    }

    public function test_show_renders_phase_and_checklist(): void
    {
        $phase = $this->makeWbsPhase($this->project, $this->manager, 'Phase One');

        $this->actingAs($this->supervisor)
            ->get(route('wbs-phases.show', $phase->id))
            ->assertOk()
            ->assertSee('Phase One');
    }

    public function test_manager_can_add_and_complete_checklist_item(): void
    {
        $phase = $this->makeWbsPhase($this->project, $this->manager);

        $this->actingAs($this->manager)
            ->post(route('wbs-phases.checklist-items.store', $phase->id), [
                'title' => 'SRS document',
                'description' => 'requirements',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $item = WbsPhaseChecklistItem::where('title', 'SRS document')->firstOrFail();
        $this->assertFalse((bool) $item->is_completed);

        $this->actingAs($this->manager)
            ->post(route('wbs-phases.checklist-items.complete', [$phase->id, $item->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertTrue((bool) $item->fresh()->is_completed);
        $this->assertSame($this->manager->id, $item->fresh()->completed_by);

        $this->assertDatabaseHas('activity_logs', ['action' => 'wbs_phase_checklist_item_completed']);
    }

    public function test_checklist_item_can_be_reopened(): void
    {
        $phase = $this->makeWbsPhase($this->project, $this->manager);
        $item = app(WbsPhaseService::class)->addChecklistItem($phase, $this->manager, 'ERD');
        app(WbsPhaseService::class)->completeChecklistItem($item, $this->manager);

        $this->actingAs($this->manager)
            ->post(route('wbs-phases.checklist-items.reopen', [$phase->id, $item->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertFalse((bool) $item->fresh()->is_completed);
    }

    public function test_supervisor_can_complete_phase(): void
    {
        $phase = $this->makeWbsPhase($this->project, $this->manager);

        $this->actingAs($this->supervisor)
            ->post(route('wbs-phases.decide', $phase->id), [
                'decision' => 'complete',
                'comment' => 'all reviewed',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('completed', $phase->fresh()->completion_status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'wbs_phase_completed']);
    }

    public function test_not_completed_requires_comment(): void
    {
        $phase = $this->makeWbsPhase($this->project, $this->manager);

        $this->actingAs($this->supervisor)
            ->post(route('wbs-phases.decide', $phase->id), [
                'decision' => 'not_completed',
                'comment' => '',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('pending', $phase->fresh()->completion_status);
    }

    public function test_supervisor_can_reopen_decided_phase(): void
    {
        $phase = $this->makeWbsPhase($this->project, $this->manager);
        app(WbsPhaseService::class)->complete($phase, $this->supervisor, 'looked complete');

        $this->actingAs($this->supervisor)
            ->post(route('wbs-phases.decide', $phase->id), [
                'decision' => 'reopen',
                'comment' => 'evidence withdrawn',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('pending', $phase->fresh()->completion_status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'wbs_phase_reopened']);
    }

    public function test_phase_creation_is_forbidden_for_contractor(): void
    {
        $contractor = $this->makeUserWithRole('contractor');

        $this->actingAs($contractor)
            ->post(route('wbs-phases.store'), [
                'project_id' => $this->project->id,
                'name' => 'Illegal Phase',
                'weight' => 0,
                'planned_duration' => 10,
                'duration_unit' => 'day',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('wbs_phases', ['name' => 'Illegal Phase']);
    }
}
