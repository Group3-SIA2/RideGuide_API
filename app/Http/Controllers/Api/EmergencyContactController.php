<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commuter;
use App\Models\Driver;
use App\Models\EmergencyContact;
use App\Models\UsersEmergencyContact;
use App\Support\InputValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmergencyContactController extends Controller
{
    /* Endpoint: /api/emergency-contacts/
       Method: POST
       Body Params:
         - contact_name (string, required): Name of the emergency contact
         - contact_phone_number (string, required): Phone number of the emergency contact
         - contact_relationship (string, optional): Relationship of the emergency contact to the user
       Response:
         - message (string)
         - data (object) – details of the created emergency contact if successful
    */
    public function addEmergencyContact(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'contact_name' => InputValidation::nameRequiredRules(),
            'contact_phone_number' => 'required|string|max:20',
            'contact_relationship' => InputValidation::safeStringRules(required: false, max: 255),
        ]);

        // Can only have one emergency contact per driver and commuter role, so check if one already exists
        $checkCommuter = Commuter::where('user_id', auth()->id())->first();
        if ($checkCommuter && $checkCommuter->emergency_contact_id) {
            return response()->json(['error' => 'You already have an emergency contact associated with your commuter profile. Please update or delete the existing contact before adding a new one.'], 400);
        }

        $checkDriver = Driver::where('user_id', auth()->id())->first();
        if ($checkDriver && $checkDriver->emergency_contact_id) {
            return response()->json(['error' => 'You already have an emergency contact associated with your driver profile. Please update or delete the existing contact before adding a new one.'], 400);
        }

        $emergencyContact = EmergencyContact::create([
            'user_id' => auth()->id(),
            'contact_name' => $validatedData['contact_name'],
            'contact_phone_number' => $validatedData['contact_phone_number'],
            'contact_relationship' => $validatedData['contact_relationship'],
        ]);

        // insert to UsersEmergencyContact pivot table
        UsersEmergencyContact::create([
            'user_id' => auth()->id(),
            'emergency_contact_id' => $emergencyContact->id,
        ]);

        $driver = Driver::where('user_id', auth()->id())->first();
        if ($driver) {
            $driver->emergency_contact_id = $emergencyContact->id;
            $driver->save();
        }

        return response()->json([
            'message' => 'Emergency contact added successfully.',
            'data' => $emergencyContact,
        ], 201);
    }

    /* Endpoint: /api/emergency-contacts/{id}
       Method: PUT
       URL Params:
         - id (string, required): ID of the emergency contact to update
       Body Params:
         - contact_name (string, optional): Name of the emergency contact
         - contact_phone_number (string, optional): Phone number of the emergency contact
         - contact_relationship (string, optional): Relationship of the emergency contact to the user
       Response:
         - message (string)
         - data (object) – details of the updated emergency contact if successful
    */

    public function updateEmergencyContact(Request $request, $id): JsonResponse
    {
        $emergencyContact = EmergencyContact::where('id', $id)->where('user_id', auth()->id())->first();

        if (! $emergencyContact) {
            return response()->json(['error' => 'Emergency contact not found.'], 404);
        }

        $validatedData = $request->validate([
            'contact_name' => ['sometimes', ...InputValidation::nameRequiredRules()],
            'contact_phone_number' => 'sometimes|required|string|max:20',
            'contact_relationship' => InputValidation::safeStringRules(required: false, max: 255),
        ]);

        $emergencyContact->update($validatedData);

        return response()->json([
            'message' => 'Emergency contact updated successfully.',
            'data' => $emergencyContact,
        ], 200);
    }

    public function getEmergencyContacts(): JsonResponse
    {
        $emergencyContacts = EmergencyContact::where('user_id', auth()->id())->get();

        return response()->json([
            'data' => $emergencyContacts,
        ], 200);
    }

    public function softDeleteEmergencyContact($id): JsonResponse
    {
        $emergencyContact = EmergencyContact::where('id', $id)->where('user_id', auth()->id())->first();

        if (! $emergencyContact) {
            return response()->json(['error' => 'Emergency contact not found.'], 404);
        }

        $emergencyContact->delete();

        return response()->json([
            'message' => 'Emergency contact deleted successfully.',
        ], 200);
    }
}
