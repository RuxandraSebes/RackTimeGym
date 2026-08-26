<?php

namespace Tests\Feature;

use App\Models\EquipmentUnit;
use App\Models\Gym;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_reserve_an_available_slot(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();
        $startsAt = now()->addHour();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/equipment-units/{$unit->id}/reservations", [
            'starts_at' => $startsAt->toIso8601String(),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('reservations', [
            'equipment_unit_id' => $unit->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_member_cannot_reserve_a_slot_already_reserved_by_someone_else(): void
    {
        $gym = Gym::factory()->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();
        $startsAt = now()->addHour();
        Reservation::factory()->for($unit, 'equipmentUnit')->create(['starts_at' => $startsAt]);
        $member = User::factory()->member()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/equipment-units/{$unit->id}/reservations", [
            'starts_at' => $startsAt->toIso8601String(),
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_member_can_reserve_a_different_slot_on_the_same_unit(): void
    {
        $gym = Gym::factory()->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();
        Reservation::factory()->for($unit, 'equipmentUnit')->create(['starts_at' => now()->addHour()]);
        $member = User::factory()->member()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/equipment-units/{$unit->id}/reservations", [
            'starts_at' => now()->addHours(2)->toIso8601String(),
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_a_reservation_not_checked_into_within_5_minutes_is_released_and_the_slot_becomes_available_again(): void
    {
        $gym = Gym::factory()->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();
        $lapsedStart = now()->subMinutes(6);
        Reservation::factory()->for($unit, 'equipmentUnit')->create(['starts_at' => $lapsedStart]);
        $member = User::factory()->member()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/equipment-units/{$unit->id}/reservations", [
            'starts_at' => $lapsedStart->toIso8601String(),
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('reservations', 2);
        $this->assertDatabaseHas('reservations', [
            'equipment_unit_id' => $unit->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_a_confirmed_reservation_is_never_released(): void
    {
        $gym = Gym::factory()->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();
        $startsAt = now()->subMinutes(6);
        Reservation::factory()->for($unit, 'equipmentUnit')->confirmed()->create(['starts_at' => $startsAt]);
        $member = User::factory()->member()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/equipment-units/{$unit->id}/reservations", [
            'starts_at' => $startsAt->toIso8601String(),
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_reserving_a_slot_on_an_equipment_unit_from_another_gym_is_rejected(): void
    {
        $member = User::factory()->member()->create();
        $otherGymUnit = EquipmentUnit::factory()->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/equipment-units/{$otherGymUnit->id}/reservations", [
            'starts_at' => now()->addHour()->toIso8601String(),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_staff_cannot_reserve_an_equipment_slot_for_themselves(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/equipment-units/{$unit->id}/reservations", [
            'starts_at' => now()->addHour()->toIso8601String(),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_unauthenticated_request_cannot_reserve_a_slot(): void
    {
        $unit = EquipmentUnit::factory()->create();

        $this->postJson("/api/equipment-units/{$unit->id}/reservations", [
            'starts_at' => now()->addHour()->toIso8601String(),
        ])->assertUnauthorized();
    }

    public function test_member_can_see_their_own_active_reservations(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();
        $reservation = Reservation::factory()->for($unit, 'equipmentUnit')->for($member, 'member')->create(['starts_at' => now()->addHour()]);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/reservations');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($reservation->id));
    }

    public function test_own_reservations_excludes_released_reservations(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();
        $released = Reservation::factory()->for($unit, 'equipmentUnit')->for($member, 'member')->released()->create();

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/reservations');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($released->id));
    }

    public function test_own_reservations_only_includes_the_authenticated_members_reservations(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $otherMember = User::factory()->member()->for($gym)->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();
        $ownReservation = Reservation::factory()->for($unit, 'equipmentUnit')->for($member, 'member')->create(['starts_at' => now()->addHour()]);
        $otherReservation = Reservation::factory()->for($unit, 'equipmentUnit')->for($otherMember, 'member')->create(['starts_at' => now()->addHours(2)]);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/reservations');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($ownReservation->id));
        $this->assertFalse($ids->contains($otherReservation->id));
    }
}
