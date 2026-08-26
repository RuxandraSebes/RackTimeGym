<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GymAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_their_own_gym_roster(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->owner()->for($gym)->create();
        User::factory()->member()->for($gym)->create();

        $otherGymUser = User::factory()->member()->create();

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/gym/users');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($owner->id));
        $this->assertFalse($ids->contains($otherGymUser->id));
    }

    public function test_member_cannot_view_gym_roster(): void
    {
        $member = User::factory()->member()->create();

        $this->actingAs($member, 'sanctum')->getJson('/api/gym/users')->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_view_gym_roster(): void
    {
        $this->getJson('/api/gym/users')->assertUnauthorized();
    }

    public function test_owner_can_create_a_staff_account_in_their_own_gym(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->owner()->for($gym)->create();

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/gym/users', [
            'name' => 'New Staff',
            'email' => 'new-staff@example.com',
            'password' => 'a-strong-password',
            'role' => 'staff',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'new-staff@example.com',
            'role' => 'staff',
            'gym_id' => $gym->id,
        ]);
    }

    public function test_staff_can_create_a_member_account(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/gym/users', [
            'name' => 'New Member',
            'email' => 'new-member@example.com',
            'password' => 'a-strong-password',
            'role' => 'member',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'new-member@example.com',
            'role' => 'member',
            'gym_id' => $gym->id,
        ]);
    }

    public function test_staff_cannot_create_a_staff_account(): void
    {
        $staff = User::factory()->staff()->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/gym/users', [
            'name' => 'Should Fail',
            'email' => 'should-fail@example.com',
            'password' => 'a-strong-password',
            'role' => 'staff',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'should-fail@example.com']);
    }

    public function test_member_cannot_create_any_account(): void
    {
        $member = User::factory()->member()->create();

        $response = $this->actingAs($member, 'sanctum')->postJson('/api/gym/users', [
            'name' => 'Should Fail',
            'email' => 'should-fail-2@example.com',
            'password' => 'a-strong-password',
            'role' => 'member',
        ]);

        $response->assertForbidden();
    }

    public function test_created_account_is_always_scoped_to_the_creators_gym(): void
    {
        $creatorGym = Gym::factory()->create();
        $owner = User::factory()->owner()->for($creatorGym)->create();
        $otherGym = Gym::factory()->create();

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/gym/users', [
            'name' => 'New Member',
            'email' => 'scoped-member@example.com',
            'password' => 'a-strong-password',
            'role' => 'member',
            'gym_id' => $otherGym->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'scoped-member@example.com',
            'gym_id' => $creatorGym->id,
        ]);
    }
}
