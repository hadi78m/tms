<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class UiFoundationTest extends TestCase
{
    /**
     * تست دودیِ رندر شدن کامپوننت‌های پایهٔ رابط کاربری.
     * این کامپوننت‌ها نباید هیچ وابستگی‌ای به دیتابیس، Policy یا سرویس دامنه داشته باشند.
     */
    public function test_badge_renders_all_variants(): void
    {
        foreach (['default', 'muted', 'success', 'warning', 'danger', 'info', 'primary'] as $variant) {
            $html = Blade::render('<x-ui.badge variant="'.$variant.'" dot>بررسی</x-ui.badge>');
            $this->assertStringContainsString('بررسی', $html);
            $this->assertStringContainsString('rounded-full', $html);
        }
    }

    public function test_panel_custom_header_replaces_default_header(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.panel title="عنوان پیش‌فرض">
                <x-slot:header>هدر سفارشی</x-slot:header>
                بدنه پنل
            </x-ui.panel>
        BLADE);

        // slot سفارشی باید هدر پیش‌فرض (شامل عنوان) را جایگزین کند
        $this->assertStringContainsString('هدر سفارشی', $html);
        $this->assertStringNotContainsString('عنوان پیش‌فرض', $html);
        $this->assertStringContainsString('بدنه پنل', $html);
    }

    public function test_panel_renders_default_header_and_footer_slot(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.panel title="عنوان پنل" subtitle="زیرعنوان">
                بدنه پنل
                <x-slot:footer>فوتر پنل</x-slot:footer>
            </x-ui.panel>
        BLADE);

        $this->assertStringContainsString('عنوان پنل', $html);
        $this->assertStringContainsString('زیرعنوان', $html);
        $this->assertStringContainsString('بدنه پنل', $html);
        $this->assertStringContainsString('فوتر پنل', $html);
    }

    public function test_panel_renders_without_header(): void
    {
        $html = Blade::render('<x-ui.panel variant="flat">فقط بدنه</x-ui.panel>');
        $this->assertStringContainsString('فقط بدنه', $html);
        $this->assertStringNotContainsString('<header', $html);
    }

    public function test_panel_supports_accent_and_icon(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.panel title="پنل" accent="#22c55e">
                <x-slot:icon>
                    <svg class="h-5 w-5"></svg>
                </x-slot:icon>
                بدنه
            </x-ui.panel>
        BLADE);

        $this->assertStringContainsString('background: #22c55e', $html);
        $this->assertStringContainsString('<svg class="h-5 w-5"></svg>', $html);
    }

    public function test_avatar_falls_back_to_initials_when_no_image(): void
    {
        $html = Blade::render('<x-ui.avatar name="سارا محمدی" size="md" />');
        $this->assertStringContainsString('سم', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_avatar_renders_image_when_src_present(): void
    {
        $html = Blade::render('<x-ui.avatar src="/img/a.png" name="سارا" alt="آلت" />');
        $this->assertStringContainsString('src="/img/a.png"', $html);
        $this->assertStringContainsString('alt="آلت"', $html);
    }

    public function test_avatar_handles_empty_name_safely(): void
    {
        $html = Blade::render('<x-ui.avatar />');
        $this->assertNotEmpty(trim(strip_tags($html)));
    }

    public function test_progress_renders_clamped_percentage(): void
    {
        $html = Blade::render('<x-ui.progress :value="150" :max="100" label />');
        $this->assertStringContainsString('width: 100', $html);
        $this->assertStringContainsString('aria-valuenow="100"', $html);

        $html = Blade::render('<x-ui.progress :value="-10" :max="100" />');
        $this->assertStringContainsString('width: 0', $html);
    }

    public function test_progress_supports_variants_and_sizes(): void
    {
        foreach (['primary', 'secondary', 'success', 'warning', 'danger', 'info'] as $variant) {
            $html = Blade::render('<x-ui.progress :value="50" variant="'.$variant.'" size="lg" />');
            $this->assertStringContainsString('h-3', $html);
        }
    }

    public function test_ring_renders_dashoffset(): void
    {
        $html = Blade::render('<x-ui.ring :value="25" :size="64" :stroke="8"><span>۲۵٪</span></x-ui.ring>');
        $this->assertStringContainsString('stroke-dasharray', $html);
        $this->assertStringContainsString('stroke-dashoffset', $html);
        $this->assertStringContainsString('۲۵٪', $html);
    }

    public function test_chip_renders_active_and_inactive_states(): void
    {
        $active = Blade::render('<x-ui.chip active label="فعال" />');
        $this->assertStringContainsString('فعال', $active);
        $this->assertStringContainsString('bg-primary', $active);

        $inactive = Blade::render('<x-ui.chip label="غیرفعال" />');
        $this->assertStringContainsString('border-line', $inactive);

        $colored = Blade::render('<x-ui.chip active label="رنگی" color="#ef6211" />');
        $this->assertStringContainsString('border-color: #ef6211', $colored);
    }

    public function test_chip_renders_as_button(): void
    {
        $html = Blade::render('<x-ui.chip as="button" label="فیلتر" />');
        $this->assertStringContainsString('<button type="button"', $html);
    }

    public function test_segmented_renders_options_and_holds_value(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.segmented name="view" value="board" :options="['board' => 'بورد', 'list' => 'لیست']" />
        BLADE);

        $this->assertStringContainsString('name="view"', $html);
        $this->assertStringContainsString('بورد', $html);
        $this->assertStringContainsString('لیست', $html);
        $this->assertStringContainsString('current', $html);
    }

    public function test_segmented_accepts_array_of_options(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.segmented name="v" value="a" :options="[['value' => 'a', 'label' => 'الف'], ['value' => 'b', 'label' => 'ب']]" />
        BLADE);

        $this->assertStringContainsString('الف', $html);
        $this->assertStringContainsString('ب', $html);
    }

    public function test_empty_state_renders_title_description_and_action(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.empty-state title="موردی یافت نشد" description="هنوز تسکی ثبت نشده است">
                <x-slot:action>
                    <button type="button">افزودن</button>
                </x-slot:action>
            </x-ui.empty-state>
        BLADE);

        $this->assertStringContainsString('موردی یافت نشد', $html);
        $this->assertStringContainsString('هنوز تسکی ثبت نشده است', $html);
        $this->assertStringContainsString('افزودن', $html);
    }

    public function test_empty_state_renders_default_icon_without_content(): void
    {
        $html = Blade::render('<x-ui.empty-state />');
        $this->assertStringContainsString('<svg', $html);
    }

    public function test_key_val_renders_label_and_value(): void
    {
        $html = Blade::render('<x-ui.key-val label="مسئول">سارا محمدی</x-ui.key-val>');
        $this->assertStringContainsString('مسئول', $html);
        $this->assertStringContainsString('سارا محمدی', $html);
    }

    public function test_status_indicator_renders_dot_and_label_without_mapping(): void
    {
        foreach (['default', 'muted', 'success', 'warning', 'danger', 'info', 'primary'] as $variant) {
            $html = Blade::render('<x-ui.status-indicator label="وضعیت" variant="'.$variant.'" />');
            $this->assertStringContainsString('وضعیت', $html);
            $this->assertStringContainsString('rounded-full', $html);
        }
    }

    public function test_status_indicator_can_render_without_dot(): void
    {
        $html = Blade::render('<x-ui.status-indicator label="بدون نقطه" :dot="false" />');
        $this->assertStringContainsString('بدون نقطه', $html);
    }

    public function test_drawer_shell_renders_alpine_state_and_backdrop(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-elements.drawer name="task-detail" side="left">
                محتوای کشویی
            </x-elements.drawer>
        BLADE);

        $this->assertStringContainsString('محتوای کشویی', $html);
        $this->assertStringContainsString('x-data', $html);
        $this->assertStringContainsString('open-drawer', $html);
        $this->assertStringContainsString('close-drawer', $html);
        $this->assertStringContainsString('left-0', $html);
        $this->assertStringContainsString('keydown.escape.window', $html);
    }

    public function test_drawer_supports_right_side_and_custom_width(): void
    {
        $html = Blade::render('<x-elements.drawer name="d" side="right" width="sm:max-w-lg">x</x-elements.drawer>');
        $this->assertStringContainsString('right-0', $html);
        $this->assertStringContainsString('sm:max-w-lg', $html);
    }
}
