<?php

namespace App\Domain\Enums;

/**
 * V1.11 — V11-01 (DEC-048 / OD-1): type of a Project Membership.
 *
 * Stored as VARCHAR(50) in project_memberships.membership_type — the
 * repository contract is "enum-ish column = VARCHAR(50) + a PHP enum in
 * app/Domain/Enums" (see ModuleStatus / TaskStatus). No `CHECK IN (...)`
 * is added, so a future membership type does not need a migration.
 *
 * V1.11 value set: supervisor only (OD-2 — Employer membership is
 * DEFERRED; no other type is approved). The value is a scope label only,
 * never a permission source (DEC-050 — Permission ≠ Project Membership).
 */
enum ProjectMembershipType: string
{
    case Supervisor = 'supervisor';
}
