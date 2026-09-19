<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        $database = 'ok';

        try {
            DB::connection()->select('select 1');
        } catch (\Throwable) {
            $database = 'error';
        }

        $payload = [
            'status' => $database === 'ok' ? 'ok' : 'error',
            'app' => config('app.name'),
            'version' => config('app.version', '1.0.0'),
            'time' => now()->toIso8601String(),
            'database' => $database,
        ];

        return response()->json($payload, $database === 'ok' ? 200 : 503);
    }
}
