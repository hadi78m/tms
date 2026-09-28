<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * V1.11 — V11-01 (DEC-048 / OD-1): one historical User ↔ Project membership.
 *
 * Active = ended_at IS NULL (OD-6-a — no is_active flag, no status column).
 * Historical rows are never deleted: FKs are RESTRICT and this model blocks
 * deletion (same immutable-history pattern as Approval, C-10).
 */
class ProjectMembership extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'project_id' => 'integer',
        'user_id' => 'integer',
        'assigned_by' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Immutable history (OD-6-d / Concrete Design §8): ending an
        // assignment is an UPDATE (ended_at); deletion is not an approved
        // operation anywhere in V1.11.
        static::deleting(function () {
            return false;
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Active memberships (OD-6-a): ended_at IS NULL.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('membership_type', $type);
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }
}
