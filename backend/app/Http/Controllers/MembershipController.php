<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Http\Requests\UpdateCancellationWindowRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function reactivate(Request $request, User $member): UserResource
    {
        $this->assertOwnGymMember($request, $member);
        abort_if($member->membership_status === MembershipStatus::Active, 422, 'This Membership is already active.');

        $member->reactivateMembership();

        return new UserResource($member);
    }

    public function updateCancellationWindow(UpdateCancellationWindowRequest $request, User $member): UserResource
    {
        $this->assertOwnGymMember($request, $member);

        $member->update([
            'cancellation_window_minutes' => $request->integer('cancellation_window_minutes'),
        ]);

        return new UserResource($member);
    }

    private function assertOwnGymMember(Request $request, User $member): void
    {
        abort_unless($request->user()->gym_id === $member->gym_id, 403, 'This Member belongs to a different Gym.');
        abort_unless($member->role === Role::Member, 422, 'Only a Member has a Membership.');
    }
}
