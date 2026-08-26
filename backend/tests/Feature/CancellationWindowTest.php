<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Gym;
use App\Models\GymClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancellationWindowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_member_defaults_to_the_gyms_configured_cancellation_window(): void
    {
        $gym = Gym::factory()->create(['cancellation_window_minutes' => 720]);
        $owner = User::factory()->owner()->for($gym)->create();

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/gym/users', [
            'name' => 'New Member',
            'email' => 'new-member@example.com',
            'password' => 'a-strong-password',
            'role' => 'member',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'new-member@example.com',
            'cancellation_window_minutes' => 720,
        ]);
    }

    public function test_staff_can_override_a_members_cancellation_window(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->cancellationWindow(1440)->create();

        $response = $this->actingAs($staff, 'sanctum')->patchJson("/api/gym/users/{$member->id}/cancellation-window", [
            'cancellation_window_minutes' => 60,
        ]);

        $response->assertOk();
        $this->assertSame(60, $member->fresh()->cancellation_window_minutes);
    }

    public function test_owner_can_override_a_members_cancellation_window(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->owner()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->create();

        $response = $this->actingAs($owner, 'sanctum')->patchJson("/api/gym/users/{$member->id}/cancellation-window", [
            'cancellation_window_minutes' => 30,
        ]);

        $response->assertOk();
        $this->assertSame(30, $member->fresh()->cancellation_window_minutes);
    }

    public function test_member_cannot_override_their_own_cancellation_window(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->patchJson("/api/gym/users/{$member->id}/cancellation-window", [
            'cancellation_window_minutes' => 0,
        ]);

        $response->assertForbidden();
    }

    public function test_staff_cannot_override_the_cancellation_window_of_a_member_from_another_gym(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $otherGymMember = User::factory()->member()->create();

        $response = $this->actingAs($staff, 'sanctum')->patchJson("/api/gym/users/{$otherGymMember->id}/cancellation-window", [
            'cancellation_window_minutes' => 60,
        ]);

        $response->assertForbidden();
    }

    public function test_cancelling_a_booking_outside_the_cancellation_window_does_not_record_a_strike(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->cancellationWindow(60)->create();
        $class = GymClass::factory()->for($gym)->create(['starts_at' => now()->addHours(3)]);
        $booking = Booking::factory()->for($class, 'gymClass')->for($member, 'member')->create();

        $this->actingAs($member, 'sanctum')->deleteJson("/api/bookings/{$booking->id}")->assertOk();

        $this->assertDatabaseCount('strikes', 0);
    }

    public function test_cancelling_a_booking_inside_the_cancellation_window_records_a_strike(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->cancellationWindow(1440)->create();
        $class = GymClass::factory()->for($gym)->create(['starts_at' => now()->addHours(2)]);
        $booking = Booking::factory()->for($class, 'gymClass')->for($member, 'member')->create();

        $this->actingAs($member, 'sanctum')->deleteJson("/api/bookings/{$booking->id}")->assertOk();

        $this->assertDatabaseHas('strikes', [
            'user_id' => $member->id,
            'booking_id' => $booking->id,
            'reason' => 'late_cancellation',
        ]);
    }
}
