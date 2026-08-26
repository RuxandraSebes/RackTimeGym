<?php

namespace App\Http\Controllers;

use App\Http\Resources\CheckInResource;
use App\Models\CheckIn;
use App\Models\EquipmentUnit;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EquipmentCheckInController extends Controller
{
    public function store(Request $request, string $token): CheckInResource
    {
        $unit = EquipmentUnit::where('qr_token', $token)->firstOrFail();

        abort_unless($request->user()->gym_id === $unit->gym_id, 403, 'This Equipment Unit belongs to a different Gym.');

        DB::transaction(function () use ($request, $unit) {
            $reservation = Reservation::where('equipment_unit_id', $unit->id)
                ->where('user_id', $request->user()->id)
                ->whereNull('confirmed_at')
                ->where('starts_at', '<=', now())
                ->where('starts_at', '>', now()->subMinutes(Reservation::CHECKIN_GRACE_MINUTES))
                ->lockForUpdate()
                ->first();

            abort_if($reservation === null, 422, 'You have no active Reservation for this Equipment Unit right now.');

            $reservation->update(['confirmed_at' => now()]);
        });

        $checkIn = CheckIn::create([
            'user_id' => $request->user()->id,
            'gym_id' => $unit->gym_id,
            'equipment_unit_id' => $unit->id,
        ]);

        return new CheckInResource($checkIn->load(['member', 'equipmentUnit']));
    }
}
