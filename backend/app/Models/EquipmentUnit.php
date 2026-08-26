<?php

namespace App\Models;

use Database\Factories\EquipmentUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['gym_id', 'name'])]
class EquipmentUnit extends Model
{
    /** @use HasFactory<EquipmentUnitFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (EquipmentUnit $unit) {
            $unit->qr_token ??= Str::random(32);
        });
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
