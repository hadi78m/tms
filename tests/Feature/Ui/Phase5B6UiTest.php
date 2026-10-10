<?php

namespace Tests\Feature\Ui;

use App\Domain\Services\StageProgressApprovalService;
use App\Models\Module;
use App\Models\ModuleStage;
use App\Models\Project;
use App\Models\User;
use App\Support\TaskBoard\BoardColumns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V19\Concerns\V19Fixtures;
use Tests\TestCase;

use function app;

/**
 * Phase 5B-6 — Visual Migration of modules.show + modules.stages.show.
 *
 * Pins the CONTRACTS of the migration, not its pixels:
 *   - both pages keep their authorization boundaries (guest redirect,
 *     contractor 403, project_manager 200);
 *   - form actions, methods, CSRF, field names, hidden inputs, min/max/step,
 *     required/disabled, maxlength and value attributes are unchanged;
 *   - the weight snapshot still renders the controller-supplied values
 *     (weight / approved / remaining / lock state) without recalculation;
 *   - approval status badges keep their exact Persian labels while using the
 *     design-system variants, and the pending/decided action forms keep their
 *     decision + supersedes hidden inputs;
 *   - the task list keeps its tasks.show link and now renders the canonical
 *     BoardColumns status presentation (the established mapping);
 *   - the legacy slate/old-palette vocabulary is gone from both pages.
 *
 * Like Phase5B2..5B5UiTest, these tests avoid decorative class-by-class
 * assertions for styling that carries no contract.
 */
class Phase5B6UiTest extends TestCase
{
    use RefreshDatabase, V19Fixtures;

    /** Legacy colour tokens that must be gone from both migrated pages. */
    private const LEGACY_MARKUP = [
        'blue-600',
        'blue-700',
        'green-600',
        'green-700',
        'red-600',
        'red-700',
        'amber-600',
        'amber-700',
        'rounded-xl',
        'bg-slate',
        'text-slate',
        'border-slate',
    ];

    private Project $project;

    private User $manager;

    private User $supervisor;

    private Module $module;

    private ModuleStage $stage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makeProject(1);
        $this->manager = $this->makeUserWithRole('project_manager');
        $this->supervisor = $this->makeUserWithRole('supervisor');
        $this->module = $this->makeSingleModule($this->project, $this->manager);
        $this->stage = $this->module->stages()->firstOrFail();
    }

    private function assertNoLegacyMarkup(string $html, string $page): void
    {
        foreach (self::LEGACY_MARKUP as $legacy) {
            $this->assertStringNotContainsString(
                $legacy,
                $html,
                "{$page} still contains legacy presentation markup: {$legacy}"
            );
        }

        // "slate-N" as a colour token (the lookbehind skips "tranSLATE-x").
        $this->assertDoesNotMatchRegularExpression(
            '/(?<!tran)slate-\d/u',
            $html,
            "{$page} still contains a legacy slate colour token"
        );
    }

    // ==================================================================
    // modules.stages.show — authorization
    // ==================================================================

    public function test_stage_show_keeps_its_authorization_boundary(): void
    {
        $this->get(route('modules.stages.show', $this->stage->id))
            ->assertRedirect(route('login'));

        $contractor = $this->makeUserWithRole('contractor');

        $this->actingAs($contractor)
            ->get(route('modules.stages.show', $this->stage->id))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->get(route('modules.stages.show', $this->stage->id))
            ->assertOk();
    }

    // ==================================================================
    // modules.stages.show — identity + weight snapshot
    // ==================================================================

    public function test_stage_show_renders_identity_heading_and_back_link(): void
    {
        $html = $this->actingAs($this->manager)
            ->get(route('modules.stages.show', $this->stage->id))
            ->assertOk()
            ->getContent();

        // h1 keeps its level and carries the stage name (heading architecture untouched).
        $this->assertMatchesRegularExpression(
            '/<h1[^>]*>'.preg_quote($this->stage->name, '/').'<\/h1>/u',
            $html
        );

        // Same back-link target as before.
        $this->assertStringContainsString(
            'href="'.route('modules.show', $this->module->project_id).'"',
            $html
        );

        $this->assertStringContainsString('ماژول '.$this->module->name, $html);

        // Local heading levels preserved: both section h2s survive.
        $this->assertMatchesRegularExpression('/<h2[^>]*>توزیع وزن مراحل ماژول<\/h2>/u', $html);
        $this->assertMatchesRegularExpression('/<h2[^>]*>ثبت پیشنهاد پیشرفت مرحله<\/h2>/u', $html);
    }

    public function test_stage_show_renders_the_supplied_weight_snapshot_values(): void
    {
        $html = $this->actingAs($this->manager)
            ->get(route('modules.stages.show', $this->stage->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('وزن تخصیص‌یافته:', $html);
        $this->assertStringContainsString(number_format((float) $this->stage->weight, 2).'٪', $html);

        $this->assertStringContainsString('مبلغ تأییدشدهٔ مراحل:', $html);
        $this->assertStringContainsString('باقی‌مانده:', $html);
        $this->assertStringContainsString('وضعیت وزن:', $html);

        // Unlocked stage (no approval rows yet) keeps the editable state text.
        $this->assertStringContainsString('قابل ویرایش', $html);
        $this->assertStringNotContainsString('قفل‌شده (پس از اولین ثبت)', $html);
    }

    // ==================================================================
    // modules.stages.show — forms
    // ==================================================================

    public function test_stage_weight_rebalance_form_contract_is_unchanged(): void
    {
        $html = $this->actingAs($this->manager)
            ->get(route('modules.stages.show', $this->stage->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            '<form method="POST" action="'.route('modules.stages.rebalance', $this->module->id).'"',
            $html
        );
        $this->assertStringContainsString('name="_token"', $html);

        // Every one of the nine stages keeps its weight input contract.
        $stages = $this->module->stages()->get();
        $this->assertCount(9, $stages);

        foreach ($stages as $stage) {
            $this->assertStringContainsString(
                'name="weights['.$stage->id.']" value="'.$stage->weight.'" step="0.01" min="0.01" max="100"',
                $html,
                "Missing weight input for stage {$stage->id}"
            );
        }
    }

    public function test_locked_stage_weight_input_stays_disabled(): void
    {
        // Any approval row locks that stage's weight (OQ-05 = 3A).
        app(StageProgressApprovalService::class)
            ->propose($this->stage, $this->supervisor, 5);

        $html = $this->actingAs($this->manager)
            ->get(route('modules.stages.show', $this->stage->id))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/name="weights\['.$this->stage->id.'\]"[^>]*\sdisabled\b/u',
            $html,
            'Locked stage weight input must stay disabled'
        );

        $this->assertStringContainsString('قفل‌شده (پس از اولین ثبت)', $html);
        $this->assertStringContainsString('مراحل قفل‌شده از به‌روزرسانی مستثنی هستند.', $html);
    }

    public function test_stage_proposal_form_contract_is_unchanged(): void
    {
        $html = $this->actingAs($this->manager)
            ->get(route('modules.stages.show', $this->stage->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            '<form method="POST" action="'.route('stages.progress-approvals.store', $this->stage->id).'"',
            $html
        );

        // Field names, bounds (max = the stage weight) and required survive.
        $this->assertStringContainsString(
            'type="number" name="proposed_amount" step="0.01" min="0.01" max="'.$this->stage->weight.'"',
            $html
        );
        $this->assertStringContainsString('type="text" name="reason" maxlength="1000"', $html);
    }

    // ==================================================================
    // modules.stages.show — approval history presentation
    // ==================================================================

    public function test_pending_approval_renders_its_decision_forms_and_labels(): void
    {
        $approval = app(StageProgressApprovalService::class)
            ->propose($this->stage, $this->supervisor, 5, 'initial proposal');

        $html = $this->actingAs($this->manager)
            ->get(route('modules.stages.show', $this->stage->id))
            ->assertOk()
            ->getContent();

        // The exact Persian status vocabulary is unchanged.
        $this->assertStringContainsString('در انتظار تصمیم', $html);
        $this->assertStringContainsString('پیشنهاد: '.number_format(5, 2).'٪', $html);
        $this->assertStringContainsString('دلیل: initial proposal', $html);

        // Adjust form (same route + field names).
        $this->assertStringContainsString(
            'action="'.route('stages.progress-approvals.adjust', $approval->id).'"',
            $html
        );

        // Decide forms keep their hidden decision values.
        $this->assertStringContainsString(
            'action="'.route('stages.progress-approvals.decide', $approval->id).'"',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="decision" value="approve">/u',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="decision" value="reject">/u',
            $html
        );
        $this->assertStringContainsString('name="approved_amount"', $html);
    }

    public function test_decided_approval_renders_its_supersede_form(): void
    {
        $approval = app(StageProgressApprovalService::class)
            ->propose($this->stage, $this->supervisor, 5);

        app(StageProgressApprovalService::class)
            ->approve($approval, $this->supervisor, 5);

        $html = $this->actingAs($this->manager)
            ->get(route('modules.stages.show', $this->stage->id))
            ->assertOk()
            ->getContent();

        // Approved label + approved amount keep their wording.
        $this->assertStringContainsString('تأییدشده', $html);
        $this->assertStringContainsString(
            'مبلغ تأییدشده: '.number_format(5, 2).'٪',
            $html
        );

        // The replace-not-add supersedes form keeps its hidden id + route.
        $this->assertStringContainsString(
            'action="'.route('stages.progress-approvals.store', $this->stage->id).'"',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="supersedes_approval_id" value="'.$approval->id.'">/u',
            $html
        );
        $this->assertStringContainsString('اصلاح این تصمیم (ثبت جایگزین)', $html);
    }

    // ==================================================================
    // modules.stages.show — tasks
    // ==================================================================

    public function test_stage_task_list_keeps_its_link_and_canonical_status(): void
    {
        $task = $this->makeStagedTask($this->project, $this->manager, $this->stage->id, 'stage bound task');

        $html = $this->actingAs($this->manager)
            ->get(route('modules.stages.show', $this->stage->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('وظایف این مرحله', $html);
        $this->assertStringContainsString(
            'href="'.route('tasks.show', $task->id).'"',
            $html
        );
        $this->assertStringContainsString('stage bound task', $html);

        // Canonical status presentation (BoardColumns), not a new vocabulary.
        $this->assertStringContainsString(
            BoardColumns::statusLabel((string) $task->status),
            $html
        );
    }

    public function test_stage_show_renders_both_empty_states(): void
    {
        $html = $this->actingAs($this->manager)
            ->get(route('modules.stages.show', $this->stage->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('هنوز هیچ رکورد تأیید پیشرفتی برای این مرحله ثبت نشده است.', $html);
        $this->assertStringContainsString('وظیفه‌ای به این مرحله متصل نیست.', $html);
    }

    // ==================================================================
    // modules.show — empty state (definition form)
    // ==================================================================

    public function test_modules_show_definition_form_contract_is_unchanged(): void
    {
        $emptyProject = $this->makeProject(2);

        $html = $this->actingAs($this->manager)
            ->get(route('modules.show', $emptyProject->id))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<h2[^>]*>تعریف ساختار ماژول<\/h2>/u', $html);

        $this->assertStringContainsString(
            '<form method="POST" action="'.route('modules.store').'"',
            $html
        );
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString(
            '<input type="hidden" name="project_id" value="'.$emptyProject->id.'">',
            $html
        );

        // The three module rows keep their exact field names and bounds.
        foreach ([0, 1, 2] as $i) {
            $this->assertStringContainsString('name="modules['.$i.'][name]"', $html);
            $this->assertStringContainsString('name="modules['.$i.'][code]" maxlength="50"', $html);
            $this->assertStringContainsString(
                'name="modules['.$i.'][weight]" step="0.01" min="0.01" max="100"',
                $html
            );
        }
    }

    // ==================================================================
    // modules.show — structure page
    // ==================================================================

    public function test_modules_show_structure_page_keeps_its_forms_and_links(): void
    {
        $html = $this->actingAs($this->manager)
            ->get(route('modules.show', $this->project->id))
            ->assertOk()
            ->getContent();

        // h1 identity + back link unchanged.
        $this->assertMatchesRegularExpression(
            '/<h1[^>]*>'.preg_quote($this->project->name, '/').'<\/h1>/u',
            $html
        );
        $this->assertStringContainsString('href="'.route('modules.index').'"', $html);

        // Local heading levels preserved.
        $this->assertMatchesRegularExpression('/<h2[^>]*>ماژول‌های فعال<\/h2>/u', $html);
        $this->assertMatchesRegularExpression('/<h2[^>]*>بازتوازن وزن ماژول‌ها<\/h2>/u', $html);

        // Module weight rendered as supplied.
        $this->assertStringContainsString(
            'مجموع وزن: '.number_format((float) $this->module->weight, 2).'٪ از ۱۰۰٪',
            $html
        );

        // Stage chips keep pointing at modules.stages.show with the same weight.
        foreach ($this->module->stages()->get() as $stage) {
            $this->assertStringContainsString(
                'href="'.route('modules.stages.show', $stage->id).'"',
                $html
            );
            $this->assertStringContainsString(
                $stage->name.' ('.number_format((float) $stage->weight, 1).'٪)',
                $html
            );
        }

        // Inline update form: same action + field names.
        $this->assertStringContainsString(
            '<form method="POST" action="'.route('modules.update', $this->module->id).'"',
            $html
        );
        $this->assertStringContainsString('name="name" value="'.$this->module->name.'" required', $html);
        $this->assertStringContainsString('name="code"', $html);
        $this->assertStringContainsString('name="description"', $html);

        // Module rebalance form: same action + field names.
        $this->assertStringContainsString(
            '<form method="POST" action="'.route('modules.rebalance', $this->project->id).'"',
            $html
        );
        $this->assertStringContainsString(
            'name="weights['.$this->module->id.']" value="'.$this->module->weight.'" step="0.01" min="0.01" max="100"',
            $html
        );
    }

    public function test_modules_show_keeps_its_authorization_boundary(): void
    {
        $this->get(route('modules.show', $this->project->id))
            ->assertRedirect(route('login'));

        $contractor = $this->makeUserWithRole('contractor');

        $this->actingAs($contractor)
            ->get(route('modules.show', $this->project->id))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->get(route('modules.show', $this->project->id))
            ->assertOk();
    }

    // ==================================================================
    // Legacy cleanup + design tokens
    // ==================================================================

    public function test_migrated_module_pages_are_free_of_legacy_markup(): void
    {
        $stageHtml = $this->actingAs($this->manager)
            ->get(route('modules.stages.show', $this->stage->id))
            ->assertOk()
            ->getContent();

        $moduleHtml = $this->actingAs($this->manager)
            ->get(route('modules.show', $this->project->id))
            ->assertOk()
            ->getContent();

        $this->assertNoLegacyMarkup($stageHtml, 'modules.stages.show');
        $this->assertNoLegacyMarkup($moduleHtml, 'modules.show');

        // Design-system vocabulary is present on both pages.
        foreach ([$stageHtml, $moduleHtml] as $html) {
            $this->assertStringContainsString('rounded-ui', $html);
            $this->assertStringContainsString('text-ink', $html);
            $this->assertStringContainsString('border-line', $html);
            $this->assertStringContainsString('bg-primary', $html);
        }
    }
}
