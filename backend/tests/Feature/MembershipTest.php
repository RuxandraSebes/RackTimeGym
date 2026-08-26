<?php

namespace Tests\Feature;

use App\Enums\StrikeReason;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\GymClass;
use App\Models\Strike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_booking_never_cancelled_and_never_checked_into_records_a_strike_once_the_class_has_ended(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create(['starts_at' => now()->subHours(3)]);
        $booking = Booking::factory()->for($class, 'gymClass')->for($member, 'member')->create();

        $this->actingAs($staff, 'sanctum')->getJson("/api/classes/{$class->id}/bookings")->assertOk();

        $this->assertDatabaseHas('strikes', [
            'user_id' => $member->id,
            'booking_id' => $booking->id,
            'reason' => 'missed_check_in',
        ]);
    }

    public function test_a_member_who_checked_in_does_not_receive_a_no_show_strike(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create(['starts_at' => now()->subHours(3)]);
        Booking::factory()->for($class, 'gymClass')->for($member, 'member')->create();
        CheckIn::factory()->for($member, 'member')->forClass($class)->create();

        $this->actingAs($staff, 'sanctum')->getJson("/api/classes/{$class->id}/bookings")->assertOk();

        $this->assertDatabaseCount('strikes', 0);
    }

    public function test_a_booking_for_a_class_still_in_progress_does_not_yet_receive_a_no_show_strike(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create(['starts_at' => now()->subMinutes(10)]);
        Booking::factory()->for($class, 'gymClass')->for($member, 'member')->create();

        $this->actingAs($staff, 'sanctum')->getJson("/api/classes/{$class->id}/bookings")->assertOk();

        $this->assertDatabaseCount('strikes', 0);
    }

    public function test_settlement_does_not_record_a_duplicate_strike_for_the_same_booking(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create(['starts_at' => now()->subHours(3)]);
        $booking = Booking::factory()->for($class, 'gymClass')->for($member, 'member')->create();

        $this->actingAs($staff, 'sanctum')->getJson("/api/classes/{$class->id}/bookings")->assertOk();
        $this->actingAs($staff, 'sanctum')->getJson("/api/classes/{$class->id}/bookings")->assertOk();

        $this->assertDatabaseCount('strikes', 1);
    }

    public function test_three_strikes_within_30_days_moves_the_membership_to_inactive(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        Strike::factory()->for($member, 'member')->count(2)->create(['created_at' => now()->subDays(5)]);

        $booking = Booking::factory()->for($member, 'member')->create();
        $member->recordStrike($booking, StrikeReason::MissedCheckIn);

        $this->assertSame('inactive', $member->fresh()->membership_status->value);
    }

    public function test_strikes_older_than_30_days_do_not_count_toward_suspension(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        Strike::factory()->for($member, 'member')->count(2)->create(['created_at' => now()->subDays(31)]);

        $booking = Booking::factory()->for($member, 'member')->create();
        $member->recordStrike($booking, StrikeReason::MissedCheckIn);

        $this->assertSame('active', $member->fresh()->membership_status->value);
    }

    public function test_an_inactive_membership_blocks_class_booking(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->inactiveMembership()->create();
        $class = GymClass::factory()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/classes/{$class->id}/bookings");

        $response->assertForbidden();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_an_inactive_membership_blocks_self_service_door_check_in(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->inactiveMembership()->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/checkins/door/{$gym->door_qr_token}");

        $response->assertForbidden();
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_an_inactive_membership_blocks_staff_manual_check_in(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->inactiveMembership()->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/gym/checkins', [
            'user_id' => $member->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_staff_can_reactivate_a_suspended_membership(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->inactiveMembership()->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/gym/users/{$member->id}/reactivate");

        $response->assertOk();
        $this->assertSame('active', $member->fresh()->membership_status->value);
    }

    public function test_owner_can_reactivate_a_suspended_membership(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->owner()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->inactiveMembership()->create();

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/gym/users/{$member->id}/reactivate");

        $response->assertOk();
        $this->assertSame('active', $member->fresh()->membership_status->value);
    }

    public function test_member_cannot_reactivate_their_own_membership(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->inactiveMembership()->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/gym/users/{$member->id}/reactivate");

        $response->assertForbidden();
        $this->assertSame('inactive', $member->fresh()->membership_status->value);
    }

    public function test_reactivating_an_already_active_membership_is_rejected(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/gym/users/{$member->id}/reactivate");

        $response->assertUnprocessable();
    }

    public function test_reactivating_resets_the_strike_count_so_old_strikes_no_longer_count_toward_suspension(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->inactiveMembership()->create();
        Strike::factory()->for($member, 'member')->count(3)->create(['created_at' => now()->subDays(5)]);

        $member->reactivateMembership();

        $booking = Booking::factory()->for($member, 'member')->create();
        $member->recordStrike($booking, StrikeReason::MissedCheckIn);

        $this->assertSame('active', $member->fresh()->membership_status->value);
        $this->assertDatabaseCount('strikes', 4);
    }

    public function test_staff_cannot_reactivate_a_member_from_another_gym(): void
    {
        $staff = User::factory()->staff()->create();
        $otherGymMember = User::factory()->member()->inactiveMembership()->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/gym/users/{$otherGymMember->id}/reactivate");

        $response->assertForbidden();
    }
}
