<?php

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\EquipmentUnit;
use App\Models\Gym;
use App\Models\GymClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UtilizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_the_utilization_dashboard(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/utilization?'.http_build_query([
            'from' => now()->subWeek()->toIso8601String(),
            'to' => now()->toIso8601String(),
        ]));

        $response->assertOk()->assertJsonStructure(['classes', 'equipment_units']);
    }

    public function test_owner_can_view_the_utilization_dashboard(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->owner()->for($gym)->create();

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/gym/utilization?'.http_build_query([
            'from' => now()->subWeek()->toIso8601String(),
            'to' => now()->toIso8601String(),
        ]));

        $response->assertOk();
    }

    public function test_member_cannot_view_the_utilization_dashboard(): void
    {
        $member = User::factory()->member()->create();

        $this->actingAs($member, 'sanctum')->getJson('/api/gym/utilization?'.http_build_query([
            'from' => now()->subWeek()->toIso8601String(),
            'to' => now()->toIso8601String(),
        ]))->assertForbidden();
    }

    public function test_unauthenticated_request_cannot_view_the_utilization_dashboard(): void
    {
        $this->getJson('/api/gym/utilization?'.http_build_query([
            'from' => now()->subWeek()->toIso8601String(),
            'to' => now()->toIso8601String(),
        ]))->assertUnauthorized();
    }

    public function test_requesting_utilization_without_a_period_is_rejected(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();

        $this->actingAs($staff, 'sanctum')->getJson('/api/gym/utilization')->assertUnprocessable();
    }

    public function test_requesting_utilization_with_to_before_from_is_rejected(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/utilization?'.http_build_query([
            'from' => now()->toIso8601String(),
            'to' => now()->subWeek()->toIso8601String(),
        ]));

        $response->assertUnprocessable();
    }

    public function test_class_utilization_is_check_ins_divided_by_capacity(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $startsAt = Carbon::parse('2026-08-20 09:00:00');
        $class = GymClass::factory()->for($gym)->create(['capacity' => 4, 'starts_at' => $startsAt]);
        CheckIn::factory()->count(3)->forClass($class)->create();

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/utilization?'.http_build_query([
            'from' => '2026-08-20T00:00:00+00:00',
            'to' => '2026-08-20T23:59:59+00:00',
        ]));

        $response->assertOk();
        $entry = collect($response->json('classes'))->firstWhere('class_id', $class->id);
        $this->assertSame(4, $entry['capacity']);
        $this->assertSame(3, $entry['check_ins']);
        $this->assertSame(9, $entry['hour']);
        $this->assertSame(0.75, $entry['utilization']);
    }

    public function test_class_utilization_excludes_classes_outside_the_period(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $outsideClass = GymClass::factory()->for($gym)->create(['starts_at' => Carbon::parse('2026-09-01 09:00:00')]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/utilization?'.http_build_query([
            'from' => '2026-08-20T00:00:00+00:00',
            'to' => '2026-08-20T23:59:59+00:00',
        ]));

        $ids = collect($response->json('classes'))->pluck('class_id');
        $this->assertFalse($ids->contains($outsideClass->id));
    }

    public function test_class_utilization_excludes_cancelled_classes(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $cancelledClass = GymClass::factory()->cancelled()->for($gym)->create(['starts_at' => Carbon::parse('2026-08-20 09:00:00')]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/utilization?'.http_build_query([
            'from' => '2026-08-20T00:00:00+00:00',
            'to' => '2026-08-20T23:59:59+00:00',
        ]));

        $ids = collect($response->json('classes'))->pluck('class_id');
        $this->assertFalse($ids->contains($cancelledClass->id));
    }

    public function test_class_utilization_is_scoped_to_the_requesting_staffs_gym(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $otherGymClass = GymClass::factory()->create(['starts_at' => Carbon::parse('2026-08-20 09:00:00')]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/utilization?'.http_build_query([
            'from' => '2026-08-20T00:00:00+00:00',
            'to' => '2026-08-20T23:59:59+00:00',
        ]));

        $ids = collect($response->json('classes'))->pluck('class_id');
        $this->assertFalse($ids->contains($otherGymClass->id));
    }

    public function test_equipment_utilization_is_check_ins_divided_by_bookable_slots_for_the_hour(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();

        CheckIn::factory()->for($member, 'member')->for($gym)->create([
            'equipment_unit_id' => $unit->id,
            'created_at' => Carbon::parse('2026-08-20 14:10:00'),
        ]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/utilization?'.http_build_query([
            'from' => '2026-08-20T00:00:00+00:00',
            'to' => '2026-08-20T23:59:59+00:00',
        ]));

        $response->assertOk();
        $entries = collect($response->json('equipment_units'))->where('equipment_unit_id', $unit->id);
        $this->assertCount(24, $entries);

        $busyHour = $entries->firstWhere('hour', 14);
        $this->assertSame(2, $busyHour['bookable_slots']);
        $this->assertSame(1, $busyHour['check_ins']);
        $this->assertSame(0.5, $busyHour['utilization']);

        $quietHour = $entries->firstWhere('hour', 15);
        $this->assertSame(0, $quietHour['check_ins']);
        $this->assertEquals(0.0, $quietHour['utilization']);
    }

    public function test_equipment_bookable_slots_exclude_hours_outside_a_partial_day_period(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        EquipmentUnit::factory()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/utilization?'.http_build_query([
            'from' => '2026-08-20T12:00:00+00:00',
            'to' => '2026-08-20T17:59:59+00:00',
        ]));

        $entries = collect($response->json('equipment_units'));
        $withinPeriod = $entries->whereBetween('hour', [12, 17]);
        $outsidePeriod = $entries->reject(fn (array $e) => $e['hour'] >= 12 && $e['hour'] <= 17);

        $this->assertTrue($withinPeriod->every(fn (array $e) => $e['bookable_slots'] === 2));
        $this->assertTrue($outsidePeriod->every(fn (array $e) => $e['bookable_slots'] === 0));
    }

    public function test_equipment_bookable_slots_are_correct_for_a_period_spanning_midnight(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        EquipmentUnit::factory()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/utilization?'.http_build_query([
            'from' => '2026-08-20T23:00:00+00:00',
            'to' => '2026-08-21T01:00:00+00:00',
        ]));

        $entries = collect($response->json('equipment_units'));
        $hour23 = $entries->firstWhere('hour', 23);
        $hour0 = $entries->firstWhere('hour', 0);
        $hour12 = $entries->firstWhere('hour', 12);

        $this->assertSame(2, $hour23['bookable_slots']);
        $this->assertSame(2, $hour0['bookable_slots']);
        $this->assertSame(0, $hour12['bookable_slots']);
    }

    public function test_equipment_bookable_slots_scale_with_the_number_of_days_in_the_period(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        EquipmentUnit::factory()->for($gym)->create();

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/utilization?'.http_build_query([
            'from' => '2026-08-20T00:00:00+00:00',
            'to' => '2026-08-21T23:59:59+00:00',
        ]));

        $entry = collect($response->json('equipment_units'))->first();
        $this->assertSame(4, $entry['bookable_slots']);
    }

    public function test_equipment_utilization_excludes_check_ins_outside_the_period(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->create();
        $unit = EquipmentUnit::factory()->for($gym)->create();

        CheckIn::factory()->for($member, 'member')->for($gym)->create([
            'equipment_unit_id' => $unit->id,
            'created_at' => Carbon::parse('2026-09-01 14:10:00'),
        ]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/utilization?'.http_build_query([
            'from' => '2026-08-20T00:00:00+00:00',
            'to' => '2026-08-20T23:59:59+00:00',
        ]));

        $entries = collect($response->json('equipment_units'))->where('equipment_unit_id', $unit->id);
        $this->assertTrue($entries->every(fn (array $entry) => $entry['check_ins'] === 0));
    }

    public function test_equipment_utilization_is_scoped_to_the_requesting_staffs_gym(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $otherGymUnit = EquipmentUnit::factory()->create();

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/utilization?'.http_build_query([
            'from' => '2026-08-20T00:00:00+00:00',
            'to' => '2026-08-20T23:59:59+00:00',
        ]));

        $ids = collect($response->json('equipment_units'))->pluck('equipment_unit_id');
        $this->assertFalse($ids->contains($otherGymUnit->id));
    }

    public function test_door_check_ins_do_not_count_toward_equipment_utilization(): void
    {
        $gym = Gym::factory()->create();
        $staff = User::factory()->staff()->for($gym)->create();
        $member = User::factory()->member()->for($gym)->create();
        EquipmentUnit::factory()->for($gym)->create();

        CheckIn::factory()->for($member, 'member')->for($gym)->create([
            'created_at' => Carbon::parse('2026-08-20 14:10:00'),
        ]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/gym/utilization?'.http_build_query([
            'from' => '2026-08-20T00:00:00+00:00',
            'to' => '2026-08-20T23:59:59+00:00',
        ]));

        $entries = collect($response->json('equipment_units'));
        $this->assertTrue($entries->every(fn (array $entry) => $entry['check_ins'] === 0));
    }
}
