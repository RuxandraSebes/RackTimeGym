<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\GymClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_a_class(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/classes', [
            'name' => 'Morning Yoga',
            'starts_at' => now()->addDay()->toIso8601String(),
            'capacity' => 12,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('classes', [
            'gym_id' => $gym->id,
            'name' => 'Morning Yoga',
            'capacity' => 12,
        ]);
    }

    public function test_owner_can_create_a_class(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->owner()->for($gym)->create();

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/classes', [
            'name' => 'HIIT Circuit',
            'starts_at' => now()->addDay()->toIso8601String(),
            'capacity' => 8,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('classes', ['gym_id' => $gym->id, 'name' => 'HIIT Circuit']);
    }

    public function test_member_cannot_create_a_class(): void
    {
        $member = User::factory()->member()->create();

        $response = $this->actingAs($member, 'sanctum')->postJson('/api/classes', [
            'name' => 'Should Fail',
            'starts_at' => now()->addDay()->toIso8601String(),
            'capacity' => 10,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('classes', 0);
    }

    public function test_unauthenticated_request_cannot_create_a_class(): void
    {
        $this->postJson('/api/classes', [
            'name' => 'Should Fail',
            'starts_at' => now()->addDay()->toIso8601String(),
            'capacity' => 10,
        ])->assertUnauthorized();
    }

    public function test_creating_a_class_in_the_past_is_rejected(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/classes', [
            'name' => 'Too Late',
            'starts_at' => now()->subDay()->toIso8601String(),
            'capacity' => 10,
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('classes', 0);
    }

    public function test_created_class_is_always_scoped_to_the_creators_gym(): void
    {
        $creatorGym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($creatorGym)->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/classes', [
            'name' => 'Scoped Class',
            'starts_at' => now()->addDay()->toIso8601String(),
            'capacity' => 10,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('classes', ['name' => 'Scoped Class', 'gym_id' => $creatorGym->id]);
    }

    public function test_each_class_is_issued_its_own_qr_token_at_creation(): void
    {
        $gym = Gym::factory()->create();

        $first = GymClass::factory()->for($gym)->create();
        $second = GymClass::factory()->for($gym)->create();

        $this->assertNotNull($first->qr_token);
        $this->assertNotNull($second->qr_token);
        $this->assertNotSame($first->qr_token, $second->qr_token);
    }

    public function test_staff_can_edit_an_upcoming_class(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create(['name' => 'Old Name']);

        $response = $this->actingAs($staff, 'sanctum')->patchJson("/api/classes/{$class->id}", [
            'name' => 'New Name',
            'starts_at' => now()->addWeek()->toIso8601String(),
            'capacity' => 15,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('classes', ['id' => $class->id, 'name' => 'New Name', 'capacity' => 15]);
    }

    public function test_member_cannot_edit_a_class(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->patchJson("/api/classes/{$class->id}", [
            'name' => 'New Name',
            'starts_at' => now()->addWeek()->toIso8601String(),
            'capacity' => 15,
        ]);

        $response->assertForbidden();
    }

    public function test_editing_a_class_from_another_gym_is_rejected(): void
    {
        $staff = User::factory()->staff()->create();
        $otherGymClass = GymClass::factory()->create();

        $response = $this->actingAs($staff, 'sanctum')->patchJson("/api/classes/{$otherGymClass->id}", [
            'name' => 'New Name',
            'starts_at' => now()->addWeek()->toIso8601String(),
            'capacity' => 15,
        ]);

        $response->assertForbidden();
    }

    public function test_editing_a_cancelled_class_is_rejected(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $class = GymClass::factory()->cancelled()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->patchJson("/api/classes/{$class->id}", [
            'name' => 'New Name',
            'starts_at' => now()->addWeek()->toIso8601String(),
            'capacity' => 15,
        ]);

        $response->assertUnprocessable();
    }

    public function test_editing_a_class_that_already_started_is_rejected(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $class = GymClass::factory()->past()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->patchJson("/api/classes/{$class->id}", [
            'name' => 'New Name',
            'starts_at' => now()->addWeek()->toIso8601String(),
            'capacity' => 15,
        ]);

        $response->assertUnprocessable();
    }

    public function test_staff_can_cancel_an_upcoming_class(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/classes/{$class->id}/cancel");

        $response->assertOk();
        $this->assertNotNull($class->fresh()->cancelled_at);
    }

    public function test_member_cannot_cancel_a_class(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/classes/{$class->id}/cancel");

        $response->assertForbidden();
        $this->assertNull($class->fresh()->cancelled_at);
    }

    public function test_cancelling_a_class_from_another_gym_is_rejected(): void
    {
        $staff = User::factory()->staff()->create();
        $otherGymClass = GymClass::factory()->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/classes/{$otherGymClass->id}/cancel");

        $response->assertForbidden();
        $this->assertNull($otherGymClass->fresh()->cancelled_at);
    }

    public function test_cancelling_an_already_cancelled_class_is_rejected(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $class = GymClass::factory()->cancelled()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/classes/{$class->id}/cancel");

        $response->assertUnprocessable();
    }

    public function test_members_can_list_upcoming_classes_with_remaining_capacity(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 20]);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/classes');

        $response->assertOk();
        $data = collect($response->json('data'));
        $listed = $data->firstWhere('id', $class->id);
        $this->assertNotNull($listed);
        $this->assertSame(20, $listed['remaining_capacity']);
        $this->assertSame($class->starts_at->toIso8601String(), $listed['starts_at']);
    }

    public function test_upcoming_classes_list_excludes_cancelled_and_past_classes(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $upcoming = GymClass::factory()->for($gym)->create();
        $cancelled = GymClass::factory()->cancelled()->for($gym)->create();
        $past = GymClass::factory()->past()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/classes');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($upcoming->id));
        $this->assertFalse($ids->contains($cancelled->id));
        $this->assertFalse($ids->contains($past->id));
    }

    public function test_upcoming_classes_list_is_scoped_to_the_members_gym(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $ownClass = GymClass::factory()->for($gym)->create();
        $otherGymClass = GymClass::factory()->create();

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/classes');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($ownClass->id));
        $this->assertFalse($ids->contains($otherGymClass->id));
    }

    public function test_qr_token_is_never_included_in_the_classes_list(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        GymClass::factory()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/classes');

        $this->assertArrayNotHasKey('qr_token', $response->json('data.0'));
    }

    public function test_unauthenticated_request_cannot_list_classes(): void
    {
        $this->getJson('/api/classes')->assertUnauthorized();
    }

    public function test_staff_can_view_a_classs_qr_token(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->getJson("/api/classes/{$class->id}/qr");

        $response->assertOk()->assertJson(['qr_token' => $class->qr_token]);
    }

    public function test_member_cannot_view_a_classs_qr_token(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();

        $this->actingAs($member, 'sanctum')->getJson("/api/classes/{$class->id}/qr")->assertForbidden();
    }

    public function test_viewing_the_qr_token_of_a_class_from_another_gym_is_rejected(): void
    {
        $staff = User::factory()->staff()->create();
        $otherGymClass = GymClass::factory()->create();

        $this->actingAs($staff, 'sanctum')->getJson("/api/classes/{$otherGymClass->id}/qr")->assertForbidden();
    }
}
