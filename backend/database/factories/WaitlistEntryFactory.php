<?php

namespace Database\Factories;

use App\Models\GymClass;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    protected $model = WaitlistEntry::class;

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

    public function offered(): static
    {
        return $this->state([
            'offered_at' => now(),
            'offer_expires_at' => now()->addMinutes(15),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state([
            'offered_at' => now(),
            'offer_expires_at' => now()->addMinutes(15),
            'confirmed_at' => now(),
        ]);
    }
}
