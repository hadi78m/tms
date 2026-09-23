<?php

namespace App\Models;

use App\Domain\Enums\StageProgressApprovalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A single, immutable stage progress approval event.
 *
 * Stored status domain: pending | approved | rejected.
 * "superseded" is derived — a row is superseded when another row points at it
 * via `supersedes_approval_id`. Revoking an approval therefore never requires
 * an UPDATE, which is what allows the immutability rule to be absolute
 * (DEC-027). Mirrored in the database by `trg_spa_immutable` and
 * `trg_spa_no_delete`.
 */
class StageProgressApproval extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'proposed_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'decided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $model): bool {
            return $model->getOriginal('status') === StageProgressApprovalStatus::Pending->value;
        });

        static::deleting(function (): bool {
            return false;
        });
    }

    public function moduleStage(): BelongsTo
    {
        return $this->belongsTo(ModuleStage::class, 'module_stage_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function finalApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_approved_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_approval_id');
    }

    public function supersededBy()
    {
        return $this->hasMany(self::class, 'supersedes_approval_id');
    }

    public function isPending(): bool
    {
        return $this->status === StageProgressApprovalStatus::Pending->value;
    }

    public function isSuperseded(): bool
    {
        return $this->supersededBy()->exists();
    }

    /**
     * Active approvals only: approved, and not superseded by a newer row.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('stage_progress_approvals.status', StageProgressApprovalStatus::Approved->value)
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('stage_progress_approvals as superseding')
                    ->whereColumn('superseding.supersedes_approval_id', 'stage_progress_approvals.id');
            });
    }
}
