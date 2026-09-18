<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskDependency extends Model
{
    public $timestamps = false;

    // As per V1.7, this model only has created_at (no updated_at).
    // We override the saving mechanisms to prevent updates.

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Immutable model: prevent any updates or deletes
        static::updating(function () {
            return false;
        });

        static::deleting(function () {
            return false;
        });
    }

    public function predecessor()
    {
        return $this->belongsTo(Task::class, 'predecessor_task_id');
    }

    public function successor()
    {
        return $this->belongsTo(Task::class, 'successor_task_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
