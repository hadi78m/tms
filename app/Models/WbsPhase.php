<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WbsPhase extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'weight' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'planned_duration' => 'integer',
        'sort_order' => 'integer',
        'actual_completion' => 'datetime',
        'supervisor_approved_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'wbs_phase_id');
    }

    /**
     * The real output contract of a phase is its checklist, not the legacy
     * `expected_output` text column.
     */
    public function checklistItems()
    {
        return $this->hasMany(WbsPhaseChecklistItem::class, 'wbs_phase_id')->orderBy('sort_order');
    }

    public function supervisorApprover()
    {
        return $this->belongsTo(User::class, 'supervisor_approved_by');
    }
}
