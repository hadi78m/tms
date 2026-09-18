<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlaRecord extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
        'breached_at' => 'datetime',
        'is_breached' => 'boolean',
        'target_duration' => 'integer', // in minutes
        'actual_duration' => 'integer', // in minutes
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function events()
    {
        return $this->hasMany(SlaEvent::class, 'sla_record_id');
    }
}
