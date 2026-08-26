<?php

namespace Tests\Feature;

use App\Models\EquipmentUnit;
use App\Models\Gym;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanning_the_equipment_qr_within_the_grace_period_records_a_check_in_and_confirms_the_reservation(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();
        $reservation = Reservation::factory()->for($unit, 'equipmentUnit')->for($member, 'member')->create(['starts_at' => now()->subMinutes(2)]);

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/checkins/equipment/{$unit->qr_token}");

        $response->assertCreated();
        $this->assertDatabaseHas('check_ins', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'equipment_unit_id' => $unit->id,
        ]);
        $this->assertNotNull($reservation->fresh()->confirmed_at);
    }

    public function test_scanning_exactly_at_the_slots_start_confirms_the_reservation(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();
        $reservation = Reservation::factory()->for($unit, 'equipmentUnit')->for($member, 'member')->create(['starts_at' => now()]);

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/checkins/equipment/{$unit->qr_token}");

        $response->assertCreated();
        $this->assertNotNull($reservation->fresh()->confirmed_at);
    }

    public function test_scanning_after_the_5_minute_grace_period_is_rejected_and_leaves_the_reservation_unconfirmed(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();
        $reservation = Reservation::factory()->for($unit, 'equipmentUnit')->for($member, 'member')->released()->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/checkins/equipment/{$unit->qr_token}");

        $response->assertUnprocessable();
        $this->assertNull($reservation->fresh()->confirmed_at);
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_scanning_without_an_active_reservation_is_rejected(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/checkins/equipment/{$unit->qr_token}");

        $response->assertUnprocessable();
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_scanning_someone_elses_reservation_slot_is_rejected(): void
    {
        $gym = Gym::factory()->create();
        $reservedBy = User::factory()->member()->for($gym)->create();
        $scanner = User::factory()->member()->for($gym)->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();
        Reservation::factory()->for($unit, 'equipmentUnit')->for($reservedBy, 'member')->create(['starts_at' => now()]);

        $response = $this->actingAs($scanner, 'sanctum')->postJson("/api/checkins/equipment/{$unit->qr_token}");

        $response->assertUnprocessable();
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_scanning_an_equipment_qr_from_another_gym_is_rejected(): void
    {
        $ownGym = Gym::factory()->create();
        $member = User::factory()->member()->for($ownGym)->create();
        $otherGymUnit = EquipmentUnit::factory()->create();

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/checkins/equipment/{$otherGymUnit->qr_token}");

        $response->assertForbidden();
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_scanning_an_unknown_equipment_qr_token_returns_not_found(): void
    {
        $member = User::factory()->member()->create();

        $this->actingAs($member, 'sanctum')->postJson('/api/checkins/equipment/not-a-real-token')->assertNotFound();
    }

    public function test_staff_cannot_scan_an_equipment_qr_themselves(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/checkins/equipment/{$unit->qr_token}");

        $response->assertForbidden();
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_unauthenticated_scan_is_rejected(): void
    {
        $unit = EquipmentUnit::factory()->create();

        $this->postJson("/api/checkins/equipment/{$unit->qr_token}")->assertUnauthorized();
    }
}
