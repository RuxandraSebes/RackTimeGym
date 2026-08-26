<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Gym;
use App\Models\GymClass;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_join_the_waitlist_in_order(): void
    {
        $gym = Gym::factory()->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 1]);
        Booking::factory()->for($class, 'gymClass')->create();
        $first = User::factory()->member()->for($gym)->create();
        $second = User::factory()->member()->for($gym)->create();

        $this->actingAs($first, 'sanctum')->postJson("/api/classes/{$class->id}/bookings")->assertCreated();
        $this->actingAs($second, 'sanctum')->postJson("/api/classes/{$class->id}/bookings")->assertCreated();

        $firstEntry = WaitlistEntry::where('user_id', $first->id)->first();
        $secondEntry = WaitlistEntry::where('user_id', $second->id)->first();

        $this->assertSame(1, $this->actingAs($first, 'sanctum')->getJson('/api/waitlist-entries')->json('data.0.position'));
        $this->assertSame(2, $this->actingAs($second, 'sanctum')->getJson('/api/waitlist-entries')->json('data.0.position'));
        $this->assertTrue($firstEntry->id < $secondEntry->id);
    }

    public function test_a_member_cannot_join_the_same_waitlist_twice(): void
    {
        $gym = Gym::factory()->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 1]);
        Booking::factory()->for($class, 'gymClass')->create();
        $member = User::factory()->member()->for($gym)->create();
        WaitlistEntry::factory()->for($class, 'gymClass')->for($member, 'member')->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/classes/{$class->id}/bookings");

        $response->assertUnprocessable();
        $this->assertDatabaseCount('waitlist_entries', 1);
    }

    public function test_cancelling_a_booking_offers_the_spot_to_the_next_waitlisted_member(): void
    {
        $gym = Gym::factory()->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 1, 'starts_at' => now()->addHours(2)]);
        $booking = Booking::factory()->for($class, 'gymClass')->create();
        $waiting = User::factory()->member()->for($gym)->create();
        $entry = WaitlistEntry::factory()->for($class, 'gymClass')->for($waiting, 'member')->create();

        $owner = $booking->member;
        $this->actingAs($owner, 'sanctum')->deleteJson("/api/bookings/{$booking->id}")->assertOk();

        $entry->refresh();
        $this->assertNotNull($entry->offered_at);
        $this->assertNotNull($entry->offer_expires_at);
        $this->assertTrue($entry->offer_expires_at->equalTo($entry->offered_at->addMinutes(15)));
    }

    public function test_the_confirmation_window_is_capped_to_the_time_remaining_before_the_class_starts(): void
    {
        $gym = Gym::factory()->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 1, 'starts_at' => now()->addMinutes(10)]);
        $booking = Booking::factory()->for($class, 'gymClass')->create();
        $waiting = User::factory()->member()->for($gym)->create();
        $entry = WaitlistEntry::factory()->for($class, 'gymClass')->for($waiting, 'member')->create();

        $this->actingAs($booking->member, 'sanctum')->deleteJson("/api/bookings/{$booking->id}")->assertOk();

        $entry->refresh();
        $this->assertNotNull($entry->offered_at);
        $this->assertTrue($entry->offer_expires_at->equalTo($class->starts_at));
    }

    public function test_the_waitlist_stops_offering_the_spot_when_fewer_than_five_minutes_remain(): void
    {
        $gym = Gym::factory()->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 1, 'starts_at' => now()->addMinutes(4)]);
        $booking = Booking::factory()->for($class, 'gymClass')->create();
        $waiting = User::factory()->member()->for($gym)->create();
        $entry = WaitlistEntry::factory()->for($class, 'gymClass')->for($waiting, 'member')->create();

        $this->actingAs($booking->member, 'sanctum')->deleteJson("/api/bookings/{$booking->id}")->assertOk();

        $entry->refresh();
        $this->assertNull($entry->offered_at);
    }

    public function test_the_waitlist_still_offers_at_exactly_five_minutes_remaining(): void
    {
        $gym = Gym::factory()->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 1, 'starts_at' => now()->addMinutes(5)]);
        $booking = Booking::factory()->for($class, 'gymClass')->create();
        $waiting = User::factory()->member()->for($gym)->create();
        $entry = WaitlistEntry::factory()->for($class, 'gymClass')->for($waiting, 'member')->create();

        $this->actingAs($booking->member, 'sanctum')->deleteJson("/api/bookings/{$booking->id}")->assertOk();

        $entry->refresh();
        $this->assertNotNull($entry->offered_at);
    }

    public function test_the_offered_member_can_confirm_the_offer_and_receives_a_booking(): void
    {
        $gym = Gym::factory()->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 1]);
        $waiting = User::factory()->member()->for($gym)->create();
        $entry = WaitlistEntry::factory()->offered()->for($class, 'gymClass')->for($waiting, 'member')->create();

        $response = $this->actingAs($waiting, 'sanctum')->postJson("/api/waitlist-entries/{$entry->id}/confirm");

        $response->assertCreated();
        $this->assertDatabaseHas('bookings', [
            'class_id' => $class->id,
            'user_id' => $waiting->id,
            'cancelled_at' => null,
        ]);
        $this->assertNotNull($entry->fresh()->confirmed_at);
    }

    public function test_a_member_cannot_confirm_another_members_offer(): void
    {
        $gym = Gym::factory()->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 1]);
        $waiting = User::factory()->member()->for($gym)->create();
        $intruder = User::factory()->member()->for($gym)->create();
        $entry = WaitlistEntry::factory()->offered()->for($class, 'gymClass')->for($waiting, 'member')->create();

        $response = $this->actingAs($intruder, 'sanctum')->postJson("/api/waitlist-entries/{$entry->id}/confirm");

        $response->assertForbidden();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_confirming_an_offer_that_was_never_extended_is_rejected(): void
    {
        $gym = Gym::factory()->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 1]);
        Booking::factory()->for($class, 'gymClass')->create();
        $waiting = User::factory()->member()->for($gym)->create();
        $entry = WaitlistEntry::factory()->for($class, 'gymClass')->for($waiting, 'member')->create();

        $response = $this->actingAs($waiting, 'sanctum')->postJson("/api/waitlist-entries/{$entry->id}/confirm");

        $response->assertUnprocessable();
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_a_lapsed_offer_cascades_to_the_next_waitlisted_member(): void
    {
        $gym = Gym::factory()->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 1, 'starts_at' => now()->addHours(2)]);
        $firstInLine = User::factory()->member()->for($gym)->create();
        $secondInLine = User::factory()->member()->for($gym)->create();
        $firstEntry = WaitlistEntry::factory()
            ->for($class, 'gymClass')->for($firstInLine, 'member')
            ->state(['offered_at' => now()->subMinutes(16), 'offer_expires_at' => now()->subMinutes(1)])
            ->create();
        $secondEntry = WaitlistEntry::factory()->for($class, 'gymClass')->for($secondInLine, 'member')->create();

        $class->settleWaitlist();

        $this->assertNull($firstEntry->fresh()->confirmed_at);
        $secondEntry->refresh();
        $this->assertNotNull($secondEntry->offered_at);
        $this->assertNotNull($secondEntry->offer_expires_at);

        $this->actingAs($firstInLine, 'sanctum')->postJson("/api/waitlist-entries/{$firstEntry->id}/confirm")
            ->assertUnprocessable();
    }

    public function test_confirming_an_offer_at_exactly_its_expiry_instant_is_rejected(): void
    {
        $gym = Gym::factory()->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 1, 'starts_at' => now()->addHours(2)]);
        $waiting = User::factory()->member()->for($gym)->create();
        $expiresAt = now();
        $entry = WaitlistEntry::factory()
            ->for($class, 'gymClass')->for($waiting, 'member')
            ->state(['offered_at' => $expiresAt->copy()->subMinutes(15), 'offer_expires_at' => $expiresAt])
            ->create();

        $this->travelTo($expiresAt);

        $response = $this->actingAs($waiting, 'sanctum')->postJson("/api/waitlist-entries/{$entry->id}/confirm");

        $response->assertUnprocessable();
    }

    public function test_a_confirmed_offer_cannot_be_confirmed_again(): void
    {
        $gym = Gym::factory()->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 1]);
        $waiting = User::factory()->member()->for($gym)->create();
        $entry = WaitlistEntry::factory()->confirmed()->for($class, 'gymClass')->for($waiting, 'member')->create();

        $response = $this->actingAs($waiting, 'sanctum')->postJson("/api/waitlist-entries/{$entry->id}/confirm");

        $response->assertUnprocessable();
    }

    public function test_a_member_can_see_their_own_position_on_a_waitlist(): void
    {
        $gym = Gym::factory()->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 1]);
        Booking::factory()->for($class, 'gymClass')->create();
        $first = User::factory()->member()->for($gym)->create();
        $second = User::factory()->member()->for($gym)->create();
        WaitlistEntry::factory()->for($class, 'gymClass')->for($first, 'member')->create();
        WaitlistEntry::factory()->for($class, 'gymClass')->for($second, 'member')->create();

        $response = $this->actingAs($second, 'sanctum')->getJson('/api/waitlist-entries');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.0.position'));
    }

    public function test_a_members_waitlist_view_excludes_other_members_entries(): void
    {
        $gym = Gym::factory()->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 1]);
        Booking::factory()->for($class, 'gymClass')->create();
        $member = User::factory()->member()->for($gym)->create();
        $otherMember = User::factory()->member()->for($gym)->create();
        WaitlistEntry::factory()->for($class, 'gymClass')->for($otherMember, 'member')->create();

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/waitlist-entries');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_unauthenticated_request_cannot_view_waitlist_entries(): void
    {
        $this->getJson('/api/waitlist-entries')->assertUnauthorized();
    }

    public function test_a_new_member_booking_cannot_snipe_a_lapsed_offer_ahead_of_the_next_waitlisted_member(): void
    {
        $gym = Gym::factory()->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 2, 'starts_at' => now()->addHours(2)]);
        Booking::factory()->for($class, 'gymClass')->create();
        $lapsed = User::factory()->member()->for($gym)->create();
        $nextInLine = User::factory()->member()->for($gym)->create();
        WaitlistEntry::factory()
            ->for($class, 'gymClass')->for($lapsed, 'member')
            ->state(['offered_at' => now()->subMinutes(16), 'offer_expires_at' => now()->subMinutes(1)])
            ->create();
        $nextEntry = WaitlistEntry::factory()->for($class, 'gymClass')->for($nextInLine, 'member')->create();

        $newcomer = User::factory()->member()->for($gym)->create();
        $response = $this->actingAs($newcomer, 'sanctum')->postJson("/api/classes/{$class->id}/bookings");

        $response->assertCreated();
        $this->assertDatabaseCount('bookings', 1);
        $this->assertNotNull($nextEntry->fresh()->offered_at, 'The rightful next-in-line Member should have been offered the spot.');
        $this->assertDatabaseHas('waitlist_entries', ['user_id' => $newcomer->id, 'offered_at' => null]);
    }

    public function test_a_class_at_full_capacity_reports_zero_remaining_capacity_while_an_offer_is_outstanding(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $class = GymClass::factory()->for($gym)->create(['capacity' => 2]);
        Booking::factory()->for($class, 'gymClass')->create();
        WaitlistEntry::factory()->offered()->for($class, 'gymClass')->create();

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/classes');

        $listed = collect($response->json('data'))->firstWhere('id', $class->id);
        $this->assertSame(0, $listed['remaining_capacity']);
    }
}
