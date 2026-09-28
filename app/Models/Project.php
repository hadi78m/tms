<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Domain\Enums\ProjectMembershipType;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function contract()
    {
        return $this->belongsTo(SyncedContract::class, 'contract_id');
    }

    public function contractor()
    {
        return $this->belongsTo(SyncedContractor::class, 'contractor_id');
    }

    public function modules()
    {
        return $this->hasMany(Module::class, 'project_id');
    }

    public function activeModules()
    {
        return $this->hasMany(Module::class, 'project_id')->where('status', 'active');
    }

    public function wbsPhases()
    {
        return $this->hasMany(WbsPhase::class, 'project_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    /**
     * V1.11 — V11-01 (DEC-048 / OD-1): full membership history.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ProjectMembership::class, 'project_id');
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->whereNull('ended_at');
    }

    /**
     * The single active Supervisor (Task::activeAssignment pattern —
     * guaranteed ≤ 1 row by idx_active_project_supervisor).
     */
    public function activeSupervisor(): HasOne
    {
        return $this->hasOne(ProjectMembership::class, 'project_id')
            ->where('membership_type', ProjectMembershipType::Supervisor->value)
            ->whereNull('ended_at');
    }
}
