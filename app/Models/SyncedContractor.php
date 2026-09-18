<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SyncedContractor extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'source_updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'contractor_id');
    }

    public function contracts()
    {
        return $this->hasMany(SyncedContract::class, 'contractor_id');
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'contractor_id');
    }
}
