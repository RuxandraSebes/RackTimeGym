<?php

namespace App\Http\Controllers;

use App\Http\Requests\EquipmentUnitRequest;
use App\Http\Resources\EquipmentUnitResource;
use App\Models\EquipmentUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EquipmentUnitController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return EquipmentUnitResource::collection(
            EquipmentUnit::where('gym_id', $request->user()->gym_id)
                ->orderBy('name')
                ->get()
        );
    }

    public function store(EquipmentUnitRequest $request): EquipmentUnitResource
    {
        $unit = EquipmentUnit::create([
            'gym_id' => $request->user()->gym_id,
            'name' => $request->string('name'),
        ]);

        return new EquipmentUnitResource($unit);
    }

    public function showQr(Request $request, EquipmentUnit $equipmentUnit): JsonResponse
    {
        abort_unless($request->user()->gym_id === $equipmentUnit->gym_id, 403, 'This Equipment Unit belongs to a different Gym.');

        return response()->json(['qr_token' => $equipmentUnit->qr_token]);
    }
}
