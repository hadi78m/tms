<?php

namespace App\Domain\Services;

use App\Domain\Contracts\AuditServiceInterface;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

/**
 * The real Audit writer (DEC-011 — step zero of the V1.8 implementation).
 *
 * Contract:
 *   - synchronous  : no queue, no job — the request waits for the row.
 *   - transactional: ActivityLog::create() runs inside the caller's business
 *                    transaction, so an audit failure rolls the business
 *                    operation back.
 *   - immutable    : guaranteed by the ActivityLog model and by the
 *                    `trg_activity_logs_no_delete` database trigger.
 *   - failure is NEVER swallowed: there is no try/catch around the write
 *     (§8.5). If the row cannot be written, the caller's transaction fails.
 *
 * Metadata mapping (OQ-25 = RESOLVED · DEC-030), which needs no schema change:
 *   metadata['reason']  →  activity_logs.reason
 *   any other metadata  →  activity_logs.new_values['metadata']
 *
 * `actor_role` is snapshotted into new_values so that "who acted as Employer"
 * survives a later role change, without adding a fixed user-type column (§8.4).
 */
class DatabaseAuditService implements AuditServiceInterface
{
    public function log(
        string $action,
        Model $entity,
        User $actor,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = []
    ): ActivityLog {
        $reason = $metadata['reason'] ?? null;
        unset($metadata['reason']);

        if ($metadata !== []) {
            $newValues['metadata'] = $metadata;
        }

        $actorRole = $actor->getRoleNames()->first();

        if ($actorRole !== null && ! array_key_exists('actor_role', $newValues)) {
            $newValues['actor_role'] = $actorRole;
        }

        return ActivityLog::create([
            'user_id' => $actor->getKey(),
            'action' => $action,
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => $entity->getKey(),
            'old_values' => $oldValues === [] ? null : $oldValues,
            'new_values' => $newValues === [] ? null : $newValues,
            'ip_address' => $this->ipAddress(),
            'user_agent' => $this->userAgent(),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function ipAddress(): ?string
    {
        return App::runningInConsole() ? null : request()->ip();
    }

    private function userAgent(): ?string
    {
        return App::runningInConsole() ? null : request()->userAgent();
    }
}
