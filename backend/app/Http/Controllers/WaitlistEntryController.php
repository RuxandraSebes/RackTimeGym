<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Http\Resources\WaitlistEntryResource;
use App\Models\Booking;
use App\Models\GymClass;
use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class WaitlistEntryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $classIds = WaitlistEntry::where('user_id', $request->user()->id)
            ->whereNull('confirmed_at')
            ->pluck('class_id')
            ->unique();

        GymClass::whereIn('id', $classIds)->get()->each(fn (GymClass $class) => $class->settleWaitlist());

        return WaitlistEntryResource::collection(
            WaitlistEntry::where('user_id', $request->user()->id)
                ->pending()
                ->with('gymClass')
                ->orderBy('id')
                ->get()
        );
    }

    public function confirm(Request $request, WaitlistEntry $waitlistEntry): BookingResource
    {
        abort_unless($waitlistEntry->user_id === $request->user()->id, 403, 'This Waitlist entry belongs to a different Member.');

        $waitlistEntry->gymClass->settleWaitlist();

        $booking = DB::transaction(function () use ($waitlistEntry) {
            $entry = WaitlistEntry::whereKey($waitlistEntry->id)->lockForUpdate()->firstOrFail();

            abort_if($entry->confirmed_at !== null, 422, 'This Waitlist offer has already been confirmed.');
            abort_if($entry->offered_at === null || $entry->offer_expires_at <= now(), 422, 'This Waitlist offer is no longer available.');

            $entry->update(['confirmed_at' => now()]);

            return Booking::create([
                'class_id' => $entry->class_id,
                'user_id' => $entry->user_id,
            ]);
        });

        return new BookingResource($booking->load('gymClass'));
    }
}
