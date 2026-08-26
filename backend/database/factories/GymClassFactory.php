<?php

namespace Database\Factories;

use App\Models\Gym;
use App\Models\GymClass;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GymClass>
 */
class GymClassFactory extends Factory
{
    protected $model = GymClass::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'name' => fake()->randomElement(['Morning Yoga', 'HIIT Circuit', 'Spin', 'Strength Basics']),
            'starts_at' => fake()->dateTimeBetween('+1 day', '+2 weeks'),
            'capacity' => fake()->numberBetween(5, 20),
            'qr_token' => Str::random(32),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(['cancelled_at' => now()]);
    }

    public function past(): static
    {
        return $this->state(['starts_at' => now()->subDay()]);
    }
}
