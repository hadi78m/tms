<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}
