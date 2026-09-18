<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeightChangeRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'old_weight' => 'decimal:2',
        'new_weight' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
