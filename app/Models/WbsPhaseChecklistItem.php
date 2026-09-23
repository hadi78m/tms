<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A checklist item of a WBS Phase.
 *
 * Checklist items carry NO computational weight: they only express
 * complete / incomplete plus who and when. Soft deleted because an item is
 * business evidence and its removal must stay traceable.
 */
class WbsPhaseChecklistItem extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function wbsPhase(): BelongsTo
    {
        return $this->belongsTo(WbsPhase::class, 'wbs_phase_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
