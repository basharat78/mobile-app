<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarrierDocument;
use App\Models\User;
use App\Notifications\DocumentUploaded;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'carrier_id' => 'required|integer',
            'type' => 'required|string|in:mc_authority,insurance,w9',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'user_name' => 'required|string',
            'dispatcher_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Store the uploaded file
        $file = $request->file('file');
        $filename = $request->type . '_' . time() . '.' . $file->getClientOriginalExtension();
        $destination = 'documents/' . $filename;
        $file->storeAs('public/documents', $filename);

        // Create the document record
        $document = CarrierDocument::create([
            'carrier_id' => $request->carrier_id,
            'type' => $request->type,
            'file_path' => $destination,
            'status' => 'pending',
        ]);

        // Notify dispatcher(s)
        if ($request->dispatcher_id) {
            $dispatcher = User::find($request->dispatcher_id);
            if ($dispatcher) {
                $dispatcher->notify(new DocumentUploaded($request->user_name, $request->type));
            }
        } else {
            $dispatchers = User::where('role', 'dispatcher')->get();
            Notification::send($dispatchers, new DocumentUploaded($request->user_name, $request->type));
        }

        return response()->json([
            'success' => true,
            'document_id' => $document->id,
            'message' => 'Document uploaded and synced to remote server.',
        ], 201);
    }
}
