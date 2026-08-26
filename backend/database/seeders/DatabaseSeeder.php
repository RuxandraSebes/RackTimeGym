<?php

namespace Database\Seeders;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $gym = Gym::factory()->create(['name' => 'RackTime Gym']);

        User::factory()->owner()->create([
            'name' => 'Olivia Owner',
            'email' => 'owner@racktimegym.test',
            'gym_id' => $gym->id,
        ]);

        User::factory()->staff()->create([
            'name' => 'Sam Staff',
            'email' => 'staff@racktimegym.test',
            'gym_id' => $gym->id,
        ]);

        User::factory()->member()->create([
            'name' => 'Mia Member',
            'email' => 'member@racktimegym.test',
            'gym_id' => $gym->id,
        ]);
    }
}
