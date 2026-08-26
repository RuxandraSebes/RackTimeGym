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
                'utilization' => $class->utilization,
            ])
            ->all();
    }

    /**
     * Equipment Units have no discrete occurrences to report per-instance, so
     * utilization is reported per hour-of-day bucket instead: each bucket's
     * "bookable Reservation slots" is the number of 30-minute slots starting
     * in that hour whose start actually falls within [from, to], so a period
     * that doesn't span whole days doesn't overcount hours outside its range.
     *
     * @return array<int, array<string, mixed>>
     */
    private function equipmentUtilization(int $gymId, Carbon $from, Carbon $to): array
    {
        $bookableSlotsByHour = $this->bookableSlotCountsByHour($from, $to);

        $checkInsByUnitAndHour = CheckIn::where('gym_id', $gymId)
            ->whereNotNull('equipment_unit_id')
            ->whereBetween('created_at', [$from, $to])
            ->get(['equipment_unit_id', 'created_at'])
            ->groupBy('equipment_unit_id')
            ->map(fn ($checkIns) => $checkIns->countBy(fn (CheckIn $checkIn) => $checkIn->created_at->hour));

        return EquipmentUnit::where('gym_id', $gymId)
            ->orderBy('name')
            ->get()
            ->flatMap(function (EquipmentUnit $unit) use ($checkInsByUnitAndHour, $bookableSlotsByHour) {
                $hourlyCheckIns = $checkInsByUnitAndHour->get($unit->id, collect());

                return collect(range(0, self::HOURS_PER_DAY - 1))->map(function (int $hour) use ($unit, $hourlyCheckIns, $bookableSlotsByHour) {
                    $checkIns = $hourlyCheckIns->get($hour, 0);
                    $bookableSlots = $bookableSlotsByHour[$hour];

                    return [
                        'equipment_unit_id' => $unit->id,
                        'name' => $unit->name,
                        'hour' => $hour,
                        'bookable_slots' => $bookableSlots,
                        'check_ins' => $checkIns,
                        'utilization' => $bookableSlots > 0 ? round($checkIns / $bookableSlots, 4) : 0.0,
                    ];
                });
            })
            ->all();
    }

    /**
     * How many 30-minute Reservation slots starting in each hour-of-day
     * (0-23) have their start within [from, to].
     *
     * @return array<int, int>
     */
    private function bookableSlotCountsByHour(Carbon $from, Carbon $to): array
    {
        $counts = array_fill(0, self::HOURS_PER_DAY, 0);

        $slotStart = $from->copy()->startOfDay();
        $periodEnd = $to->copy()->endOfDay();

        while ($slotStart->lessThanOrEqualTo($periodEnd)) {
            if ($slotStart->between($from, $to)) {
                $counts[$slotStart->hour]++;
            }

            $slotStart->addMinutes(Reservation::SLOT_MINUTES);
        }

        return $counts;
    }
}
