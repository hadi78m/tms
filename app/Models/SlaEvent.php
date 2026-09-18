<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlaEvent extends Model
{
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'occurred_at' => 'datetime',
        'metadata' => 'array',
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

    public function slaRecord()
    {
        return $this->belongsTo(SlaRecord::class, 'sla_record_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
