<?php

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OccupancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_the_gyms_occupancy(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/gym/occupancy');

        $response->assertOk()->assertJson(['count' => 0]);
    }

    public function test_staff_can_view_the_gyms_occupancy(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/occupancy');

        $response->assertOk()->assertJson(['count' => 0]);
    }

    public function test_owner_can_view_the_gyms_occupancy(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->owner()->for($gym)->create();

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/gym/occupancy');

        $response->assertOk()->assertJson(['count' => 0]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/gym/occupancy')->assertUnauthorized();
    }

    public function test_occupancy_counts_members_checked_in_within_the_last_90_minutes(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $recentMember = User::factory()->member()->for($gym)->create();

        CheckIn::factory()->for($recentMember, 'member')->for($gym)->create([
            'created_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/occupancy');

        $response->assertOk()->assertJson(['count' => 1]);
    }

    public function test_occupancy_excludes_check_ins_older_than_90_minutes(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $staleMember = User::factory()->member()->for($gym)->create();

        CheckIn::factory()->for($staleMember, 'member')->for($gym)->create([
            'created_at' => now()->subMinutes(91),
        ]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/occupancy');

        $response->assertOk()->assertJson(['count' => 0]);
    }

    public function test_occupancy_counts_each_member_once_even_with_multiple_recent_check_ins(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->create();

        CheckIn::factory()->for($member, 'member')->for($gym)->create(['created_at' => now()->subMinutes(60)]);
        CheckIn::factory()->for($member, 'member')->for($gym)->create(['created_at' => now()->subMinutes(10)]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/occupancy');

        $response->assertOk()->assertJson(['count' => 1]);
    }

    public function test_occupancy_counts_a_member_whose_latest_check_in_is_recent_even_if_an_older_one_has_expired(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->create();

        CheckIn::factory()->for($member, 'member')->for($gym)->create(['created_at' => now()->subMinutes(200)]);
        CheckIn::factory()->for($member, 'member')->for($gym)->create(['created_at' => now()->subMinutes(5)]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/occupancy');

        $response->assertOk()->assertJson(['count' => 1]);
    }

    public function test_occupancy_excludes_a_check_in_exactly_90_minutes_old(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->create();

        CheckIn::factory()->for($member, 'member')->for($gym)->create(['created_at' => now()->subMinutes(90)]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/occupancy');

        $response->assertOk()->assertJson(['count' => 0]);
    }

    public function test_occupancy_is_scoped_to_the_requesting_users_gym(): void
    {
        $gym = Gym::factory()->create();
        $otherGym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $otherGymMember = User::factory()->member()->for($otherGym)->create();

        CheckIn::factory()->for($otherGymMember, 'member')->for($otherGym)->create([
            'created_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/occupancy');

        $response->assertOk()->assertJson(['count' => 0]);
    }
}
