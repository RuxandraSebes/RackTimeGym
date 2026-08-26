<?php

namespace App\Http\Controllers;

use App\Http\Resources\CheckInResource;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\GymClass;
use Illuminate\Http\Request;

class ClassCheckInController extends Controller
{
    public function store(Request $request, string $token): CheckInResource
    {
        $class = GymClass::where('qr_token', $token)->firstOrFail();

        abort_unless($request->user()->gym_id === $class->gym_id, 403, 'This Class QR belongs to a different Gym.');

        $hasBooking = Booking::where('class_id', $class->id)
            ->where('user_id', $request->user()->id)
            ->whereNull('cancelled_at')
            ->exists();
        abort_unless($hasBooking, 422, 'You do not have a Booking for this Class.');

        $checkIn = CheckIn::create([
            'user_id' => $request->user()->id,
            'gym_id' => $class->gym_id,
            'class_id' => $class->id,
        ]);

        return new CheckInResource($checkIn->load('member'));
    }
}
