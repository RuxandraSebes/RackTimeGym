<?php

namespace App\Http\Controllers;

use App\Models\CheckIn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OccupancyController extends Controller
{
    private const PRESENCE_WINDOW_MINUTES = 90;

    public function show(Request $request): JsonResponse
    {
        $count = CheckIn::query()
            ->where('gym_id', $request->user()->gym_id)
            ->whereNull('class_id')
            ->where('created_at', '>', now()->subMinutes(self::PRESENCE_WINDOW_MINUTES))
            ->distinct()
            ->count('user_id');

        return response()->json(['count' => $count]);
    }
}
