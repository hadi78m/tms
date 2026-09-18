<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Enforce equality of username and national_code in the application layer
        static::saving(function (User $user) {
            if ($user->national_code) {
                $user->username = $user->national_code;
            }
        });
    }

    public function contractor()
    {
        return $this->belongsTo(SyncedContractor::class, 'contractor_id');
    }
}
