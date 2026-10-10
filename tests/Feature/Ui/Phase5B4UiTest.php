<?php

namespace Tests\Feature\Ui;

use App\Domain\Services\StageProgressApprovalService;
use App\Models\Module;
use App\Models\ModuleStage;
use App\Models\Project;
use App\Models\StageProgressApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V19\Concerns\V19Fixtures;
use Tests\TestCase;

use function app;
use function route;

/**
 * Phase 5B-4 — Visual Migration of reports/index.
 *
 * Pins the CONTRACTS of the migration, not its pixels:
 *   - the page renders through reports.index for authorized roles and stays
 *     forbidden/redirected for everyone else;
 *   - the GET filter form keeps its action, field names (project_id/from/to),
 *     selected-state logic and reset link;
 *   - section headings keep their levels (h2 x3 in content, h3 x3 in export
 *     cards) and their exact Persian labels;
 *   - KPI labels, stage table headers, stage links, empty state, the
 *     stage-less exclusion banner and the three CSV export links (including
 *     the stage export's filter passthrough) are unchanged;
 *   - the legacy slate/old-colour presentation vocabulary is gone.
 *
 * Like Phase5B2/3UiTest, these tests avoid decorative class-by-class
 * assertions for styling that carries no contract.
 */
class Phase5B4UiTest extends TestCase
{
    use RefreshDatabase, V19Fixtures;

    /** Legacy signatures that must be gone from the migrated page. */
    private const LEGACY_MARKUP = [
        'border-slate-200',
        'border-slate-100',
        'border-slate-300',
        'text-slate-800',
        'text-slate-600',
        'text-slate-500',
        'text-slate-400',
        'bg-slate-50',
        'bg-slate-100',
        'bg-blue-600',
        'hover:bg-blue-700',
        'hover:border-blue-300',
        'bg-green-100',
        'text-green-700',
        'bg-red-100',
        'text-red-600',
        'bg-amber-50',
        'border-amber-100',
        'text-amber-800',
        'rounded-xl shadow-sm border',
    ];

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

    /**
     * Creates a real approved stage progress row (same path V110 uses).
     *
     * @return array{0: Module, 1: ModuleStage, 2: StageProgressApproval}
     */
    private function approveAtStage(float $amount): array
    {
        $module = $this->makeSingleModule($this->project, $this->supervisor);
        $stage = $module->stages()->firstWhere('stage_code', 'analysis');

        $service = app(StageProgressApprovalService::class);
        $approval = $service->propose($stage, $this->supervisor, $amount);
        $approved = $service->approve($approval, $this->supervisor, $amount);

        return [$module, $stage, $approved];
    }

    // ==================================================================
    // Rendering + authorization
    // ==================================================================

    public function test_reports_page_renders_through_existing_route(): void
    {
        $response = $this->actingAs($this->manager)->get(route('reports.index'))->assertOk();

        $response->assertSee('گزارش‌های سیستم');
        $response->assertSee('فیلتر گزارش');
    }

    public function test_authorization_boundaries_are_unchanged(): void
    {
        // Route middleware: role:admin|management|employer|project_manager.
        $noRole = User::factory()->create();
        $this->actingAs($noRole)->get(route('reports.index'))->assertForbidden();

    }

    public function test_guests_are_redirected_to_login(): void
    {
        // Guests hit the auth middleware first (fresh test: no actingAs user).
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    // ==================================================================
    // Filter form contract (DEC-046 5-1/5-2)
    // ==================================================================

    public function test_filter_form_contract_is_unchanged(): void
    {
        $html = $this->actingAs($this->manager)->get(route('reports.index'))->assertOk()->getContent();

        // Same method, same action, same field names.
        $this->assertStringContainsString('<form method="GET" action="'.route('reports.index').'"', $html);
        foreach (['project_id', 'from', 'to'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html);
        }

        // Labels and buttons survive verbatim.
        foreach (['پروژه', 'از تاریخ (میلادی)', 'تا تاریخ (میلادی)', 'اعمال فیلتر', 'حذف فیلترها', 'همهٔ پروژه‌ها'] as $label) {
            $this->assertStringContainsString($label, $html);
        }

        // The reset link still points at the bare index route.
        $this->assertStringContainsString('href="'.route('reports.index').'"', $html);

        // Date inputs keep their Latin (miliadi) direction.
        $this->assertMatchesRegularExpression('/name="from"[^>]*dir="ltr"/s', $html);
        $this->assertMatchesRegularExpression('/name="to"[^>]*dir="ltr"/s', $html);
    }

    public function test_filter_selected_state_is_preserved(): void
    {
        // Without filters: empty date values, nothing selected in the select.
        $plain = $this->actingAs($this->manager)->get(route('reports.index'))->assertOk()->getContent();
        $this->assertStringContainsString('name="from" value=""', $plain);
        $this->assertStringContainsString('name="to" value=""', $plain);
        $this->assertStringContainsString('<option value="">', $plain);
        $this->assertStringNotContainsString('<option value="'.$this->project->id.'" selected>', $plain);

        // With filters: the selected option and the date values round-trip.
        $html = $this->actingAs($this->manager)->get(route('reports.index', [
            'project_id' => $this->project->id,
            'from' => '2024-01-05',
            'to' => '2024-02-06',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('<option value="'.$this->project->id.'" selected>', $html);
        $this->assertStringContainsString('name="from" value="2024-01-05"', $html);
        $this->assertStringContainsString('name="to" value="2024-02-06"', $html);
    }

    // ==================================================================
    // Headings + KPI labels
    // ==================================================================

    public function test_section_headings_keep_their_levels_and_labels(): void
    {
        $html = $this->actingAs($this->manager)->get(route('reports.index'))->assertOk()->getContent();

        foreach (['فیلتر گزارش', 'پیشرفت مراحل (مبتنی بر مبلغ تأییدشده)', 'برون‌ریزی داده‌ها (CSV Export)'] as $heading) {
            $this->assertMatchesRegularExpression(
                '/<h2[^>]*>'.preg_quote($heading, '/').'<\/h2>/u',
                $html,
                "Missing h2 section heading: {$heading}"
            );
        }

        foreach (['گزارش وظایف تأیید نهایی‌شده', 'گزارش وضعیت و تاخیرات SLA', 'گزارش مرحله‌محور (CSV)'] as $heading) {
            $this->assertMatchesRegularExpression(
                '/<h3[^>]*>'.preg_quote($heading, '/').'<\/h3>/u',
                $html,
                "Missing h3 export heading: {$heading}"
            );
        }
    }

    public function test_kpi_labels_remain_untouched(): void
    {
        $html = $this->actingAs($this->manager)->get(route('reports.index'))->assertOk()->getContent();

        foreach (['وزن کل تسک‌ها', 'مبلغ تأییدشدهٔ مراحل', 'از مجموع', 'وظایف دارای تاخیر بحرانی'] as $label) {
            $this->assertStringContainsString($label, $html);
        }

        // The period-filter semantics note survives verbatim.
        $this->assertStringContainsString('فیلتر دوره بر تاریخ تصمیم تأیید (decided_at) اعمال می‌شود', $html);
    }

    // ==================================================================
    // Stage progress table
    // ==================================================================

    public function test_stage_table_headers_and_stage_links_remain(): void
    {
        [$module, $stage] = $this->approveAtStage(10);

        $html = $this->actingAs($this->manager)->get(route('reports.index'))->assertOk()->getContent();

        foreach (['پروژه', 'ماژول', 'مرحله', 'وزن تخصیص‌یافته', 'مبلغ تأییدشدهٔ مراحل'] as $column) {
            $this->assertStringContainsString('>'.$column.'</th>', $html, "Missing table header: {$column}");
        }

        // Row content comes from the same service data as before.
        $this->assertStringContainsString($module->name, $html);
        $this->assertStringContainsString('href="'.route('modules.stages.show', $stage->id).'"', $html);
        $this->assertStringContainsString('10.00', $html);
    }

    public function test_stage_table_empty_state_renders(): void
    {
        $html = $this->actingAs($this->manager)->get(route('reports.index'))->assertOk()->getContent();

        $this->assertStringContainsString('هنوز ساختار ماژول/مرحله‌ای برای گزارش وجود ندارد.', $html);
    }

    public function test_stageless_exclusion_banner_survives(): void
    {
        $this->makeStagelessTask($this->project, $this->supervisor);

        $html = $this->actingAs($this->manager)->get(route('reports.index'))->assertOk()->getContent();

        // DEC-040 wording pinned by V110 — must survive the migration.
        $this->assertStringContainsString('1 وظیفه بدون مرحله', $html);
        $this->assertStringContainsString('module_stage_id خالی', $html);
    }

    // ==================================================================
    // CSV export links
    // ==================================================================

    public function test_export_links_keep_types_and_filter_passthrough(): void
    {
        $html = $this->actingAs($this->manager)->get(route('reports.index'))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.e(route('reports.export', ['type' => 'approved_tasks'])).'"', $html);
        $this->assertStringContainsString('href="'.e(route('reports.export', ['type' => 'all_tasks'])).'"', $html);
        $this->assertStringContainsString('href="'.e(route('reports.export', ['type' => 'stage_progress'])).'"', $html);

        foreach (['دانلود CSV', 'دانلود CSV مراحل'] as $cta) {
            $this->assertStringContainsString($cta, $html);
        }

        // The stage export carries the active filters (array_filter contract).
        $filtered = $this->actingAs($this->manager)->get(route('reports.index', [
            'project_id' => $this->project->id,
            'from' => '2024-01-05',
            'to' => '2024-02-06',
        ]))->assertOk()->getContent();

        $expected = e(route('reports.export', [
            'type' => 'stage_progress',
            'project_id' => $this->project->id,
            'from' => '2024-01-05',
            'to' => '2024-02-06',
        ]));

        $this->assertStringContainsString('href="'.$expected.'"', $filtered);
    }

    // ==================================================================
    // Migration itself
    // ==================================================================

    public function test_reports_page_has_no_legacy_markup(): void
    {
        // Render every conditional block (table rows + exclusion banner).
        $this->approveAtStage(10);
        $this->makeStagelessTask($this->project, $this->supervisor);

        $html = $this->actingAs($this->manager)->get(route('reports.index'))->assertOk()->getContent();

        foreach (self::LEGACY_MARKUP as $legacy) {
            $this->assertStringNotContainsString(
                $legacy,
                $html,
                "reports/index still contains legacy presentation markup: {$legacy}"
            );
        }

        // The page now renders through the design-system chrome.
        $this->assertStringContainsString('border-line', $html);
        $this->assertStringContainsString('shadow-ui-soft', $html);
    }
}
