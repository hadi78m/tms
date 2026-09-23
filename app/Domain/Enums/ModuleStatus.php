<?php

namespace App\Domain\Enums;

/**
 * Lifecycle status of a Module.
 *
 * Stored as VARCHAR(50) — the repository contract is "enum-ish column =
 * VARCHAR(50) + a PHP enum in app/Domain/Enums" (see TaskStatus). No
 * `CHECK IN (...)` is added, so a future status does not need a migration.
 */
enum ModuleStatus: string
{
    case Active = 'active';

    case Archived = 'archived';
}
