<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Gym;
use App\Models\GymClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_with_a_booking_can_check_into_the_class_by_scanning_its_qr(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();
        Booking::factory()->for($class, 'gymClass')->for($member, 'member')->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/checkins/class/{$class->qr_token}");

        $response->assertCreated();
        $this->assertDatabaseHas('check_ins', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'class_id' => $class->id,
        ]);
    }

    public function test_a_member_without_a_booking_cannot_check_into_the_class(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/checkins/class/{$class->qr_token}");

        $response->assertUnprocessable();
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_a_member_whose_booking_was_cancelled_cannot_check_into_the_class(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();
        Booking::factory()->for($class, 'gymClass')->for($member, 'member')->cancelled()->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/checkins/class/{$class->qr_token}");

        $response->assertUnprocessable();
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_scanning_a_class_qr_from_another_gym_is_rejected(): void
    {
        $member = User::factory()->member()->create();
        $otherGymClass = GymClass::factory()->create();
        Booking::factory()->for($otherGymClass, 'gymClass')->for($member, 'member')->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/checkins/class/{$otherGymClass->qr_token}");

        $response->assertForbidden();
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_scanning_an_unknown_class_qr_token_returns_not_found(): void
    {
        $member = User::factory()->member()->create();

        $this->actingAs($member, 'sanctum')->postJson('/api/checkins/class/not-a-real-token')->assertNotFound();
    }

    public function test_staff_cannot_check_into_a_class_themselves(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/checkins/class/{$class->qr_token}");

        $response->assertForbidden();
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_unauthenticated_request_cannot_check_into_a_class(): void
    {
        $class = GymClass::factory()->create();

        $this->postJson("/api/checkins/class/{$class->qr_token}")->assertUnauthorized();
    }

    public function test_class_check_ins_are_excluded_from_the_gyms_occupancy_count(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();
        Booking::factory()->for($class, 'gymClass')->for($member, 'member')->create();

        $this->actingAs($member, 'sanctum')->postJson("/api/checkins/class/{$class->qr_token}")->assertCreated();

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/gym/occupancy');

        $response->assertOk()->assertJson(['count' => 0]);
    }

    public function test_checking_in_settles_no_show_strikes_for_other_members_of_the_ended_class(): void
    {
        $gym = Gym::factory()->create();
        $class = GymClass::factory()->for($gym)->create(['starts_at' => now()->subHours(3)]);
        $attendee = User::factory()->member()->for($gym)->create();
        $noShow = User::factory()->member()->for($gym)->create();
        Booking::factory()->for($class, 'gymClass')->for($attendee, 'member')->create();
        $noShowBooking = Booking::factory()->for($class, 'gymClass')->for($noShow, 'member')->create();

        $this->actingAs($attendee, 'sanctum')->postJson("/api/checkins/class/{$class->qr_token}")->assertCreated();

        $this->assertDatabaseHas('strikes', [
            'user_id' => $noShow->id,
            'booking_id' => $noShowBooking->id,
            'reason' => 'missed_check_in',
        ]);
        $this->assertDatabaseMissing('strikes', ['user_id' => $attendee->id]);
    }
}
