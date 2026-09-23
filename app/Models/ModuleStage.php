<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of the nine standard, weighted stages of a Module.
 *
 * The Stage — not the Task — owns weight. `weight` is the single source of
 * truth for the allocated amount and becomes immutable once the first
 * StageProgressApproval row exists for it (OQ-05 = 3A), enforced both here and
 * by the `trg_module_stages_weight_locked` trigger.
 *
 * There is no Soft Delete: a Stage is part of a fixed catalogue, and soft
 * deleting one would leave its approval history suspended.
 */
class ModuleStage extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'weight' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(StageProgressApproval::class, 'module_stage_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'module_stage_id');
    }

    /**
     * Any approval row at all — pending included — locks the base weight.
     */
    public function isWeightLocked(): bool
    {
        return $this->approvals()->exists();
    }

    /**
     * SUM of ACTIVE approved amounts.
     *
     * An approved row is inactive when a newer row supersedes it through
     * `supersedes_approval_id`. This is the only definition of "active", so an
     * approved amount is never counted twice (DEC-027).
     */
    public function approvedWeight(): float
    {
        return round((float) $this->approvedQuery()->sum('approved_amount'), 2);
    }

    public function remainingWeight(): float
    {
        return round((float) $this->weight - $this->approvedWeight(), 2);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<StageProgressApproval>
     */
    public function approvedQuery()
    {
        // Delegates to the single Active definition (StageProgressApproval::scopeActive)
        // — R-3 consolidation. Semantics are unchanged: this was a verbatim copy of
        // that scope (approved + NOT EXISTS superseding), verified equivalent.
        return $this->approvals()->active();
    }
}
