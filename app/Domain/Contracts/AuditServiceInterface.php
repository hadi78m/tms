<?php

namespace App\Domain\Contracts;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

interface AuditServiceInterface
{
    public function log(
        string $action,
        Model $entity,
        User $actor,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = []
    ): ActivityLog;
}
