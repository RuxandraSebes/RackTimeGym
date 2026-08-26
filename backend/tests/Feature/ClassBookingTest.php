<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Gym;
use App\Models\GymClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_book_an_upcoming_class_with_remaining_capacity(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 5]);

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/classes/{$class->id}/bookings");

        $response->assertCreated();
        $this->assertDatabaseHas('bookings', [
            'class_id' => $class->id,
            'user_id' => $member->id,
            'cancelled_at' => null,
        ]);
    }

    public function test_booking_a_class_at_full_capacity_joins_the_waitlist_instead_of_being_rejected(): void
    {
        $gym = Gym::factory()->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 1]);
        Booking::factory()->for($class, 'gymClass')->create();
        $member = User::factory()->member()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/classes/{$class->id}/bookings");

        $response->assertCreated();
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('waitlist_entries', [
            'class_id' => $class->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_a_cancelled_booking_frees_up_the_capacity_slot_it_held(): void
    {
        $gym = Gym::factory()->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 1]);
        Booking::factory()->for($class, 'gymClass')->cancelled()->create();
        $member = User::factory()->member()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/classes/{$class->id}/bookings");

        $response->assertCreated();
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_member_cannot_book_the_same_class_twice(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 5]);
        Booking::factory()->for($class, 'gymClass')->for($member, 'member')->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/classes/{$class->id}/bookings");

        $response->assertUnprocessable();
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_booking_a_cancelled_class_is_rejected(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->cancelled()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/classes/{$class->id}/bookings");

        $response->assertUnprocessable();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_a_class_that_already_started_is_rejected(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->past()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/classes/{$class->id}/bookings");

        $response->assertUnprocessable();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_a_class_from_another_gym_is_rejected(): void
    {
        $member = User::factory()->member()->create();
        $otherGymClass = GymClass::factory()->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/classes/{$otherGymClass->id}/bookings");

        $response->assertForbidden();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_staff_cannot_book_a_class_for_themselves(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/classes/{$class->id}/bookings");

        $response->assertForbidden();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_unauthenticated_request_cannot_book_a_class(): void
    {
        $class = GymClass::factory()->create();

        $this->postJson("/api/classes/{$class->id}/bookings")->assertUnauthorized();
    }

    public function test_member_can_cancel_their_own_booking(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();
        $booking = Booking::factory()->for($class, 'gymClass')->for($member, 'member')->create();

        $response = $this->actingAs($member, 'sanctum')->deleteJson("/api/bookings/{$booking->id}");

        $response->assertOk();
        $this->assertNotNull($booking->fresh()->cancelled_at);
    }

    public function test_member_cannot_cancel_another_members_booking(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $otherMember = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();
        $booking = Booking::factory()->for($class, 'gymClass')->for($otherMember, 'member')->create();

        $response = $this->actingAs($member, 'sanctum')->deleteJson("/api/bookings/{$booking->id}");

        $response->assertForbidden();
        $this->assertNull($booking->fresh()->cancelled_at);
    }

    public function test_cancelling_an_already_cancelled_booking_is_rejected(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();
        $booking = Booking::factory()->for($class, 'gymClass')->for($member, 'member')->cancelled()->create();

        $response = $this->actingAs($member, 'sanctum')->deleteJson("/api/bookings/{$booking->id}");

        $response->assertUnprocessable();
    }

    public function test_unauthenticated_request_cannot_cancel_a_booking(): void
    {
        $booking = Booking::factory()->create();

        $this->deleteJson("/api/bookings/{$booking->id}")->assertUnauthorized();
    }

    public function test_member_can_see_their_own_upcoming_bookings(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $upcomingClass = GymClass::factory()->for($gym)->create();
        $booking = Booking::factory()->for($upcomingClass, 'gymClass')->for($member, 'member')->create();

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/bookings');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($booking->id));
    }

    public function test_own_upcoming_bookings_excludes_cancelled_bookings_and_past_classes(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $upcomingClass = GymClass::factory()->for($gym)->create();
        $pastClass = GymClass::factory()->past()->for($gym)->create();
        $activeBooking = Booking::factory()->for($upcomingClass, 'gymClass')->for($member, 'member')->create();
        $cancelledBooking = Booking::factory()->for($upcomingClass, 'gymClass')->for($member, 'member')->cancelled()->create();
        $pastBooking = Booking::factory()->for($pastClass, 'gymClass')->for($member, 'member')->create();

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/bookings');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($activeBooking->id));
        $this->assertFalse($ids->contains($cancelledBooking->id));
        $this->assertFalse($ids->contains($pastBooking->id));
    }

    public function test_own_upcoming_bookings_excludes_bookings_for_a_class_staff_later_cancelled(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $cancelledClass = GymClass::factory()->cancelled()->for($gym)->create();
        $booking = Booking::factory()->for($cancelledClass, 'gymClass')->for($member, 'member')->create();

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/bookings');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($booking->id));
    }

    public function test_own_upcoming_bookings_only_includes_the_authenticated_members_bookings(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $otherMember = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();
        $ownBooking = Booking::factory()->for($class, 'gymClass')->for($member, 'member')->create();
        $otherBooking = Booking::factory()->for($class, 'gymClass')->for($otherMember, 'member')->create();

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/bookings');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($ownBooking->id));
        $this->assertFalse($ids->contains($otherBooking->id));
    }

    public function test_staff_can_see_the_roster_of_a_class(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();
        $booking = Booking::factory()->for($class, 'gymClass')->for($member, 'member')->create();

        $response = $this->actingAs($staff, 'sanctum')->getJson("/api/classes/{$class->id}/bookings");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($booking->id));
        $this->assertSame($member->name, collect($response->json('data'))->firstWhere('id', $booking->id)['member']['name']);
    }

    public function test_owner_can_see_the_roster_of_a_class(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->owner()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();

        $response = $this->actingAs($owner, 'sanctum')->getJson("/api/classes/{$class->id}/bookings");

        $response->assertOk();
    }

    public function test_roster_excludes_cancelled_bookings(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();
        $cancelledBooking = Booking::factory()->for($class, 'gymClass')->cancelled()->create(['class_id' => $class->id]);

        $response = $this->actingAs($staff, 'sanctum')->getJson("/api/classes/{$class->id}/bookings");

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($cancelledBooking->id));
    }

    public function test_member_cannot_see_the_roster_of_a_class(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create();

        $this->actingAs($member, 'sanctum')->getJson("/api/classes/{$class->id}/bookings")->assertForbidden();
    }

    public function test_viewing_the_roster_of_a_class_from_another_gym_is_rejected(): void
    {
        $staff = User::factory()->staff()->create();
        $otherGymClass = GymClass::factory()->create();

        $this->actingAs($staff, 'sanctum')->getJson("/api/classes/{$otherGymClass->id}/bookings")->assertForbidden();
    }

    public function test_classes_list_reports_remaining_capacity_after_bookings(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 5]);
        Booking::factory()->count(2)->for($class, 'gymClass')->create();
        Booking::factory()->for($class, 'gymClass')->cancelled()->create();

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/classes');

        $listed = collect($response->json('data'))->firstWhere('id', $class->id);
        $this->assertSame(3, $listed['remaining_capacity']);
    }
}
