<?php

namespace App\Http\Controllers;

use App\Enums\StrikeReason;
use App\Http\Resources\BookingResource;
use App\Http\Resources\WaitlistEntryResource;
use App\Models\Booking;
use App\Models\GymClass;
use App\Models\WaitlistEntry;
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

    public function store(Request $request, GymClass $class): BookingResource|WaitlistEntryResource
    {
        abort_unless($request->user()->gym_id === $class->gym_id, 403, 'This Class belongs to a different Gym.');
        abort_if(! $request->user()->hasActiveMembership(), 403, 'This Membership is inactive.');

        $result = DB::transaction(function () use ($request, $class) {
            $class = GymClass::whereKey($class->id)->lockForUpdate()->firstOrFail();

            abort_if($class->cancelled_at !== null, 422, 'This Class has been cancelled.');
            abort_if($class->starts_at->isPast(), 422, 'This Class has already started.');

            $alreadyBooked = Booking::where('class_id', $class->id)
                ->where('user_id', $request->user()->id)
                ->whereNull('cancelled_at')
                ->exists();
            abort_if($alreadyBooked, 422, 'You already have a Booking for this Class.');

            // Settle any lapsed offers first, so a stale one can't be mistaken for free
            // capacity and handed to a new Member ahead of whoever is next in line.
            $class->settleWaitlist();

            $activeBookings = Booking::where('class_id', $class->id)->whereNull('cancelled_at')->count();
            $outstandingOffers = $class->activeWaitlistOffers()->count();

            if ($activeBookings + $outstandingOffers >= $class->capacity) {
                $alreadyWaitlisted = WaitlistEntry::where('class_id', $class->id)
                    ->where('user_id', $request->user()->id)
                    ->pending()
                    ->exists();
                abort_if($alreadyWaitlisted, 422, 'You are already on the Waitlist for this Class.');

                return WaitlistEntry::create([
                    'class_id' => $class->id,
                    'user_id' => $request->user()->id,
                ]);
            }

            WaitlistEntry::where('class_id', $class->id)
                ->where('user_id', $request->user()->id)
                ->whereNull('confirmed_at')
                ->delete();

            return Booking::create([
                'class_id' => $class->id,
                'user_id' => $request->user()->id,
            ]);
        });

        if ($result instanceof WaitlistEntry) {
            return new WaitlistEntryResource($result->load('gymClass'));
        }

        return new BookingResource($result->load('gymClass'));
    }

    public function destroy(Request $request, Booking $booking): BookingResource
    {
        abort_unless($booking->user_id === $request->user()->id, 403, 'This Booking belongs to a different Member.');
        abort_if($booking->cancelled_at !== null, 422, 'This Booking has already been cancelled.');

        $member = $request->user();
        $class = $booking->gymClass;
        $isLateCancellation = now()->greaterThanOrEqualTo($class->cancellationDeadlineFor($member));

        $booking->update(['cancelled_at' => now()]);

        if ($isLateCancellation) {
            $member->recordStrike($booking, StrikeReason::LateCancellation);
        }

        $class->settleWaitlist();

        return new BookingResource($booking->load('gymClass'));
    }

    public function roster(Request $request, GymClass $class): AnonymousResourceCollection
    {
        abort_unless($request->user()->gym_id === $class->gym_id, 403, 'This Class belongs to a different Gym.');

        $class->settleNoShowStrikes();

        return BookingResource::collection(
            Booking::where('class_id', $class->id)
                ->whereNull('cancelled_at')
                ->with('member')
                ->get()
        );
    }
}
