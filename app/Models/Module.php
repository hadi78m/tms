<?php

namespace App\Models;

use App\Domain\Enums\ModuleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Module owns a share of the development scope of a Project
 * (OQ-01a = 1A). It is the parent of the nine weighted Module Stages.
 *
 * `weight` is the module's share of the project; the invariant
 * SUM(active modules.weight) = 100 lives in ModuleService, never in a CHECK.
 */
class Module extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'weight' => 'decimal:2',
        'sort_order' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ModuleStage::class, 'module_id')->orderBy('sort_order');
    }

    public function progressApprovals(): HasMany
    {
        return $this->hasMany(StageProgressApproval::class, 'module_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'module_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ModuleStatus::Active->value);
    }

    /**
     * Sum of allocated weight across this module's stages (always 100.00).
     */
    public function allocatedStageWeight(): float
    {
        return round((float) $this->stages()->sum('weight'), 2);
    }
}
