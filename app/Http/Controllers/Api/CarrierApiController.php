<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CarrierApiController extends Controller
{
    /**
     * Get the current status of a carrier (Pending/Approved/Rejected)
     */
    public function getStatus($userId)
    {
        $user = User::with('carrier')->find($userId);
        
        if (!$user || !$user->carrier) {
            return response()->json(['success' => false, 'message' => 'Carrier not found'], 404);
        }

        return response()->json([
            'success' => true,
            'status' => $user->carrier->status,
            'remote_id' => $user->carrier->id
        ]);
    }

    /**
     * Sync carrier preferences from mobile to central server
     */
    public function syncPreferences(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'carrier_id' => 'required|integer',
            'preferred_origin' => 'nullable|string',
            'preferred_destination' => 'nullable|string',
            'preferred_equipment' => 'nullable|string',
            'min_rate' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $carrier = Carrier::find($request->carrier_id);
        
        if (!$carrier) {
            return response()->json(['success' => false, 'message' => 'Carrier profile not found'], 404);
        }

        $carrier->update([
            'preferred_origin' => $request->preferred_origin,
            'preferred_destination' => $request->preferred_destination,
            'preferred_equipment' => $request->preferred_equipment,
            'min_rate' => $request->min_rate,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preferences synced to central server.'
        ]);
    }
}
