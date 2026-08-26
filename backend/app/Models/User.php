<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\StrikeReason;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'gym_id', 'cancellation_window_minutes', 'membership_status', 'membership_reactivated_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    private const STRIKES_BEFORE_SUSPENSION = 3;

    private const STRIKE_ROLLING_WINDOW_DAYS = 30;

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->membership_status ??= MembershipStatus::Active;

            if ($user->role === Role::Member) {
                $user->cancellation_window_minutes ??= Gym::find($user->gym_id)?->cancellation_window_minutes;
            }
        });
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function strikes(): HasMany
    {
        return $this->hasMany(Strike::class);
    }

    public function hasActiveMembership(): bool
    {
        return $this->membership_status === MembershipStatus::Active;
    }

    /**
     * Record a Strike against this Member's Membership for the given Booking,
     * suspending the Membership once the rolling-window count reaches the
     * suspension threshold.
     */
    public function recordStrike(Booking $booking, StrikeReason $reason): Strike
    {
        return DB::transaction(function () use ($booking, $reason) {
            $member = self::whereKey($this->id)->lockForUpdate()->firstOrFail();

            $strike = $member->strikes()->create([
                'booking_id' => $booking->id,
                'reason' => $reason,
            ]);

            if ($member->hasActiveMembership() && $member->activeStrikeCount() >= self::STRIKES_BEFORE_SUSPENSION) {
                $member->update(['membership_status' => MembershipStatus::Inactive]);
            }

            return $strike;
        });
    }

    /**
     * How many Strikes count toward suspension right now: those within the
     * rolling 30-day window, and no older than the last reactivation (which
     * resets the count even though Strikes don't expire individually).
     */
    public function activeStrikeCount(): int
    {
        $windowStart = now()->subDays(self::STRIKE_ROLLING_WINDOW_DAYS);

        if ($this->membership_reactivated_at !== null && $this->membership_reactivated_at->greaterThan($windowStart)) {
            $windowStart = $this->membership_reactivated_at;
        }

        return $this->strikes()->where('created_at', '>=', $windowStart)->count();
    }

    /**
     * Manually reactivate a suspended Membership. Never happens automatically.
     */
    public function reactivateMembership(): void
    {
        $this->update([
            'membership_status' => MembershipStatus::Active,
            'membership_reactivated_at' => now(),
        ]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'membership_status' => MembershipStatus::class,
            'membership_reactivated_at' => 'datetime',
        ];
    }
}
