<?php

namespace App\Domain\Services;

use App\Domain\Contracts\AuditServiceInterface;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class NullAuditService implements AuditServiceInterface
{
    public function log(string $action, Model $entity, User $actor, array $oldValues = [], array $newValues = [], array $metadata = []): ActivityLog
    {
        // Do nothing for testing
        return new ActivityLog;
    }
}
