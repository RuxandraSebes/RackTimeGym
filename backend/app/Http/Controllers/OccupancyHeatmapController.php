<?php

namespace App\Http\Controllers;

use App\Models\CheckIn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OccupancyHeatmapController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $counts = array_fill(0, 7, array_fill(0, 24, 0));

        CheckIn::query()
            ->where('gym_id', $request->user()->gym_id)
            ->whereNull('class_id')
            ->get(['created_at'])
            ->each(function (CheckIn $checkIn) use (&$counts) {
                $counts[$checkIn->created_at->dayOfWeek][$checkIn->created_at->hour]++;
            });

        $data = [];
        $busiest = null;
        $quietest = null;

        foreach ($counts as $dayOfWeek => $hours) {
            foreach ($hours as $hour => $count) {
                $bucket = ['day_of_week' => $dayOfWeek, 'hour' => $hour, 'count' => $count];
                $data[] = $bucket;

                if ($count > 0 && ($busiest === null || $count > $busiest['count'])) {
                    $busiest = $bucket;
                }

                if ($quietest === null || $count < $quietest['count']) {
                    $quietest = $bucket;
                }
            }
        }

        if ($busiest === null) {
            $quietest = null;
        }

        return response()->json([
            'data' => $data,
            'busiest' => $busiest,
            'quietest' => $quietest,
        ]);
    }
}
