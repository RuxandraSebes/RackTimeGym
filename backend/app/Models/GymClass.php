<?php

namespace App\Models;

use Database\Factories\GymClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable(['gym_id', 'name', 'starts_at', 'capacity', 'cancelled_at'])]
class GymClass extends Model
{
    /** @use HasFactory<GymClassFactory> */
    use HasFactory;

    protected $table = 'classes';

    private const WAITLIST_CONFIRMATION_WINDOW_MINUTES = 15;

    private const WAITLIST_OFFER_CUTOFF_SECONDS = 300;

    protected static function booted(): void
    {
        static::creating(function (GymClass $class) {
            $class->qr_token ??= Str::random(32);
        });
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'class_id');
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class, 'class_id');
    }

    /**
     * Offer the freed spot to the next waiting Member(s), in order, whenever
     * capacity allows it. A Member's offer holds the spot exclusively until it
     * is confirmed or its confirmation window lapses, at which point the next
     * call to this method offers it onward. Stops offering entirely once fewer
     * than 5 minutes remain before the Class starts.
     */
    public function settleWaitlist(): void
    {
        DB::transaction(function () {
            $class = self::whereKey($this->id)->lockForUpdate()->firstOrFail();

            $activeBookings = $class->bookings()->whereNull('cancelled_at')->count();
            $outstandingOffers = $class->activeWaitlistOffers()->count();

            $freeSlots = $class->capacity - $activeBookings - $outstandingOffers;
            if ($freeSlots <= 0) {
                return;
            }

            $now = now();
            if ($now->getTimestamp() > $class->starts_at->getTimestamp() - self::WAITLIST_OFFER_CUTOFF_SECONDS) {
                return;
            }

            $offerExpiresAt = $now->copy()->addMinutes(self::WAITLIST_CONFIRMATION_WINDOW_MINUTES);
            if ($class->starts_at->lessThan($offerExpiresAt)) {
                $offerExpiresAt = $class->starts_at->copy();
            }

            $class->waitlistEntries()
                ->whereNull('offered_at')
                ->whereNull('confirmed_at')
                ->orderBy('id')
                ->limit($freeSlots)
                ->get()
                ->each(fn (WaitlistEntry $entry) => $entry->update([
                    'offered_at' => $now,
                    'offer_expires_at' => $offerExpiresAt,
                ]));
        });
    }

    /**
     * Waitlist entries currently holding an unconfirmed, unexpired offer —
     * each one reserves a capacity slot exclusively for its Member.
     */
    public function activeWaitlistOffers(): HasMany
    {
        return $this->waitlistEntries()
            ->whereNotNull('offered_at')
            ->whereNull('confirmed_at')
            ->where('offer_expires_at', '>', now());
    }

    /**
     * @return Attribute<int, never>
     */
    protected function remainingCapacity(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->capacity
                - ($this->active_bookings_count ?? $this->bookings()->whereNull('cancelled_at')->count())
                - ($this->active_waitlist_offers_count ?? $this->activeWaitlistOffers()->count()),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
