<?php

namespace Database\Factories;

use App\Models\EquipmentUnit;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'equipment_unit_id' => EquipmentUnit::factory(),
            'user_id' => User::factory()->member(),
            'starts_at' => fake()->dateTimeBetween('+1 hour', '+2 weeks'),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['confirmed_at' => now()]);
    }

    public function released(): static
    {
        return $this->state([
            'starts_at' => now()->subMinutes(6),
            'confirmed_at' => null,
        ]);
    }
}
