<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'weight' => 'decimal:2',
        'planned_start_at' => 'datetime',
        'planned_due_at' => 'datetime',
        'actual_started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function contract()
    {
        return $this->belongsTo(SyncedContract::class, 'contract_id');
    }

    public function contractor()
    {
        return $this->belongsTo(SyncedContractor::class, 'contractor_id');
    }

    public function wbsPhase()
    {
        return $this->belongsTo(WbsPhase::class, 'wbs_phase_id');
    }

    public function parentTask()
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function childTasks()
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments()
    {
        return $this->hasMany(TaskAssignment::class, 'task_id');
    }

    public function activeAssignment()
    {
        return $this->hasOne(TaskAssignment::class, 'task_id')->whereNull('ended_at');
    }

    public function weightChangeRequests()
    {
        return $this->hasMany(WeightChangeRequest::class, 'task_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'task_id');
    }

    public function approvals()
    {
        return $this->hasMany(Approval::class, 'task_id');
    }

    public function slaRecords()
    {
        return $this->hasMany(SlaRecord::class, 'task_id');
    }

    public function dependenciesAsPredecessor()
    {
        return $this->hasMany(TaskDependency::class, 'predecessor_task_id');
    }

    public function dependenciesAsSuccessor()
    {
        return $this->hasMany(TaskDependency::class, 'successor_task_id');
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'attachable');
    }

    public function activityLogs()
    {
        return $this->morphMany(ActivityLog::class, 'entity');
    }
}
