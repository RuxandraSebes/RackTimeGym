<?php

namespace App\Models;

use App\Enums\StrikeReason;
use Database\Factories\StrikeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'booking_id', 'reason'])]
class Strike extends Model
{
    /** @use HasFactory<StrikeFactory> */
    use HasFactory;

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => StrikeReason::class,
        ];
    }
}
