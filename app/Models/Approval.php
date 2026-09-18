<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'requested_at' => 'datetime',
        'acted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Immutable history model: prevent any updates or deletes
        static::updating(function () {
            return false;
        });

        static::deleting(function () {
            return false;
        });
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
