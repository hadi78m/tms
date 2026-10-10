<?php

namespace Tests\Feature\V111;

use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Domain\Rules\ProjectScopeService;
use App\Domain\Services\ProjectMembershipService;
use App\Domain\Services\WbsPhaseService;
use App\Models\Project;
use App\Models\User;
use App\Models\WbsPhase;
use App\Models\WbsPhaseChecklistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Feature\V19\Concerns\V19Fixtures;
use Tests\TestCase;

/**
 * V1.11 — WBS Phase · I-3: Security / Cross-Project Attack Matrix.
 *
 * TEST-FIRST SECURITY VALIDATION. This gate adds NO production code: it proves
 * that the I-1/I-2 authorization cannot be bypassed by cross-project access or
 * request tampering, and that the four independent layers stay independent:
 *
 *   Role Authorization        (route middleware — unchanged, authoritative)
 * + Resource Authorization    (WbsPhasePolicy)
 * + Project Scope            (ProjectScopeService — canonical, untouched)
 * + Business Invariants      (WbsPhaseService guards — untouched)
 *
 * Pinned independence (§4):
 *   Permission      != Project Scope    → a direct permission grant never widens scope
 *   Project Scope   != Business Invariant → foreign resource = 403, invariant = 302
 *   Role            != Project Membership → role without membership = 403
 *   Policy          != Service Guard     → service rejections stay 302+error, never 403
 *
 * 403 (authorization) vs 404 (data-integrity) semantics are asserted explicitly
 * and are never conflated.
 */
class WbsCrossProjectAttackMatrixTest extends TestCase
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

    /** Supervisor B — legitimately scoped to the foreign project. */
    private function supervisorB(): User
    {
        $user = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorTo($user, $this->projectB);

        return $user;
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
     * Combined role: project_manager (write middleware) + supervisor
     * (scope-restricting role) with an ACTIVE membership in project A only.
     * The PM role must NOT bypass the supervisor membership scope (DEC-048).
     */
    private function supervisorAndPm(): User
    {
        $user = $this->makeUserWithRole('project_manager');
        Role::findOrCreate('supervisor', 'web');
        $user->assignRole('supervisor');
        $user = $user->fresh();
        $this->scopeSupervisorTo($user, $this->projectA);

        return $user;
    }

    private function phaseSnapshot(WbsPhase $phase): array
    {
        $fresh = $phase->fresh();

        return [
            'completion_status' => $fresh->completion_status,
            'supervisor_comment' => $fresh->supervisor_comment,
            'supervisor_approved_by' => $fresh->supervisor_approved_by,
            'supervisor_approved_at' => $fresh->supervisor_approved_at,
            'actual_completion' => $fresh->actual_completion,
            'items' => WbsPhaseChecklistItem::where('wbs_phase_id', $phase->id)->count(),
        ];
    }

    private function wbsAuditCount(): int
    {
        return DB::table('activity_logs')->where('action', 'like', 'wbs_phase%')->count();
    }

    // ==================================================================
    // §6 — Supervisor cross-project matrix (show / decide / checklist)
    // ==================================================================

    public function test_supervisor_a_is_denied_on_every_foreign_project_b_operation(): void
    {
        $supervisor = $this->supervisorA();

        // show
        $this->actingAs($supervisor)->get(route('wbs-phases.show', $this->phaseB->id))->assertForbidden();

        // decide (all three decision verbs)
        foreach (['complete', 'not_completed', 'reopen'] as $decision) {
            $this->actingAs($supervisor)
                ->post(route('wbs-phases.decide', $this->phaseB->id), [
                    'decision' => $decision,
                    'comment' => 'attack '.$decision,
                ])
                ->assertForbidden();
        }

        $this->assertSame('pending', $this->phaseB->fresh()->completion_status);
    }

    public function test_supervisor_b_remains_unrestricted_on_its_own_project(): void
    {
        // Symmetry proof: the denial above is PROJECT SCOPE, not a broken
        // supervisor path — the same verbs succeed for the legitimate owner.
        $supervisor = $this->supervisorB();

        $this->actingAs($supervisor)->get(route('wbs-phases.show', $this->phaseB->id))->assertOk();

        $this->actingAs($supervisor)
            ->post(route('wbs-phases.decide', $this->phaseB->id), [
                'decision' => 'complete',
                'comment' => 'mine',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('completed', $this->phaseB->fresh()->completion_status);
    }

    public function test_supervisor_cannot_create_phase_even_in_own_project_role_boundary(): void
    {
        // The write role boundary (role:admin|pm|employer) rejects supervisor
        // BEFORE the policy — acceptable per §6, and asserted so a future
        // middleware change cannot silently widen the create surface.
        $this->actingAs($this->supervisorA())
            ->post(route('wbs-phases.store'), [
                'project_id' => $this->projectA->id,
                'name' => 'Supervisor Phase',
                'weight' => 0,
                'planned_duration' => 10,
                'duration_unit' => 'day',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('wbs_phases', ['name' => 'Supervisor Phase']);
    }

    // ==================================================================
    // §8 — Combined role matrix: Supervisor + PM must not bypass scope
    // ==================================================================

    public function test_combined_supervisor_and_pm_role_cannot_bypass_project_scope(): void
    {
        $actor = $this->supervisorAndPm();

        // Own project: allowed (supervisor membership in A).
        $this->actingAs($actor)->get(route('wbs-phases.show', $this->phaseA->id))->assertOk();

        // Foreign project: denied — the PM role does not grant project scope.
        $this->actingAs($actor)->get(route('wbs-phases.show', $this->phaseB->id))->assertForbidden();

        $this->actingAs($actor)->get(route('wbs-phases.index'))->assertOk();
        $indexPhases = collect($this->actingAs($actor)->get(route('wbs-phases.index'))->viewData('phases'));
        $this->assertSame([$this->phaseA->id], $indexPhases->pluck('id')->all());
    }

    public function test_combined_role_cannot_decide_or_checklist_a_foreign_phase(): void
    {
        $actor = $this->supervisorAndPm();

        $this->actingAs($actor)
            ->post(route('wbs-phases.decide', $this->phaseB->id), [
                'decision' => 'complete',
                'comment' => 'combined role attack',
            ])
            ->assertForbidden();

        $this->actingAs($actor)
            ->post(route('wbs-phases.checklist-items.store', $this->phaseB->id), [
                'title' => 'Combined Role Item',
                'description' => 'attack',
            ])
            ->assertForbidden();

        $this->assertSame('pending', $this->phaseB->fresh()->completion_status);
        $this->assertDatabaseMissing('wbs_phase_checklist_items', ['title' => 'Combined Role Item']);
    }

    // ==================================================================
    // §9 / §10 — Create tampering and create preservation
    // ==================================================================

    public function test_create_tampering_is_evaluated_against_the_submitted_project(): void
    {
        // project_id = B is authorized against B, never against the actor's
        // "home" project A.
        $this->actingAs($this->supervisorAndPm())
            ->post(route('wbs-phases.store'), [
                'project_id' => $this->projectB->id,
                'name' => 'Tampered Combined',
                'weight' => 0,
                'planned_duration' => 10,
                'duration_unit' => 'day',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('wbs_phases', ['name' => 'Tampered Combined']);
    }

    public function test_unrestricted_create_roles_remain_able_to_create_in_both_projects(): void
    {
        // No over-restriction introduced by the policy (DEC-048 semantics):
        // admin / PM / employer derive no project scope.
        foreach (['admin', 'project_manager', 'employer'] as $role) {
            $actor = $role === 'admin' ? $this->admin : $this->makeUserWithRole($role);

            foreach ([$this->projectA, $this->projectB] as $project) {
                $name = $role.' Phase '.$project->id;

                $this->actingAs($actor)
                    ->post(route('wbs-phases.store'), [
                        'project_id' => $project->id,
                        'name' => $name,
                        'weight' => 0,
                        'planned_duration' => 10,
                        'duration_unit' => 'day',
                    ])
                    ->assertRedirect(route('wbs-phases.index'))
                    ->assertSessionHas('status');

                $this->assertDatabaseHas('wbs_phases', [
                    'name' => $name,
                    'project_id' => $project->id,
                ]);
            }
        }
    }

    // ==================================================================
    // §11 / §12 — Index / show leakage (server-side, not UI hiding)
    // ==================================================================

    public function test_index_leaks_no_foreign_project_data_to_scoped_supervisor(): void
    {
        $response = $this->actingAs($this->supervisorA())
            ->get(route('wbs-phases.index'))
            ->assertOk();

        // Server-side filtering: the foreign phase/project is absent from the
        // rendered response, not merely hidden by the UI.
        $response->assertSee('Phase A');
        $response->assertDontSee('Phase B');
        $response->assertDontSee('Project 2');

        $this->assertSame(
            [$this->phaseA->id],
            collect($response->viewData('phases'))->pluck('id')->all()
        );
        $this->assertSame(
            [$this->projectA->id],
            collect($response->viewData('projects'))->pluck('id')->all()
        );
    }

    public function test_index_unrestricted_behavior_preserved_for_admin_and_management(): void
    {
        foreach (['admin', 'management'] as $role) {
            $response = $this->actingAs($role === 'admin' ? $this->admin : $this->makeUserWithRole($role))
                ->get(route('wbs-phases.index'))
                ->assertOk();

            $response->assertSee('Phase A');
            $response->assertSee('Phase B');
            $this->assertCount(2, collect($response->viewData('phases')));
        }
    }

    public function test_show_denial_is_server_side_and_leaks_no_phase_content(): void
    {
        $response = $this->actingAs($this->supervisorA())
            ->get(route('wbs-phases.show', $this->phaseB->id))
            ->assertForbidden();

        $this->assertStringNotContainsString('Phase B', (string) $response->getContent());
        $this->assertStringNotContainsString('Project 2', (string) $response->getContent());
    }

    // ==================================================================
    // §13 — 403 (authorization) vs 404 (data integrity)
    // ==================================================================

    public function test_phase_item_mismatch_layer_is_pinned_per_actor(): void
    {
        // Same mismatch (phase A addressed with an item of phase B) produces
        // DIFFERENT results per actor, because two independent layers reject:
        //
        //   Layer M (role middleware, role:admin|project_manager|employer)
        //        → supervisor is denied with 403 BEFORE the resource is even
        //          resolved: no existence leak, no integrity evaluation.
        //   Layer P (WbsPhasePolicy) + integrity guard
        //        → an actor inside the write boundary AND in scope of project A
        //          reaches the integrity check → 404 (data-integrity failure).
        //
        // The 403-vs-404 distinction is therefore layer-driven, never conflated.
        $foreignItem = app(WbsPhaseService::class)->addChecklistItem($this->phaseB, $this->admin, 'B Item');

        $this->actingAs($this->supervisorA())
            ->post(route('wbs-phases.checklist-items.complete', [$this->phaseA->id, $foreignItem->id]))
            ->assertForbidden();

        $this->actingAs($this->supervisorAndPm())
            ->post(route('wbs-phases.checklist-items.complete', [$this->phaseA->id, $foreignItem->id]))
            ->assertNotFound();

        $this->actingAs($this->supervisorAndPm())
            ->post(route('wbs-phases.checklist-items.reopen', [$this->phaseA->id, $foreignItem->id]))
            ->assertNotFound();

        $this->assertFalse((bool) $foreignItem->fresh()->is_completed);
    }

    public function test_foreign_phase_checklist_operations_remain_403_for_supervisor_a(): void
    {
        $supervisor = $this->supervisorA();
        $item = app(WbsPhaseService::class)->addChecklistItem($this->phaseB, $this->admin, 'B Item');
        app(WbsPhaseService::class)->completeChecklistItem($item, $this->admin);

        $before = $this->phaseSnapshot($this->phaseB);

        $this->actingAs($supervisor)
            ->post(route('wbs-phases.checklist-items.store', $this->phaseB->id), [
                'title' => 'Attack Item',
                'description' => 'attack',
            ])
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->post(route('wbs-phases.checklist-items.complete', [$this->phaseB->id, $item->id]))
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->post(route('wbs-phases.checklist-items.reopen', [$this->phaseB->id, $item->id]))
            ->assertForbidden();

        $this->assertSame($before, $this->phaseSnapshot($this->phaseB));
        $this->assertDatabaseMissing('wbs_phase_checklist_items', ['title' => 'Attack Item']);
    }

    // ==================================================================
    // §14 / §15 — Decide side-effect protection + Admin decide
    // ==================================================================

    public function test_unauthorized_decide_attempts_leave_no_state_or_audit_trace(): void
    {
        $attackers = [
            'foreign supervisor' => $this->supervisorA(),
            'membershipless supervisor' => $this->makeUserWithRole('supervisor'),
            'ended membership supervisor' => $this->supervisorEndedA(),
            'admin' => $this->admin,
        ];

        foreach ($attackers as $label => $actor) {
            // Fresh, untouched target for every attacker.
            $target = $this->makeWbsPhase($this->projectB, $this->admin, 'Target '.$label);

            $beforeState = $this->phaseSnapshot($target);
            $beforeAudit = $this->wbsAuditCount();

            $this->actingAs($actor)
                ->post(route('wbs-phases.decide', $target->id), [
                    'decision' => 'complete',
                    'comment' => 'unauthorized by '.$label,
                ])
                ->assertForbidden();

            // WBS state, completion state, rejection reason and columns unchanged.
            $this->assertSame($beforeState, $this->phaseSnapshot($target), $label.' mutated phase state');

            // No business transition and no audit transition was written.
            $this->assertSame($beforeAudit, $this->wbsAuditCount(), $label.' wrote an audit transition');

            $this->assertDatabaseMissing('activity_logs', [
                'entity_type' => $target->getMorphClass(),
                'entity_id' => $target->id,
                'action' => 'wbs_phase_completed',
            ]);
        }
    }

    public function test_admin_decide_is_403_with_unrestricted_project_scope(): void
    {
        $beforeState = $this->phaseSnapshot($this->phaseA);
        $beforeAudit = $this->wbsAuditCount();

        $this->assertTrue(app(ProjectScopeService::class)->canAccessProject($this->projectA, $this->admin));

        $this->actingAs($this->admin)
            ->post(route('wbs-phases.decide', $this->phaseA->id), [
                'decision' => 'complete',
                'comment' => 'admin scope is not WBS decide',
            ])
            ->assertForbidden();

        $this->assertSame($beforeState, $this->phaseSnapshot($this->phaseA));
        $this->assertSame($beforeAudit, $this->wbsAuditCount());
    }

    // ==================================================================
    // §16 — Direct permission grant cannot bypass Project Scope
    // ==================================================================

    public function test_direct_permission_grant_cannot_bypass_project_scope(): void
    {
        // A directly granted catalog permission (defensive test — the catalog
        // itself is NOT modified; only an existing-style permission is granted)
        // must not widen Project Scope.
        $permission = Permission::findOrCreate('manage projects', 'web');

        // No membership at all → still fully denied.
        $noMembership = $this->makeUserWithRole('supervisor');
        $noMembership->givePermissionTo($permission);

        $this->actingAs($noMembership)->get(route('wbs-phases.show', $this->phaseA->id))->assertForbidden();
        $this->actingAs($noMembership)
            ->post(route('wbs-phases.decide', $this->phaseA->id), [
                'decision' => 'complete',
                'comment' => 'permission only',
            ])
            ->assertForbidden();

        $indexPhases = collect(
            $this->actingAs($noMembership)->get(route('wbs-phases.index'))->assertOk()->viewData('phases')
        );
        $this->assertCount(0, $indexPhases);

        // Membership in A + permission → foreign project B still denied.
        $scoped = $this->supervisorA();
        $scoped->givePermissionTo($permission);

        $this->actingAs($scoped)->get(route('wbs-phases.show', $this->phaseA->id))->assertOk();
        $this->actingAs($scoped)->get(route('wbs-phases.show', $this->phaseB->id))->assertForbidden();

        // Role without permission still allowed on its own project —
        // permission is not the authorization source either.
        $this->assertSame('pending', $this->phaseA->fresh()->completion_status);
    }

    // ==================================================================
    // §17 — Business invariants remain service-owned (Policy != Service Guard)
    // ==================================================================

    public function test_business_invariants_remain_owned_by_the_service(): void
    {
        $supervisor = $this->supervisorA();

        // (a) pending-only completion: completing twice is a SERVICE rejection.
        $this->actingAs($supervisor)
            ->post(route('wbs-phases.decide', $this->phaseA->id), [
                'decision' => 'complete',
                'comment' => 'first',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->actingAs($supervisor)
            ->post(route('wbs-phases.decide', $this->phaseA->id), [
                'decision' => 'complete',
                'comment' => 'second',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('completed', $this->phaseA->fresh()->completion_status);

        // (b) terminal-only reopen: reopen of a decided phase is allowed and
        // returns it to pending; reopen of the pending phase then fails.
        $this->actingAs($supervisor)
            ->post(route('wbs-phases.decide', $this->phaseA->id), [
                'decision' => 'reopen',
                'comment' => 'evidence withdrawn',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('pending', $this->phaseA->fresh()->completion_status);

        $this->actingAs($supervisor)
            ->post(route('wbs-phases.decide', $this->phaseA->id), [
                'decision' => 'reopen',
                'comment' => 'still pending',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        // (c) mandatory rejection reason: blank comment is refused by the
        // service guard, and the phase stays pending.
        $this->actingAs($supervisor)
            ->post(route('wbs-phases.decide', $this->phaseA->id), [
                'decision' => 'not_completed',
                'comment' => '',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('pending', $this->phaseA->fresh()->completion_status);
        $this->assertNull($this->phaseA->fresh()->supervisor_approved_at);

        // (d) completion is independent from tasks, and transitions are audited.
        $this->assertDatabaseHas('activity_logs', [
            'entity_type' => $this->phaseA->getMorphClass(),
            'entity_id' => $this->phaseA->id,
            'action' => 'wbs_phase_completed',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'entity_type' => $this->phaseA->getMorphClass(),
            'entity_id' => $this->phaseA->id,
            'action' => 'wbs_phase_reopened',
        ]);
    }

    public function test_checklist_integrity_and_state_rules_remain_service_owned(): void
    {
        // Actor passes BOTH layers: write role boundary (PM) + active
        // supervisor membership in project A (scope) — so every rejection
        // below is a SERVICE invariant, never an authorization result.
        $supervisor = $this->supervisorAndPm();

        $this->actingAs($supervisor)
            ->post(route('wbs-phases.checklist-items.store', $this->phaseA->id), [
                'title' => 'SRS',
                'description' => 'requirements',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $item = WbsPhaseChecklistItem::where('title', 'SRS')->firstOrFail();
        $this->assertFalse((bool) $item->is_completed);

        $this->actingAs($supervisor)
            ->post(route('wbs-phases.checklist-items.complete', [$this->phaseA->id, $item->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertTrue((bool) $item->fresh()->is_completed);

        $this->actingAs($supervisor)
            ->post(route('wbs-phases.checklist-items.reopen', [$this->phaseA->id, $item->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertFalse((bool) $item->fresh()->is_completed);

        $this->assertDatabaseHas('activity_logs', ['action' => 'wbs_phase_checklist_item_completed']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'wbs_phase_checklist_item_reopened']);
    }
}
