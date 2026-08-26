<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\EquipmentUnit;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ReservationResource::collection(
            Reservation::where('user_id', $request->user()->id)
                ->active()
                ->with('equipmentUnit')
                ->orderBy('starts_at')
                ->get()
        );
    }

    public function store(ReservationRequest $request, EquipmentUnit $equipmentUnit): ReservationResource
    {
        abort_unless($request->user()->gym_id === $equipmentUnit->gym_id, 403, 'This Equipment Unit belongs to a different Gym.');

        $startsAt = $request->date('starts_at');

        $reservation = DB::transaction(function () use ($request, $equipmentUnit, $startsAt) {
            $unit = EquipmentUnit::whereKey($equipmentUnit->id)->lockForUpdate()->firstOrFail();

            $slotTaken = Reservation::where('equipment_unit_id', $unit->id)
                ->overlapping($startsAt)
                ->exists();
            abort_if($slotTaken, 422, 'This slot is already reserved.');

            return Reservation::create([
                'equipment_unit_id' => $unit->id,
                'user_id' => $request->user()->id,
                'starts_at' => $startsAt,
            ]);
        });

        return new ReservationResource($reservation->load('equipmentUnit'));
    }
}
