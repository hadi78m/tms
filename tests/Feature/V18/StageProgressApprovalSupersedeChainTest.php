<?php

namespace Tests\Feature\V18;

use App\Domain\Exceptions\InvalidApprovalException;
use App\Domain\Exceptions\PendingRequestExistsException;
use App\Domain\Services\StageProgressApprovalService;
use App\Models\ModuleStage;
use App\Models\StageProgressApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\V18\Concerns\V18Fixtures;
use Tests\TestCase;

/**
 * T-1 — the supersession chain invariant.
 *
 * INTENDED INVARIANT
 *   Every approval has AT MOST ONE successor. "supersede" therefore means
 *   REPLACE, never ADD: correcting A to B must make A non-active, and the
 *   corrected value must take A's place — not join it.
 *
 * WHY THIS FILE EXISTS
 *   `UNIQUE(supersedes_approval_id)` was deliberately NOT applied in the V1.8
 *   migrations (`docs/V1.8_DETAILED_SCHEMA_DESIGN.md` §۴.۳) and the owner
 *   accepted that as-is. PostgreSQL therefore permits a fork at the storage
 *   layer — proven by `test_t1_00_database_permits_a_fork_without_the_unique`.
 *   That makes the Application Layer the ONLY line of defence, so these tests
 *   pin the invariant at the service boundary.
 *
 *   Before the fix in this phase, `supersede()` allowed a fork whenever the
 *   first successor was no longer pending, which turned a "correction" into an
 *   ADDITION (A=5 superseded by B=7, then A "corrected" to C=3 yielded 10).
 */
class StageProgressApprovalSupersedeChainTest extends TestCase
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

    private function successorCount(StageProgressApproval $approval): int
    {
        return DB::table('stage_progress_approvals')
            ->where('supersedes_approval_id', $approval->id)
            ->count();
    }

    /**
     * DOCUMENTED LIMITATION (owner-accepted): the storage layer has no UNIQUE on
     * supersedes_approval_id, so a fork IS representable in raw SQL. This test
     * exists to fail loudly if that ever becomes untrue, so the documentation can
     * be corrected.
     */
    public function test_t1_00_database_permits_a_fork_without_the_unique(): void
    {
        $userId = $this->supervisor->id;

        $original = DB::table('stage_progress_approvals')->insertGetId([
            'module_stage_id' => $this->analysis->id,
            'module_id' => $this->analysis->module_id,
            'proposed_amount' => 5, 'approved_amount' => 5, 'status' => 'approved',
            'proposed_by' => $userId, 'final_approved_by' => $userId, 'decided_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([7, 9] as $amount) {
            DB::table('stage_progress_approvals')->insert([
                'module_stage_id' => $this->analysis->id,
                'module_id' => $this->analysis->module_id,
                'proposed_amount' => $amount, 'status' => 'pending',
                'proposed_by' => $userId, 'supersedes_approval_id' => $original,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->assertSame(
            2,
            DB::table('stage_progress_approvals')->where('supersedes_approval_id', $original)->count(),
            'No UNIQUE constraint exists, so the Application Layer must enforce the single-successor rule.'
        );
    }

    /** T1-01 */
    public function test_t1_01_a_superseded_row_has_exactly_one_successor(): void
    {
        $original = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $replacement = $this->service()->supersede($original, $this->supervisor, 7);

        $this->assertSame(1, $this->successorCount($original));
        $this->assertSame($original->id, $replacement->supersedes_approval_id);
        $this->assertTrue($replacement->isSuperseded() === false);
    }

    /** T1-02 */
    public function test_t1_02_a_superseded_row_can_never_be_decided(): void
    {
        $original = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $this->service()->supersede($original, $this->supervisor, 7);

        $this->expectException(InvalidApprovalException::class);

        $this->service()->approve($original, $this->supervisor, 5);
    }

    /** T1-03 — the core T-1 regression */
    public function test_t1_03_a_second_successor_cannot_be_created_while_the_first_is_pending(): void
    {
        $original = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $this->service()->supersede($original, $this->supervisor, 7);

        try {
            $this->service()->supersede($original, $this->supervisor, 9);
            $this->fail('A superseded row must not accept a second successor.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(InvalidApprovalException::class, $e);
        }

        $this->assertSame(1, $this->successorCount($original));
    }

    /**
     * T1-03 (the real hole): once the first successor is DECIDED nothing is
     * pending any more, so only an explicit "already superseded" guard stops a
     * second successor from being attached to the same historical row.
     */
    public function test_t1_03b_a_second_successor_cannot_be_created_after_the_first_is_decided(): void
    {
        $original = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $this->service()->approve($original, $this->supervisor, 5);

        $first = $this->service()->supersede($original, $this->supervisor, 7);
        $this->service()->approve($first, $this->supervisor, 7);

        $this->expectException(InvalidApprovalException::class);

        $this->service()->supersede($original, $this->supervisor, 3);
    }

    /** T1-04 — history is never rewritten */
    public function test_t1_04_superseding_never_rewrites_the_superseded_row(): void
    {
        $original = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $this->service()->approve($original, $this->supervisor, 5);

        $this->service()->supersede($original, $this->supervisor, 7);

        $after = $original->fresh();

        $this->assertSame('approved', $after->status);
        $this->assertEqualsWithDelta(5.0, (float) $after->approved_amount, 0.001);
        $this->assertNotNull($after->decided_at);
    }

    /** T1-05 */
    public function test_t1_05_only_the_active_successor_is_decidable(): void
    {
        $original = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $first = $this->service()->supersede($original, $this->supervisor, 7);

        // The active successor is the one without a successor of its own.
        $this->assertSame(0, $this->successorCount($first));
        $this->assertSame(1, $this->successorCount($original));

        $this->service()->approve($first, $this->supervisor, 7);

        $this->assertEqualsWithDelta(7.0, $this->service()->approvedWeight($this->analysis), 0.001);
    }

    /** T1-06 — a linear chain A → B → C keeps the arithmetic exact */
    public function test_t1_06_a_linear_chain_replaces_rather_than_accumulates(): void
    {
        $a = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $this->service()->approve($a, $this->supervisor, 5);

        $b = $this->service()->supersede($a, $this->supervisor, 7);
        $this->service()->approve($b, $this->supervisor, 7);
        $this->assertEqualsWithDelta(7.0, $this->service()->approvedWeight($this->analysis), 0.001);

        $c = $this->service()->supersede($b, $this->supervisor, 3);
        $this->service()->approve($c, $this->supervisor, 3);

        // A and B are both superseded; only C counts.
        $this->assertEqualsWithDelta(3.0, $this->service()->approvedWeight($this->analysis), 0.001);
        $this->assertEqualsWithDelta(12.0, $this->service()->remainingWeight($this->analysis), 0.001);

        // Exactly three rows: A → B → C. The chain REPLACES, it does not grow the
        // approved total, and it never rewrites A or B.
        $this->assertSame(3, DB::table('stage_progress_approvals')->count());
        $this->assertSame(1, $this->successorCount($a));
        $this->assertSame(1, $this->successorCount($b));
        $this->assertSame(0, $this->successorCount($c));
        $this->assertSame('approved', $a->fresh()->status);
        $this->assertEqualsWithDelta(5.0, (float) $a->fresh()->approved_amount, 0.001);
    }

    /** T1-07 — the fork attempt has an explicit, testable outcome */
    public function test_t1_07_the_fork_attempt_raises_an_explicit_exception(): void
    {
        $a = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $this->service()->approve($a, $this->supervisor, 5);

        $b = $this->service()->supersede($a, $this->supervisor, 7);
        $this->service()->approve($b, $this->supervisor, 7);

        $this->expectException(InvalidApprovalException::class);
        $this->expectExceptionMessageMatches('/superseded/i');

        $this->service()->supersede($a, $this->supervisor, 3);
    }

    /**
     * T1-07b — the specific shape that used to slip past the ceiling check:
     * A=5 → B=7 → "C=3" was accepted because 7+3 stayed under the 15 stage
     * weight, turning a correction into an addition. It must now be refused.
     */
    public function test_t1_07b_a_fork_that_fits_under_the_ceiling_is_still_refused(): void
    {
        $a = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $this->service()->approve($a, $this->supervisor, 5);

        $b = $this->service()->supersede($a, $this->supervisor, 7);
        $this->service()->approve($b, $this->supervisor, 7);

        try {
            $c = $this->service()->supersede($a, $this->supervisor, 3);
            $this->service()->approve($c, $this->supervisor, 3);
            $this->fail('The fork was accepted and the approved total became '
                .$this->service()->approvedWeight($this->analysis)
                .' instead of the intended 7.');
        } catch (InvalidApprovalException $e) {
            $this->assertSame(1, $this->successorCount($a));
            $this->assertEqualsWithDelta(7.0, $this->service()->approvedWeight($this->analysis), 0.001);
        }
    }

    /**
     * T1-08 — same invariant, different verb: `adjust()` must not rewrite a row
     * that is already history. Without the guard, a stale row id silently changes
     * what the historical proposal said.
     */
    public function test_t1_08_a_superseded_pending_row_cannot_be_adjusted(): void
    {
        $original = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $this->service()->supersede($original, $this->supervisor, 7);

        $this->expectException(InvalidApprovalException::class);

        $this->service()->adjust($original, $this->supervisor, 11);
    }

    /** T1-08b — and the frozen value really is frozen */
    public function test_t1_08b_the_historical_proposed_amount_is_unchanged(): void
    {
        $original = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $this->service()->supersede($original, $this->supervisor, 7);

        try {
            $this->service()->adjust($original, $this->supervisor, 11);
        } catch (InvalidApprovalException) {
            // expected
        }

        $this->assertEqualsWithDelta(5.0, (float) $original->fresh()->proposed_amount, 0.001);
    }

    /**
     * The correction path that must KEEP working: replacing a live pending
     * proposal is legitimate and is still allowed.
     */
    public function test_t1_09_replacing_a_live_pending_proposal_still_works(): void
    {
        $first = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $second = $this->service()->supersede($first, $this->supervisor, 9);

        $this->assertSame($first->id, $second->supersedes_approval_id);
        $this->assertTrue($first->fresh()->isSuperseded());

        $this->service()->approve($second, $this->supervisor, 9);

        $this->assertEqualsWithDelta(9.0, $this->service()->approvedWeight($this->analysis), 0.001);
        $this->assertSame(0, $this->successorCount($second));
    }

    /**
     * Two different rows may of course each have their own successor — the
     * invariant is per-row, not per-stage.
     */
    public function test_t1_10_independent_rows_each_keep_their_own_successor(): void
    {
        $a = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $this->service()->approve($a, $this->supervisor, 5);

        $b = $this->service()->propose($this->analysis, $this->supervisor, 6);
        $this->service()->approve($b, $this->supervisor, 6);

        // Only one proposal may be pending at a time, so each replacement has to
        // be decided before the next correction starts.
        $aReplacement = $this->service()->supersede($a, $this->supervisor, 2);
        $this->service()->approve($aReplacement, $this->supervisor, 2);

        $bReplacement = $this->service()->supersede($b, $this->supervisor, 3);
        $this->service()->approve($bReplacement, $this->supervisor, 3);

        $this->assertSame(1, $this->successorCount($a));
        $this->assertSame(1, $this->successorCount($b));

        $this->assertEqualsWithDelta(5.0, $this->service()->approvedWeight($this->analysis), 0.001);
    }

    /** A row still pending and NOT superseded keeps being the one pending proposal. */
    public function test_t1_11_a_superseded_pending_row_does_not_block_the_next_proposal(): void
    {
        $original = $this->service()->propose($this->analysis, $this->supervisor, 5);
        $replacement = $this->service()->supersede($original, $this->supervisor, 9);

        $this->service()->approve($replacement, $this->supervisor, 9);

        $next = $this->service()->propose($this->analysis, $this->supervisor, 6);

        $this->assertSame('pending', $next->fresh()->status);

        $this->service()->approve($next, $this->supervisor, 6);

        $this->assertEqualsWithDelta(15.0, $this->service()->approvedWeight($this->analysis), 0.001);
    }

    /** Proposing twice without deciding the first is still refused. */
    public function test_t1_12_proposing_twice_is_still_refused(): void
    {
        $this->service()->propose($this->analysis, $this->supervisor, 5);

        $this->expectException(PendingRequestExistsException::class);

        $this->service()->propose($this->analysis, $this->supervisor, 6);
    }
}
