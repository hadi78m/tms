<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceRecord extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_weight_completed' => 'decimal:2',
    ];

    public function contract()
    {
        return $this->belongsTo(SyncedContract::class, 'contract_id');
    }

    public function contractor()
    {
        return $this->belongsTo(SyncedContractor::class, 'contractor_id');
    }
}
