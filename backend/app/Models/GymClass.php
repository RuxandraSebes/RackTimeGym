<?php

namespace App\Models;

use Database\Factories\GymClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['gym_id', 'name', 'starts_at', 'capacity', 'cancelled_at'])]
class GymClass extends Model
{
    /** @use HasFactory<GymClassFactory> */
    use HasFactory;

    protected $table = 'classes';

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
