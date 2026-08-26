<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManualCheckInRequest;
use App\Http\Resources\CheckInResource;
use App\Models\CheckIn;
use App\Models\Gym;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoorCheckInController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'door_qr_token' => $request->user()->gym->door_qr_token,
        ]);
    }

    public function store(Request $request, string $token): CheckInResource
    {
        $gym = Gym::where('door_qr_token', $token)->firstOrFail();

        abort_unless($request->user()->gym_id === $gym->id, 403, 'This Door QR belongs to a different Gym.');

        return $this->recordCheckIn($request->user()->id, $gym->id);
    }

    public function storeManual(StoreManualCheckInRequest $request): CheckInResource
    {
        return $this->recordCheckIn($request->integer('user_id'), $request->user()->gym_id, $request->user()->id);
    }

    private function recordCheckIn(int $userId, int $gymId, ?int $recordedById = null): CheckInResource
    {
        $checkIn = CheckIn::create([
            'user_id' => $userId,
            'gym_id' => $gymId,
            'recorded_by_id' => $recordedById,
        ]);

        return new CheckInResource($checkIn->load('member'));
    }
}
