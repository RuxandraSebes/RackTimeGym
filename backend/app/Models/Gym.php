<?php

namespace App\Models;

use Database\Factories\GymFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name'])]
class Gym extends Model
{
    /** @use HasFactory<GymFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Gym $gym) {
            $gym->door_qr_token ??= Str::random(32);
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function equipmentUnits(): HasMany
    {
        return $this->hasMany(EquipmentUnit::class);
    }
}
