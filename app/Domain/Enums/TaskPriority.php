<?php

namespace App\Domain\Enums;

/**
 * Task priority.
 *
 * `label()` / `variant()` follow the existing enum presentation pattern used by
 * {@see ModuleStageCode::label()} — the enum stays the single canonical source
 * for its own presentation, so no view invents or duplicates a translation.
 * `variant()` is a ui.badge / ui.status-indicator semantic visual state; colour
 * never carries meaning on its own.
 */
enum TaskPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'پایین',
            self::Normal => 'متوسط',
            self::High => 'بالا',
            self::Urgent => 'فوری',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Low => 'muted',
            self::Normal => 'default',
            self::High => 'warning',
            self::Urgent => 'danger',
        };
    }

    /**
     * Presentation record for a raw priority value.
     *
     * Unknown values fall back to the raw value with a neutral variant so a
     * legacy row can never break a page — but the label is never invented.
     *
     * @return array{label: string, variant: string, value: string}
     */
    public static function viewOf(?string $value): array
    {
        $priority = self::tryFrom((string) $value);

        if ($priority === null) {
            return ['label' => (string) $value, 'variant' => 'default', 'value' => (string) $value];
        }

        return ['label' => $priority->label(), 'variant' => $priority->variant(), 'value' => $priority->value];
    }
}
