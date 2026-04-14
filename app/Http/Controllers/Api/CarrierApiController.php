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
     * Updated (v51): Now includes document statuses for real-time doc sync
     */
    public function getStatus($email)
    {
        $user = User::with(['carrier', 'carrier.documents'])->where('email', $email)->first();
        
        if (!$user || !$user->carrier) {
            return response()->json(['success' => false, 'message' => 'Carrier not found on cloud server.'], 404);
        }

        return response()->json([
            'success' => true,
            'status' => $user->carrier->status,
            'remote_id' => $user->carrier->id,
            'documents' => $user->carrier->documents->map(fn($d) => [
                'type' => $d->type,
                'status' => $d->status,
            ])->values(),
        ]);
    }

    /**
     * Lookup a carrier ID by email (Self-healing identity for v31)
     */
    public function lookup(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();
        
        if (!$user || $user->role !== 'carrier') {
            return response()->json(['success' => false, 'message' => 'Carrier account not found on cloud server.'], 404);
        }

        return response()->json([
            'success' => true,
            'carrier_id' => $user->carrier->id,
            'status' => $user->carrier->status
        ]);
    }

    /**
     * Sync carrier preferences from mobile to central server
     * Updated (v34): Now uses Email for 100% reliable matching
     */
    public function syncPreferences(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'preferred_origin' => 'nullable|string',
            'preferred_destination' => 'nullable|string',
            'preferred_equipment' => 'nullable|string',
            'min_rate' => 'nullable|numeric',
            'signature_path' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();
        
        if (!$user || !$user->carrier) {
            return response()->json(['success' => false, 'message' => 'Carrier profile not found on cloud server.'], 404);
        }

        $user->carrier->update([
            'preferred_origin' => $request->preferred_origin,
            'preferred_destination' => $request->preferred_destination,
            'preferred_equipment' => $request->preferred_equipment,
            'min_rate' => $request->min_rate,
            'signature_path' => $request->signature_path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preferences and signature synced to cloud via email.'
        ]);
    }
    /**
     * Authenticate a carrier from cloud (v42 Identity Recovery)
     */
    public function authenticate(Request $request)
    {
        $request->validate([
            'login' => 'required',
            'password' => 'required',
        ]);

        $fieldType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        
        $user = User::with('carrier')->where($fieldType, $request->login)->first();

        if (!$user || !\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Cloud credentials invalid.'], 401);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'company_name' => $user->company_name,
            ],
            'carrier' => [
                'id' => $user->carrier->id,
                'status' => $user->carrier->status,
            ]
        ]);
    }
}
