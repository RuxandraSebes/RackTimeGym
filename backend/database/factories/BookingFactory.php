<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\GymClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => GymClass::factory(),
            'user_id' => User::factory()->member(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(['cancelled_at' => now()]);
    }
}
