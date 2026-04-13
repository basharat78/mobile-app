<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    public function check()
    {
        $dbOk = false;
        $dbError = null;

        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            $dbOk = true;
        } catch (\Exception $e) {
            $dbError = $e->getMessage();
        }

        return response()->json([
            'status' => 'ok',
            'database' => $dbOk ? 'connected' : 'error',
            'database_error' => $dbError,
            'timestamp' => now()->toIso8601String(),
            'php_version' => PHP_VERSION,
        ]);
    }
}
