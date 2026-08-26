<?php

namespace App\Models;

use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['equipment_unit_id', 'user_id', 'starts_at', 'confirmed_at'])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    public const SLOT_MINUTES = 30;

    public const CHECKIN_GRACE_MINUTES = 5;

    public function equipmentUnit(): BelongsTo
    {
        return $this->belongsTo(EquipmentUnit::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Still holding its slot: either already confirmed by a Check-in, or not
     * yet past the grace period a Member has to check in after the slot starts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn ($query) => $query->whereNotNull('confirmed_at')
            ->orWhere('starts_at', '>', now()->subMinutes(self::CHECKIN_GRACE_MINUTES)));
    }

    /**
     * @return Attribute<Carbon, never>
     */
    protected function endsAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->starts_at->copy()->addMinutes(self::SLOT_MINUTES),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }
}
