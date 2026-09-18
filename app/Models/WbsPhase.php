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
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'wbs_phase_id');
    }
}
