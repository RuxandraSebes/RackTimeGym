# RackTimeGym

A capacity and utilization app for independent gyms: live floor occupancy, class booking with waitlists, contended-equipment reservation, and an owner-facing analytics dashboard.

## Language

**Gym**:
The business operating a physical location on RackTime. Owns its own Members, Staff, Classes, Equipment Units, and configuration. Launching with one Gym; the model anticipates more than one existing later.

**Member**:
A person with a Membership who checks in at the door, books Classes, and reserves Equipment Units.
_Avoid_: User, customer ("customer," where it comes up in policy discussions like the Cancellation Window, means Member — RackTime's own paying customer is the Gym, not an individual Member).

**Staff**:
A Gym employee who can check Members in manually and manage class rosters, in addition to everything a Member can do.

**Owner**:
The role with access to the Owner Dashboard and Gym-level configuration (policies, thresholds).

**Membership**:
What grants a Member access to the Gym: one uniform type per Gym, with no tiers or drop-in passes. Active by default; 3 Strikes within a rolling 30 days moves it to Inactive, which blocks door Check-in, Class Booking, and Equipment Reservation alike. Strikes don't individually expire, but the count resets when Staff or the Owner manually reactivates the Membership — reactivation never happens automatically.
_Avoid_: Plan, subscription (a Membership is the thing itself, not a plan tier).

**Check-in**:
An event recording that a Member scanned a fixed QR code, created at the moment of the scan. Three kinds of fixed QR exist: the Door QR, a Class's QR, and an Equipment Unit's QR. Each Check-in is tied to one Member and one of these three targets.
_Avoid_: Scan (use Check-in for the recorded event; "scan" is just the physical action).

**Class**:
A scheduled session with fixed capacity that Members book in advance and attend by Check-in at its QR.

**Booking**:
A Member's reserved place in a Class.
_Avoid_: Reservation (Reservation is reserved for Equipment Units, to keep the two vocabularies distinct even though both mean "claimed a spot in advance").

**Waitlist**:
The ordered queue of Members for a full Class. When a Booking is cancelled, the Waitlist automatically offers the spot to the next Member in line.

**Strike**:
A mark recorded against a Member's Membership, from either of two triggers on a Class Booking: cancelling inside the Member's Cancellation Window, or never cancelling and never Checking in to the Class at all. Enough Strikes move the Membership to Inactive.

**Equipment Unit**:
One specific, individually identified piece of contended equipment (e.g. "Platform 3"), each with its own fixed QR code, bookable in 30-minute Reservations.

**Reservation**:
A Member's booked 30-minute slot on one Equipment Unit. Released automatically if the Member doesn't Check-in within 5 minutes of the slot's start.
_Avoid_: Booking (see Booking's _Avoid_ note — Booking is for Classes).

**Occupancy**:
How busy the Gym is right now, measured as a whole-floor headcount from Door Check-ins. Not broken into zones. Class and Equipment Check-ins do not add to it separately.

**Cancellation Window**:
A per-Member setting: how long before a Class starts a cancellation still counts as "in time" rather than late. Cancelling inside the window is one of the two Strike triggers. Defaults to the Gym's configured value when the Member joins; Staff or the Owner can override it for an individual Member afterward.

**Churn Signal**:
An Owner Dashboard flag on a Member whose most recent Door Check-in is older than the Gym's configured threshold (default 21 days). Informational only — it doesn't trigger any automated action.

**Utilization**:
Actual usage of a Class or Equipment Unit over a period, measured as Check-ins ÷ capacity (Class) or Check-ins ÷ bookable Reservation slots (Equipment Unit). Deliberately based on Check-ins, not Bookings or Reservations made, since a fully booked Class with heavy no-shows isn't actually utilized.

**No-show Rate**:
The fraction of Bookings that produce a Strike (via a late cancellation or a missed Check-in). Reported both per Class (which classes/time slots have a no-show problem) and per Member (feeds the same picture as their Strike count).
