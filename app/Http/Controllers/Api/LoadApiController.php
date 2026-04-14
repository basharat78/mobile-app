<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Load;
use App\Models\User;
use Illuminate\Http\Request;

class LoadApiController extends Controller
{
    /**
     * Get available loads for a specific carrier (v41 High-Fidelity)
     */
    public function getAvailable($email)
    {
        $user = User::with('carrier')->where('email', $email)->first();

        if (!$user || !$user->carrier) {
            return response()->json(['success' => false, 'message' => "Identity not found."], 404);
        }

        // Return loads targeted to this carrier, INCLUDING the status of their requests
        $loads = Load::with(['requests' => function($q) use ($user) {
                $q->where('carrier_id', $user->carrier->id);
            }])
            ->where('carrier_id', $user->carrier->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'loads' => $loads
        ]);
    }

    /**
     * Post a load request from mobile (v44 High-Reliability Bid)
     */
    public function postRequest(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'load_id' => 'required|integer',
        ]);

        $email = trim($request->email);
        $user = User::with('carrier')->where('email', $email)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => "User {$email} not found on cloud server."], 404);
        }

        if (!$user->carrier) {
            return response()->json(['success' => false, 'message' => "Carrier profile for {$user->name} not found on cloud server."], 404);
        }

        $load = Load::find($request->load_id);
        if (!$load) {
            return response()->json(['success' => false, 'message' => "Load ID {$request->load_id} not found on cloud server."], 404);
        }

        // Create or update bid record on Hostinger
        $bid = \App\Models\LoadRequest::updateOrCreate(
            ['load_id' => $load->id, 'carrier_id' => $user->carrier->id],
            ['status' => 'pending']
        );

        return response()->json([
            'success' => true, 
            'message' => 'Bid successfully recorded on cloud server.',
            'bid_id' => $bid->id
        ]);
    }
}
