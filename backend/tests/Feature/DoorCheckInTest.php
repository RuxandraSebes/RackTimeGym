<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoorCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_the_gyms_door_qr_token(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/door-qr');

        $response->assertOk()->assertJson(['door_qr_token' => $gym->door_qr_token]);
    }

    public function test_member_cannot_view_the_gyms_door_qr_token(): void
    {
        $member = User::factory()->member()->create();

        $this->actingAs($member, 'sanctum')->getJson('/api/gym/door-qr')->assertForbidden();
    }

    public function test_member_scanning_the_door_qr_creates_a_check_in(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/checkins/door/{$gym->door_qr_token}");

        $response->assertCreated();
        $this->assertDatabaseHas('check_ins', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'recorded_by_id' => null,
        ]);
        $this->assertNotNull($response->json('data.checked_in_at'));
    }

    public function test_scanning_a_door_qr_from_another_gym_is_rejected(): void
    {
        $ownGym = Gym::factory()->create();
        $otherGym = Gym::factory()->create();
        $member = User::factory()->member()->for($ownGym)->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/checkins/door/{$otherGym->door_qr_token}");

        $response->assertForbidden();
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_scanning_an_unknown_door_qr_token_returns_not_found(): void
    {
        $member = User::factory()->member()->create();

        $this->actingAs($member, 'sanctum')->postJson('/api/checkins/door/not-a-real-token')->assertNotFound();
    }

    public function test_staff_cannot_scan_the_door_qr_themselves(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/checkins/door/{$gym->door_qr_token}");

        $response->assertForbidden();
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_unauthenticated_scan_is_rejected(): void
    {
        $gym = Gym::factory()->create();

        $this->postJson("/api/checkins/door/{$gym->door_qr_token}")->assertUnauthorized();
    }

    public function test_staff_can_manually_check_in_a_member_of_their_gym(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/gym/checkins', [
            'user_id' => $member->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('check_ins', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'recorded_by_id' => $staff->id,
        ]);
        $this->assertSame($member->name, $response->json('data.member.name'));
    }

    public function test_staff_cannot_manually_check_in_a_member_from_another_gym(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $otherGymMember = User::factory()->member()->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/gym/checkins', [
            'user_id' => $otherGymMember->id,
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_staff_cannot_manually_check_in_a_staff_or_owner_account(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $otherStaff = User::factory()->staff()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/gym/checkins', [
            'user_id' => $otherStaff->id,
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_member_cannot_manually_check_in_another_member(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $otherMember = User::factory()->member()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->postJson('/api/gym/checkins', [
            'user_id' => $otherMember->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_unauthenticated_request_cannot_manually_check_in_a_member(): void
    {
        $member = User::factory()->member()->create();

        $this->postJson('/api/gym/checkins', ['user_id' => $member->id])->assertUnauthorized();
    }
}
