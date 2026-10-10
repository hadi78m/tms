<?php

namespace App\Support\TaskBoard;

/**
 * Phase 3 — Board Presentation Mapping (CANONICAL, SINGLE SOURCE).
 *
 * This class is PURE PRESENTATION. It maps the 9 real Task statuses of the
 * domain (App\Domain\Enums\TaskStatus) onto the 5 owner-approved Board
 * columns, and nothing else:
 *
 *   draft + assigned                          → آماده شروع (Backlog)
 *   in_progress + needs_rework                → در حال انجام (In Progress)
 *   submitted_for_review + under_review
 *     + supervisor_approved                   → در انتظار بررسی (Review)
 *   approved                                  → تأیید شده (Approved)
 *   cancelled                                 → لغو شده (Cancelled)
 *
 * Hard guarantees (owner-approved gate §7/§27):
 *   - It never mutates a task status, never models a transition, and never
 *     encodes a business rule. Status → column is a display grouping only.
 *   - Colors carry NO business meaning — they are presentation. needs_rework
 *     is conveyed by text + icon, never by color alone (gate §25).
 *   - The Board layer is read-only by construction: this class has no write
 *     surface whatsoever.
 *
 * Any Blade that needs a status's column/label/variant MUST resolve it
 * through here — the mapping is never duplicated across views.
 */
final class BoardColumns
{
    /**
     * Canonical column definitions, in lifecycle order.
     *
     * `statuses` lists the REAL domain statuses grouped into the column.
     * `variant` is a Phase-1 design-system semantic visual state
     * (ui.badge / ui.status-indicator vocabulary).
     */
    public const COLUMNS = [
        'backlog' => [
            'label' => 'آماده شروع',
            'statuses' => ['draft', 'assigned'],
            'variant' => 'default',
        ],
        'in_progress' => [
            'label' => 'در حال انجام',
            'statuses' => ['in_progress', 'needs_rework'],
            'variant' => 'info',
        ],
        'review' => [
            'label' => 'در انتظار بررسی',
            'statuses' => ['submitted_for_review', 'under_review', 'supervisor_approved'],
            'variant' => 'warning',
        ],
        'approved' => [
            'label' => 'تأیید شده',
            'statuses' => ['approved'],
            'variant' => 'success',
        ],
        'cancelled' => [
            'label' => 'لغو شده',
            'statuses' => ['cancelled'],
            'variant' => 'muted',
        ],
    ];

    /**
     * Presentation data for each of the 9 real statuses:
     * Persian label + semantic visual variant (color is decorative only).
     */
    public const STATUS_VIEW = [
        'draft' => [
            'label' => 'پیش‌نویس',
            'variant' => 'default',
        ],
        'assigned' => [
            'label' => 'ارجاع شده',
            'variant' => 'primary',
        ],
        'in_progress' => [
            'label' => 'در حال انجام',
            'variant' => 'info',
        ],
        'submitted_for_review' => [
            'label' => 'ارائه شده برای بررسی',
            'variant' => 'warning',
            // Review Tier (gate §8): whose action is pending.
            'tier' => 'در انتظار بررسی سرپرست',
        ],
        'under_review' => [
            'label' => 'در حال بررسی',
            'variant' => 'warning',
            'tier' => 'در حال بررسی سرپرست',
        ],
        'supervisor_approved' => [
            'label' => 'تأیید ناظر',
            'variant' => 'warning',
            'tier' => 'در انتظار تأیید کارفرما',
        ],
        'approved' => [
            'label' => 'تأیید نهایی',
            'variant' => 'success',
        ],
        'needs_rework' => [
            'label' => 'نیاز به اصلاح',
            'variant' => 'danger',
        ],
        'cancelled' => [
            'label' => 'لغو شده',
            'variant' => 'muted',
        ],
    ];

    /**
     * Column key for a real status value (falls back to cancelled-safe null).
     */
    public static function columnOf(string $status): ?string
    {
        foreach (self::COLUMNS as $key => $column) {
            if (in_array($status, $column['statuses'], true)) {
                return $key;
            }
        }

        return null;
    }

    public static function columnLabel(string $key): ?string
    {
        return self::COLUMNS[$key]['label'] ?? null;
    }

    public static function columnVariant(string $key): string
    {
        return self::COLUMNS[$key]['variant'] ?? 'default';
    }

    /**
     * Real statuses belonging to a column.
     *
     * @return list<string>
     */
    public static function statusesOf(string $key): array
    {
        return self::COLUMNS[$key]['statuses'] ?? [];
    }

    /**
     * Presentation record for a real status.
     *
     * @return array{label: string, variant: string, tier?: string}
     */
    public static function statusView(string $status): array
    {
        return self::STATUS_VIEW[$status] ?? ['label' => $status, 'variant' => 'default'];
    }

    public static function statusLabel(string $status): string
    {
        return self::statusView($status)['label'];
    }

    public static function statusVariant(string $status): string
    {
        return self::statusView($status)['variant'];
    }

    /**
     * Review Tier label — who the task is waiting on (gate §8), or null when
     * the status is not one of the three review statuses.
     */
    public static function reviewTier(string $status): ?string
    {
        return self::statusView($status)['tier'] ?? null;
    }

    /**
     * All real statuses accepted by the Status filter (the true DB domain,
     * not the presentation grouping — gate §14/§16).
     *
     * @return list<string>
     */
    public static function filterableStatuses(): array
    {
        return array_keys(self::STATUS_VIEW);
    }
}
