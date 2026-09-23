<?php

namespace App\Domain\Enums;

/**
 * The nine standard stages every Module is born with, and the only place the
 * baseline percentages live.
 *
 * Each Module gets all nine stages in a single transaction, so
 * SUM(module_stages.weight) = 100 is true from the moment the Module exists.
 *
 * NOTE: the code is `penetration_test`, not `pentest`.
 */
enum ModuleStageCode: string
{
    case Analysis = 'analysis';
    case Design = 'design';
    case Coding = 'coding';
    case FunctionalTest = 'functional_test';
    case PenetrationTest = 'penetration_test';
    case Training = 'training';
    case Pilot = 'pilot';
    case Production = 'production';
    case Support = 'support';

    public function defaultWeight(): float
    {
        return match ($this) {
            self::Analysis => 15.00,
            self::Design => 5.00,
            self::Coding => 35.00,
            self::FunctionalTest => 3.00,
            self::PenetrationTest => 5.00,
            self::Training => 7.00,
            self::Pilot => 10.00,
            self::Production => 5.00,
            self::Support => 15.00,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Analysis => 'Analysis',
            self::Design => 'Design',
            self::Coding => 'Coding',
            self::FunctionalTest => 'Functional Test',
            self::PenetrationTest => 'Penetration Test',
            self::Training => 'Training',
            self::Pilot => 'Pilot',
            self::Production => 'Production',
            self::Support => 'Support',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Analysis => 1,
            self::Design => 2,
            self::Coding => 3,
            self::FunctionalTest => 4,
            self::PenetrationTest => 5,
            self::Training => 6,
            self::Pilot => 7,
            self::Production => 8,
            self::Support => 9,
        };
    }

    /**
     * The full catalogue as `stage_code => [name, weight, sort_order]`.
     *
     * @return array<string, array{name: string, weight: float, sort_order: int}>
     */
    public static function catalogue(): array
    {
        $catalogue = [];

        foreach (self::cases() as $code) {
            $catalogue[$code->value] = [
                'name' => $code->label(),
                'weight' => $code->defaultWeight(),
                'sort_order' => $code->sortOrder(),
            ];
        }

        return $catalogue;
    }

    /**
     * Sum of all baseline weights — must stay 100.00.
     */
    public static function totalDefaultWeight(): float
    {
        $total = 0.0;

        foreach (self::cases() as $code) {
            $total += $code->defaultWeight();
        }

        return round($total, 2);
    }
}
