<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\GymClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return BookingResource::collection(
            Booking::where('user_id', $request->user()->id)
                ->whereNull('cancelled_at')
                ->whereHas('gymClass', fn ($query) => $query->whereNull('cancelled_at')->where('starts_at', '>', now()))
                ->with('gymClass')
                ->get()
        );
    }

    public function store(Request $request, GymClass $class): BookingResource
    {
        abort_unless($request->user()->gym_id === $class->gym_id, 403, 'This Class belongs to a different Gym.');

        $booking = DB::transaction(function () use ($request, $class) {
            $class = GymClass::whereKey($class->id)->lockForUpdate()->firstOrFail();

            abort_if($class->cancelled_at !== null, 422, 'This Class has been cancelled.');
            abort_if($class->starts_at->isPast(), 422, 'This Class has already started.');

            $alreadyBooked = Booking::where('class_id', $class->id)
                ->where('user_id', $request->user()->id)
                ->whereNull('cancelled_at')
                ->exists();
            abort_if($alreadyBooked, 422, 'You already have a Booking for this Class.');

            $activeBookings = Booking::where('class_id', $class->id)->whereNull('cancelled_at')->count();
            abort_if($activeBookings >= $class->capacity, 422, 'This Class is at full capacity.');

            return Booking::create([
                'class_id' => $class->id,
                'user_id' => $request->user()->id,
            ]);
        });

        return new BookingResource($booking->load('gymClass'));
    }

    public function destroy(Request $request, Booking $booking): BookingResource
    {
        abort_unless($booking->user_id === $request->user()->id, 403, 'This Booking belongs to a different Member.');
        abort_if($booking->cancelled_at !== null, 422, 'This Booking has already been cancelled.');

        $booking->update(['cancelled_at' => now()]);

        return new BookingResource($booking->load('gymClass'));
    }

    public function roster(Request $request, GymClass $class): AnonymousResourceCollection
    {
        abort_unless($request->user()->gym_id === $class->gym_id, 403, 'This Class belongs to a different Gym.');

        return BookingResource::collection(
            Booking::where('class_id', $class->id)
                ->whereNull('cancelled_at')
                ->with('member')
                ->get()
        );
    }
}
