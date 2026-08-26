<?php

namespace Tests\Feature;

use App\Models\EquipmentUnit;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_define_an_equipment_unit(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/equipment-units', [
            'name' => 'Platform 3',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('equipment_units', [
            'gym_id' => $gym->id,
            'name' => 'Platform 3',
        ]);
    }

    public function test_owner_can_define_an_equipment_unit(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->owner()->for($gym)->create();

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/equipment-units', [
            'name' => 'Rower 1',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('equipment_units', ['gym_id' => $gym->id, 'name' => 'Rower 1']);
    }

    public function test_member_cannot_define_an_equipment_unit(): void
    {
        $member = User::factory()->member()->create();

        $response = $this->actingAs($member, 'sanctum')->postJson('/api/equipment-units', [
            'name' => 'Should Fail',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('equipment_units', 0);
    }

    public function test_unauthenticated_request_cannot_define_an_equipment_unit(): void
    {
        $this->postJson('/api/equipment-units', ['name' => 'Should Fail'])->assertUnauthorized();
    }

    public function test_a_newly_defined_equipment_unit_gets_its_own_fixed_qr_code(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();

        $this->actingAs($staff, 'sanctum')->postJson('/api/equipment-units', ['name' => 'Platform 3']);

        $unit = EquipmentUnit::firstWhere('name', 'Platform 3');
        $this->assertNotNull($unit->qr_token);
    }

    public function test_staff_can_view_an_equipment_units_qr_token(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->getJson("/api/equipment-units/{$unit->id}/qr");

        $response->assertOk()->assertJson(['qr_token' => $unit->qr_token]);
    }

    public function test_member_cannot_view_an_equipment_units_qr_token(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();

        $this->actingAs($member, 'sanctum')->getJson("/api/equipment-units/{$unit->id}/qr")->assertForbidden();
    }

    public function test_viewing_the_qr_of_an_equipment_unit_from_another_gym_is_rejected(): void
    {
        $staff = User::factory()->staff()->create();
        $otherGymUnit = EquipmentUnit::factory()->create();

        $this->actingAs($staff, 'sanctum')->getJson("/api/equipment-units/{$otherGymUnit->id}/qr")->assertForbidden();
    }

    public function test_members_can_list_their_gyms_equipment_units(): void
    {
        $gym = Gym::factory()->create();
        $member = User::factory()->member()->for($gym)->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();
        $otherGymUnit = EquipmentUnit::factory()->create();

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/equipment-units');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($unit->id));
        $this->assertFalse($ids->contains($otherGymUnit->id));
    }
}
