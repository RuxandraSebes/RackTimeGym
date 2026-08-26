<?php

namespace App\Models;

use Database\Factories\CheckInFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'gym_id', 'recorded_by_id', 'equipment_unit_id'])]
class CheckIn extends Model
{
    /** @use HasFactory<CheckInFactory> */
    use HasFactory;

    /**
     * Door Check-ins only: Occupancy is a whole-floor headcount from Door
     * Check-ins, and Equipment Check-ins don't add to it separately.
     */
    public function scopeDoor(Builder $query): Builder
    {
        return $query->whereNull('equipment_unit_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    public function equipmentUnit(): BelongsTo
    {
        return $this->belongsTo(EquipmentUnit::class);
    }
}
