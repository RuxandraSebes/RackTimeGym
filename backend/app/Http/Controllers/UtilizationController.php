<?php

namespace App\Http\Controllers;

use App\Http\Requests\UtilizationRequest;
use App\Models\CheckIn;
use App\Models\EquipmentUnit;
use App\Models\GymClass;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class UtilizationController extends Controller
{
    private const HOURS_PER_DAY = 24;

    public function show(UtilizationRequest $request): JsonResponse
    {
        $gymId = $request->user()->gym_id;
        $from = $request->date('from');
        $to = $request->date('to');

        return response()->json([
            'classes' => $this->classUtilization($gymId, $from, $to),
            'equipment_units' => $this->equipmentUtilization($gymId, $from, $to),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function classUtilization(int $gymId, Carbon $from, Carbon $to): array
    {
        return GymClass::where('gym_id', $gymId)
            ->whereNull('cancelled_at')
            ->whereBetween('starts_at', [$from, $to])
            ->withCount('checkIns')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (GymClass $class) => [
                'class_id' => $class->id,
                'name' => $class->name,
                'starts_at' => $class->starts_at->toIso8601String(),
                'hour' => $class->starts_at->hour,
                'capacity' => $class->capacity,
                'check_ins' => $class->check_ins_count,
                'utilization' => $class->capacity > 0 ? round($class->check_ins_count / $class->capacity, 4) : 0.0,
            ])
            ->all();
    }

    /**
     * Equipment Units have no discrete occurrences to report per-instance, so
     * utilization is reported per hour-of-day bucket instead: every day in
     * the period offers the same fixed number of 30-minute slots at a given
     * hour, which is what "bookable Reservation slots" counts against.
     *
     * @return array<int, array<string, mixed>>
     */
    private function equipmentUtilization(int $gymId, Carbon $from, Carbon $to): array
    {
        $periodDays = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $slotsPerHourPerDay = 60 / Reservation::SLOT_MINUTES;
        $bookableSlotsPerHour = $periodDays * $slotsPerHourPerDay;

        $checkInsByUnitAndHour = CheckIn::where('gym_id', $gymId)
            ->whereNotNull('equipment_unit_id')
            ->whereBetween('created_at', [$from, $to])
            ->get(['equipment_unit_id', 'created_at'])
            ->groupBy('equipment_unit_id')
            ->map(fn ($checkIns) => $checkIns->countBy(fn (CheckIn $checkIn) => $checkIn->created_at->hour));

        return EquipmentUnit::where('gym_id', $gymId)
            ->orderBy('name')
            ->get()
            ->flatMap(function (EquipmentUnit $unit) use ($checkInsByUnitAndHour, $bookableSlotsPerHour) {
                $hourlyCheckIns = $checkInsByUnitAndHour->get($unit->id, collect());

                return collect(range(0, self::HOURS_PER_DAY - 1))->map(fn (int $hour) => [
                    'equipment_unit_id' => $unit->id,
                    'name' => $unit->name,
                    'hour' => $hour,
                    'bookable_slots' => $bookableSlotsPerHour,
                    'check_ins' => $hourlyCheckIns->get($hour, 0),
                    'utilization' => round($hourlyCheckIns->get($hour, 0) / $bookableSlotsPerHour, 4),
                ]);
            })
            ->all();
    }
}
