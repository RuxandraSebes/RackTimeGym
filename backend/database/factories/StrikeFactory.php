<?php

namespace Database\Factories;

use App\Enums\StrikeReason;
use App\Models\Booking;
use App\Models\Strike;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Strike>
 */
class StrikeFactory extends Factory
{
    protected $model = Strike::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->member(),
            'booking_id' => Booking::factory(),
            'reason' => StrikeReason::MissedCheckIn,
        ];
    }
}
