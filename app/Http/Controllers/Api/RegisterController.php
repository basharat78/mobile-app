<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\CarrierRegistered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|unique:users,phone',
            'password' => 'required|min:8',
            'role' => 'required|in:carrier,dispatcher',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'company_name' => $request->company_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        if ($request->role === 'carrier') {
            $user->carrier()->create([
                'status' => 'pending',
            ]);

            // Notify all dispatchers
            $dispatchers = User::where('role', 'dispatcher')->get();
            Notification::send($dispatchers, new CarrierRegistered($user->name));
        }

        return response()->json([
            'success' => true,
            'user_id' => $user->id,
            'carrier_id' => $user->carrier?->id,
            'message' => 'User registered successfully on remote server.',
        ], 201);
    }
}
