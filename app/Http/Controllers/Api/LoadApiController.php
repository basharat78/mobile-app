<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Load;
use App\Models\User;
use Illuminate\Http\Request;

class LoadApiController extends Controller
{
    /**
     * Get available loads for a specific carrier (v35 Targeted)
     */
    public function getAvailable($email)
    {
        $user = User::with('carrier')->where('email', $email)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => "User with email {$email} not found on cloud server."], 404);
        }

        if (!$user->carrier) {
            return response()->json(['success' => false, 'message' => "Carrier profile for user {$user->name} not found on cloud server."], 404);
        }

        // Return loads assigned specifically to this carrier ID
        $loads = Load::where('status', 'available')
            ->where('carrier_id', $user->carrier->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => "Found " . $loads->count() . " loads for " . $user->name,
            'loads' => $loads
        ]);
    }
}
