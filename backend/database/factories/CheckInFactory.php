<?php

namespace Database\Factories;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\GymClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckIn>
 */
class CheckInFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->member(),
            'gym_id' => Gym::factory(),
        ];
    }

    public function forClass(GymClass $class): static
    {
        return $this->state([
            'gym_id' => $class->gym_id,
            'class_id' => $class->id,
        ]);
    }
}
