<?php

namespace Database\Factories;

use App\Models\EquipmentUnit;
use App\Models\Gym;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EquipmentUnit>
 */
class EquipmentUnitFactory extends Factory
{
    protected $model = EquipmentUnit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'name' => fake()->randomElement(['Platform 1', 'Platform 2', 'Platform 3', 'Rower 1', 'Rack A']),
            'qr_token' => Str::random(32),
        ];
    }
}
