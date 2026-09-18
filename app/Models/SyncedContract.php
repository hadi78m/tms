<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SyncedContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'amount' => 'decimal:2',
        'source_updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function contractor()
    {
        return $this->belongsTo(SyncedContractor::class, 'contractor_id');
    }

    public function system()
    {
        return $this->belongsTo(SyncedSystem::class, 'system_id');
    }

    public function project()
    {
        return $this->hasOne(Project::class, 'contract_id');
    }
}
