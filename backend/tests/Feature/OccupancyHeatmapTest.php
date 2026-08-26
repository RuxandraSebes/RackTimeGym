<?php

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OccupancyHeatmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_the_gyms_occupancy_heatmap(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/gym/occupancy/heatmap');

        $response->assertOk();
    }

    public function test_staff_can_view_the_gyms_occupancy_heatmap(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/occupancy/heatmap');

        $response->assertOk();
    }

    public function test_owner_can_view_the_gyms_occupancy_heatmap(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->owner()->for($gym)->create();

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/gym/occupancy/heatmap');

        $response->assertOk();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/gym/occupancy/heatmap')->assertUnauthorized();
    }

    public function test_heatmap_returns_a_full_grid_with_zero_counts_when_there_are_no_check_ins(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/occupancy/heatmap');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(7 * 24, $data);
        $this->assertTrue(collect($data)->every(fn (array $bucket) => $bucket['count'] === 0));
        $response->assertJson(['busiest' => null, 'quietest' => null]);
    }

    public function test_heatmap_aggregates_check_ins_into_hour_of_day_and_day_of_week_buckets(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->create();

        $timestamp = Carbon::parse('2026-01-06 07:15:00');

        CheckIn::factory()->for($member, 'member')->for($gym)->create(['created_at' => $timestamp]);
        CheckIn::factory()->for($member, 'member')->for($gym)->create(['created_at' => $timestamp->copy()->addMinutes(20)]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/occupancy/heatmap');

        $response->assertOk();
        $bucket = collect($response->json('data'))
            ->firstWhere(fn (array $bucket) => $bucket['day_of_week'] === $timestamp->dayOfWeek && $bucket['hour'] === $timestamp->hour);

        $this->assertSame(2, $bucket['count']);
    }

    public function test_heatmap_identifies_the_busiest_and_quietest_combinations(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->create();

        $busyTime = Carbon::parse('2026-01-06 07:00:00');
        $quietTime = Carbon::parse('2026-01-07 03:00:00');

        CheckIn::factory()->for($member, 'member')->for($gym)->create(['created_at' => $busyTime]);
        CheckIn::factory()->for($member, 'member')->for($gym)->create(['created_at' => $busyTime->copy()->addMinutes(10)]);
        CheckIn::factory()->for($member, 'member')->for($gym)->create(['created_at' => $busyTime->copy()->addMinutes(20)]);
        CheckIn::factory()->for($member, 'member')->for($gym)->create(['created_at' => $quietTime]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/occupancy/heatmap');

        $response->assertOk();
        $response->assertJson([
            'busiest' => ['day_of_week' => $busyTime->dayOfWeek, 'hour' => $busyTime->hour, 'count' => 3],
        ]);

        $quietest = $response->json('quietest');
        $this->assertSame(0, $quietest['count']);
    }

    public function test_heatmap_is_scoped_to_the_requesting_users_gym(): void
    {
        $gym = Gym::factory()->create();
        $otherGym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $otherGymMember = User::factory()->member()->for($otherGym)->create();

        CheckIn::factory()->for($otherGymMember, 'member')->for($otherGym)->create([
            'created_at' => Carbon::parse('2026-01-06 07:00:00'),
        ]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/occupancy/heatmap');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertTrue(collect($data)->every(fn (array $bucket) => $bucket['count'] === 0));
    }
}
