<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $database = 'connected';
        } catch (Throwable) {
            $database = 'disconnected';
        }

        return response()->json([
            'status' => 'ok',
            'database' => $database,
        ], $database === 'connected' ? 200 : 503);
    }
}
