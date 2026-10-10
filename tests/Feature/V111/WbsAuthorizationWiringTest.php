<?php

namespace Tests\Feature\V111;

use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Domain\Services\ProjectMembershipService;
use App\Domain\Services\WbsPhaseService;
use App\Models\Project;
use App\Models\User;
use App\Models\WbsPhase;
use App\Models\WbsPhaseChecklistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Feature\V19\Concerns\V19Fixtures;
use Tests\TestCase;

/**
 * V1.11 — WBS Phase · I-1 (WBS-01..05): HTTP authorization wiring.
 *
 * Proves the WbsPhasePolicy wiring on the real routes, including the
 * §12 attack matrix:
 *
 *   Supervisor(A) -> WBS(A)            = 200 / allowed
 *   Supervisor(A) -> WBS(B)            = 403
 *   Supervisor(no membership) -> WBS   = 403
 *   Supervisor(ended A) -> WBS(A)      = 403
 *   Supervisor(A) -> decide(WBS(B))    = 403
 *   Supervisor(A) -> checklist(WBS(B)) = 403
 *   Admin -> decide(WBS)               = 403 (WBS-03b, route boundary)
 *   Contractor -> WBS                  = 403 (route boundary)
 *
 * Plus: request-body project_id tampering on store, index/dropdown scope,
 * the unchanged phase/item integrity abort(404), and A-vs-B separation
 * (authorization 403 is never a business-invariant 302+error flash).
 */
class WbsAuthorizationWiringTest extends TestCase
{
    use RefreshDatabase, V19Fixtures;

    private Project $projectA;

    private Project $projectB;

    private WbsPhase $phaseA;

    private WbsPhase $phaseB;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUserWithRole('admin');
        $this->projectA = $this->makeProject(1);
        $this->projectB = $this->makeProject(2);
        $this->phaseA = $this->makeWbsPhase($this->projectA, $this->admin, 'Phase A');
        $this->phaseB = $this->makeWbsPhase($this->projectB, $this->admin, 'Phase B');
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    private function scopeSupervisorTo(User $user, Project $project): void
    {
        app(ProjectMembershipService::class)->assignSupervisor(
            new AssignProjectSupervisorData(
                project_id: $project->id,
                user_id: $user->id,
                assigned_by: $user->id
            ),
            $user
        );
    }

    private function supervisorA(): User
    {
        $user = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorTo($user, $this->projectA);

        return $user;
    }

    private function supervisorEndedA(): User
    {
        $user = $this->supervisorA();
        app(ProjectMembershipService::class)->endActiveSupervisor($this->projectA, $user);

        return $user->fresh();
    }

    /**
     * project_manager (passes the write middleware role:admin|pm|employer)
     * whose Project Scope is restricted to project A via an active supervisor
     * membership — the only actor that can reach Layer P on a WBS write route.
     */
    private function scopedPm(): User
    {
        $user = $this->makeUserWithRole('project_manager');
        Role::findOrCreate('supervisor', 'web');
        $user->assignRole('supervisor');
        $user = $user->fresh();
        $this->scopeSupervisorTo($user, $this->projectA);

        return $user;
    }

    private function assertPhaseUntouched(WbsPhase $phase): void
    {
        $this->assertSame('pending', $phase->fresh()->completion_status);
        $this->assertNull($phase->fresh()->supervisor_approved_at);
        $this->assertNull($phase->fresh()->supervisor_approved_by);
        $this->assertSame(0, WbsPhaseChecklistItem::where('wbs_phase_id', $phase->id)->count());
    }

    // ==================================================================
    // show — same project allowed, foreign denied
    // ==================================================================

    public function test_scoped_supervisor_can_show_own_project_phase(): void
    {
        $this->actingAs($this->supervisorA())
            ->get(route('wbs-phases.show', $this->phaseA->id))
            ->assertOk()
            ->assertSee('Phase A');
    }

    public function test_scoped_supervisor_cannot_show_foreign_phase(): void
    {
        $this->actingAs($this->supervisorA())
            ->get(route('wbs-phases.show', $this->phaseB->id))
            ->assertForbidden();
    }

    public function test_membershipless_supervisor_cannot_show_any_phase(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');

        $this->actingAs($supervisor)
            ->get(route('wbs-phases.show', $this->phaseA->id))
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->get(route('wbs-phases.show', $this->phaseB->id))
            ->assertForbidden();
    }

    public function test_ended_membership_supervisor_cannot_show_former_project_phase(): void
    {
        $this->actingAs($this->supervisorEndedA())
            ->get(route('wbs-phases.show', $this->phaseA->id))
            ->assertForbidden();
    }

    // ==================================================================
    // index scope + project dropdown scope (WBS-04 / WBS-04b)
    // ==================================================================

    public function test_index_shows_only_scoped_phases(): void
    {
        $response = $this->actingAs($this->supervisorA())
            ->get(route('wbs-phases.index'))
            ->assertOk();

        $phases = collect($response->viewData('phases'));
        $this->assertSame([$this->phaseA->id], $phases->pluck('id')->all());
    }

    public function test_index_dropdown_exposes_only_scoped_projects(): void
    {
        $response = $this->actingAs($this->supervisorA())
            ->get(route('wbs-phases.index'))
            ->assertOk();

        $projects = collect($response->viewData('projects'));
        $this->assertSame([$this->projectA->id], $projects->pluck('id')->all());
    }

    public function test_membershipless_supervisor_index_is_empty_including_dropdown(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');

        $response = $this->actingAs($supervisor)->get(route('wbs-phases.index'))->assertOk();

        $this->assertCount(0, collect($response->viewData('phases')));
        $this->assertCount(0, collect($response->viewData('projects')));
    }

    public function test_admin_index_remains_unrestricted(): void
    {
        $response = $this->actingAs($this->admin)->get(route('wbs-phases.index'))->assertOk();

        $this->assertCount(2, collect($response->viewData('phases')));
        $this->assertCount(2, collect($response->viewData('projects')));
    }

    // ==================================================================
    // store — foreign creation + project_id tampering (§12)
    // ==================================================================

    public function test_scoped_write_actor_cannot_create_phase_in_foreign_project(): void
    {
        $this->actingAs($this->scopedPm())
            ->post(route('wbs-phases.store'), [
                'project_id' => $this->projectB->id,
                'name' => 'Foreign Phase',
                'weight' => 0, // DEC-052: legacy weight field, untouched in I-1
                'planned_duration' => 10,
                'duration_unit' => 'day',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('wbs_phases', ['name' => 'Foreign Phase']);
    }

    public function test_store_body_project_id_tampering_is_denied_before_the_service(): void
    {
        // Project B already has a phase; a bypassed authorization would reach
        // the service and fail with a business error flash (302). A 403 proves
        // authorization fired FIRST.
        $this->actingAs($this->scopedPm())
            ->post(route('wbs-phases.store'), [
                'project_id' => $this->projectB->id,
                'name' => 'Tampered Phase',
                'weight' => 0,
                'planned_duration' => 5,
                'duration_unit' => 'day',
            ])
            ->assertForbidden()
            ->assertSessionMissing('status');

        $this->assertDatabaseMissing('wbs_phases', ['name' => 'Tampered Phase']);
        $this->assertSame(1, WbsPhase::where('project_id', $this->projectB->id)->count());
    }

    public function test_scoped_write_actor_can_create_phase_in_own_project(): void
    {
        $this->actingAs($this->scopedPm())
            ->post(route('wbs-phases.store'), [
                'project_id' => $this->projectA->id,
                'name' => 'Own Phase',
                'weight' => 0,
                'planned_duration' => 10,
                'duration_unit' => 'day',
            ])
            ->assertRedirect(route('wbs-phases.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('wbs_phases', [
            'name' => 'Own Phase',
            'project_id' => $this->projectA->id,
        ]);
    }

    // ==================================================================
    // decide — supervisor role boundary + project scope (WBS-03/03b)
    // ==================================================================

    public function test_scoped_supervisor_can_decide_own_project_phase(): void
    {
        $this->actingAs($this->supervisorA())
            ->post(route('wbs-phases.decide', $this->phaseA->id), [
                'decision' => 'complete',
                'comment' => 'reviewed',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('completed', $this->phaseA->fresh()->completion_status);
    }

    public function test_supervisor_cannot_decide_foreign_phase(): void
    {
        $this->actingAs($this->supervisorA())
            ->post(route('wbs-phases.decide', $this->phaseB->id), [
                'decision' => 'complete',
                'comment' => 'hijack attempt',
            ])
            ->assertForbidden();

        $this->assertPhaseUntouched($this->phaseB);
    }

    public function test_membershipless_supervisor_cannot_decide(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');

        $this->actingAs($supervisor)
            ->post(route('wbs-phases.decide', $this->phaseA->id), [
                'decision' => 'complete',
                'comment' => 'no membership',
            ])
            ->assertForbidden();

        $this->assertPhaseUntouched($this->phaseA);
    }

    public function test_ended_membership_supervisor_cannot_decide(): void
    {
        $this->actingAs($this->supervisorEndedA())
            ->post(route('wbs-phases.decide', $this->phaseA->id), [
                'decision' => 'complete',
                'comment' => 'ended membership',
            ])
            ->assertForbidden();

        $this->assertPhaseUntouched($this->phaseA);
    }

    public function test_admin_decide_remains_forbidden_wbs_03b(): void
    {
        // WBS-03b: the route role boundary (role:supervisor) is authoritative
        // and unchanged; unrestricted PROJECT SCOPE never implies WBS decide.
        $this->actingAs($this->admin)
            ->post(route('wbs-phases.decide', $this->phaseA->id), [
                'decision' => 'complete',
                'comment' => 'admin',
            ])
            ->assertForbidden();

        $this->assertPhaseUntouched($this->phaseA);
    }

    public function test_pm_employer_management_viewer_decide_remains_forbidden(): void
    {
        foreach (['project_manager', 'employer', 'management', 'viewer'] as $role) {
            $this->actingAs($this->makeUserWithRole($role))
                ->post(route('wbs-phases.decide', $this->phaseA->id), [
                    'decision' => 'complete',
                    'comment' => $role,
                ])
                ->assertForbidden();
        }

        $this->assertPhaseUntouched($this->phaseA);
    }

    // ==================================================================
    // checklist — foreign parent phase (WBS-05) + integrity 404 unchanged
    // ==================================================================

    public function test_scoped_write_actor_cannot_add_checklist_item_to_foreign_phase(): void
    {
        $this->actingAs($this->scopedPm())
            ->post(route('wbs-phases.checklist-items.store', $this->phaseB->id), [
                'title' => 'Foreign Item',
                'description' => 'attack',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('wbs_phase_checklist_items', ['title' => 'Foreign Item']);
        $this->assertPhaseUntouched($this->phaseB);
    }

    public function test_supervisor_cannot_complete_checklist_item_of_foreign_phase(): void
    {
        $item = app(WbsPhaseService::class)->addChecklistItem($this->phaseB, $this->admin, 'Foreign Item');

        $this->actingAs($this->supervisorA())
            ->post(route('wbs-phases.checklist-items.complete', [$this->phaseB->id, $item->id]))
            ->assertForbidden();

        $this->assertFalse((bool) $item->fresh()->is_completed);
    }

    public function test_supervisor_cannot_reopen_checklist_item_of_foreign_phase(): void
    {
        $item = app(WbsPhaseService::class)->addChecklistItem($this->phaseB, $this->admin, 'Foreign Item');
        app(WbsPhaseService::class)->completeChecklistItem($item, $this->admin);

        $this->actingAs($this->supervisorA())
            ->post(route('wbs-phases.checklist-items.reopen', [$this->phaseB->id, $item->id]))
            ->assertForbidden();

        $this->assertTrue((bool) $item->fresh()->is_completed);
    }

    public function test_scoped_write_actor_checklist_operations_work_on_own_phase(): void
    {
        $pm = $this->scopedPm();

        $this->actingAs($pm)
            ->post(route('wbs-phases.checklist-items.store', $this->phaseA->id), [
                'title' => 'Own Item',
                'description' => 'in scope',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $item = WbsPhaseChecklistItem::where('title', 'Own Item')->firstOrFail();

        $this->actingAs($pm)
            ->post(route('wbs-phases.checklist-items.complete', [$this->phaseA->id, $item->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertTrue((bool) $item->fresh()->is_completed);

        $this->actingAs($pm)
            ->post(route('wbs-phases.checklist-items.reopen', [$this->phaseA->id, $item->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertFalse((bool) $item->fresh()->is_completed);
    }

    public function test_phase_item_mismatch_remains_protected_as_404(): void
    {
        // In-scope actor (admin passes both layers): the existing integrity
        // guard — an item of phase B addressed via phase A — stays a 404 and
        // is NOT turned into an authorization result.
        $foreignItem = app(WbsPhaseService::class)->addChecklistItem($this->phaseB, $this->admin, 'B Item');

        $this->actingAs($this->admin)
            ->post(route('wbs-phases.checklist-items.complete', [$this->phaseA->id, $foreignItem->id]))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->post(route('wbs-phases.checklist-items.reopen', [$this->phaseA->id, $foreignItem->id]))
            ->assertNotFound();

        $this->assertFalse((bool) $foreignItem->fresh()->is_completed);
    }

    // ==================================================================
    // Contractor — existing role boundary blocks the whole WBS surface
    // ==================================================================

    public function test_contractor_is_denied_across_the_wbs_surface(): void
    {
        $contractor = $this->makeUserWithRole('contractor');

        $this->actingAs($contractor)->get(route('wbs-phases.index'))->assertForbidden();
        $this->actingAs($contractor)->get(route('wbs-phases.show', $this->phaseA->id))->assertForbidden();

        $this->actingAs($contractor)
            ->post(route('wbs-phases.store'), [
                'project_id' => $this->projectA->id,
                'name' => 'Contractor Phase',
                'weight' => 0,
                'planned_duration' => 5,
                'duration_unit' => 'day',
            ])
            ->assertForbidden();

        $this->actingAs($contractor)
            ->post(route('wbs-phases.decide', $this->phaseA->id), [
                'decision' => 'complete',
                'comment' => 'contractor',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('wbs_phases', ['name' => 'Contractor Phase']);
        $this->assertPhaseUntouched($this->phaseA);
    }

    // ==================================================================
    // I-2 — role-preservation pins (§7 / §10): the policy must never
    // silently BROADEN or RESTRICT the existing HTTP role/scope semantics.
    // These pins prove the I-1 wiring preserved current behavior for every
    // non-supervisor read role and for the unrestricted create roles.
    // ==================================================================

    public function test_admin_and_management_show_remains_unrestricted(): void
    {
        $this->actingAs($this->admin)->get(route('wbs-phases.show', $this->phaseB->id))->assertOk();
        $this->actingAs($this->makeUserWithRole('management'))->get(route('wbs-phases.show', $this->phaseB->id))->assertOk();
    }

    public function test_management_index_remains_unrestricted(): void
    {
        // §7: management is unrestricted on the scoped index — both projects'
        // phases are visible, never filtered by the scope service.
        $response = $this->actingAs($this->makeUserWithRole('management'))
            ->get(route('wbs-phases.index'))
            ->assertOk();

        $this->assertCount(2, collect($response->viewData('phases')));
        $this->assertCount(2, collect($response->viewData('projects')));
    }

    public function test_pm_employer_viewer_show_preserves_current_behavior(): void
    {
        // Q1/DEC-048 + DR-EMP-01 (OPEN): PM/employer/viewer derive no project
        // scope — current unrestricted READ behavior is pinned, not endorsed.
        $this->actingAs($this->makeUserWithRole('project_manager'))->get(route('wbs-phases.show', $this->phaseB->id))->assertOk();
        $this->actingAs($this->makeUserWithRole('employer'))->get(route('wbs-phases.show', $this->phaseB->id))->assertOk();
        $this->actingAs($this->makeUserWithRole('viewer'))->get(route('wbs-phases.show', $this->phaseB->id))->assertOk();
    }

    public function test_admin_and_pm_create_remains_allowed_in_any_project(): void
    {
        // §9 counterpart: the tampering guard restricts SCOPED actors only —
        // it must not over-restrict the intentionally unrestricted create
        // roles (admin / PM pass middleware + null scope).
        $this->actingAs($this->admin)
            ->post(route('wbs-phases.store'), [
                'project_id' => $this->projectB->id,
                'name' => 'Admin Phase',
                'weight' => 0,
                'planned_duration' => 10,
                'duration_unit' => 'day',
            ])
            ->assertRedirect(route('wbs-phases.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('wbs_phases', [
            'name' => 'Admin Phase',
            'project_id' => $this->projectB->id,
        ]);

        $this->actingAs($this->makeUserWithRole('project_manager'))
            ->post(route('wbs-phases.store'), [
                'project_id' => $this->projectB->id,
                'name' => 'Pm Phase',
                'weight' => 0,
                'planned_duration' => 10,
                'duration_unit' => 'day',
            ])
            ->assertRedirect(route('wbs-phases.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('wbs_phases', [
            'name' => 'Pm Phase',
            'project_id' => $this->projectB->id,
        ]);
    }

    // ==================================================================
    // I-2 — architecture pins (§13): the wiring must stay single-layered.
    // ==================================================================

    public function test_no_second_authorization_mechanism_exists(): void
    {
        // No Gate::before / dynamic permission mapping introduced anywhere.
        $providers = glob(app_path('Providers/*.php'));
        foreach ($providers as $provider) {
            $this->assertStringNotContainsString('Gate::before', (string) file_get_contents($provider));
        }

        // ProjectScopeService stays the canonical scope source: the
        // controller must not hand-roll membership SQL (no membership_type
        // / ended_at literals outside the service).
        $controller = (string) file_get_contents(app_path('Http/Controllers/Web/WbsPhaseController.php'));
        $this->assertStringNotContainsString('membership_type', $controller);
        $this->assertStringNotContainsString('ended_at', $controller);
        $this->assertStringContainsString('applyProjectScope', $controller);
    }

    // ==================================================================
    // A vs B — authorization denial is never a business-invariant denial
    // ==================================================================

    public function test_business_invariant_failure_is_not_an_authorization_failure(): void
    {
        // In-scope supervisor, invariant-violating decision (pending phase,
        // reopen is terminal-only) → service guard rejects with 302 + error.
        $this->actingAs($this->supervisorA())
            ->post(route('wbs-phases.decide', $this->phaseA->id), [
                'decision' => 'reopen',
                'comment' => 'not decided yet',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        // The SAME invalid payload on a FOREIGN phase is a 403 —
        // authorization denies before the invariant is ever evaluated.
        $this->actingAs($this->supervisorA())
            ->post(route('wbs-phases.decide', $this->phaseB->id), [
                'decision' => 'reopen',
                'comment' => 'not decided yet',
            ])
            ->assertForbidden()
            ->assertSessionMissing('error');
    }
}
