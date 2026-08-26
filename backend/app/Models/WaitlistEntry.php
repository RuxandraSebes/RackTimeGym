<?php

namespace App\Models;

use Database\Factories\WaitlistEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['class_id', 'user_id', 'offered_at', 'offer_expires_at', 'confirmed_at'])]
class WaitlistEntry extends Model
{
    /** @use HasFactory<WaitlistEntryFactory> */
    use HasFactory;

    public function gymClass(): BelongsTo
    {
        return $this->belongsTo(GymClass::class, 'class_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Still waiting for (or holding) a spot: not yet confirmed into a Booking,
     * and not sitting on a lapsed, unconfirmed offer.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('confirmed_at')
            ->where(fn ($query) => $query->whereNull('offered_at')->orWhere('offer_expires_at', '>', now()));
    }

    /**
     * This Member's 1-based rank among the Class's pending Waitlist entries.
     */
    public function position(): int
    {
        return static::where('class_id', $this->class_id)
            ->pending()
            ->where('id', '<', $this->id)
            ->count() + 1;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'offered_at' => 'datetime',
            'offer_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }
}
