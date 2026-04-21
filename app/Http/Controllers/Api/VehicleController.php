<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlateNumber;
use App\Models\vehicle;
use App\Models\vehicleImage;
use App\Models\vehicleType;
use App\Support\InputValidation;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    /* 
        Endpoint: /api/vehicles
        Body Params: vehicle_type, description, image_front, image_back, image_left, image_right, plate_number
    */
    public function addVehicle(Request $request): JsonResponse
    {
        if (! $request->isMethod('POST')) {
            return response()->json(['message' => 'Method Not Allowed'], 405);
        }

        if (! $request->user() || ! $request->user()->hasRole('driver')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'vehicle_type' => InputValidation::safeStringRules(required: true, max: 255),
            'description' => InputValidation::safeStringRules(required: true, max: 500),
            'image_front' => ['required', ...MediaStorage::imageValidationRules()],
            'image_back' => ['required', ...MediaStorage::imageValidationRules()],
            'image_left' => ['required', ...MediaStorage::imageValidationRules()],
            'image_right' => ['required', ...MediaStorage::imageValidationRules()],
            'plate_number' => 'required|string|unique:plate_number,plate_number',
        ]);

        $vehicleImage = vehicleImage::create([
            'image_front' => MediaStorage::putFile('vehicle_images', $request->file('image_front')),
            'image_back' => MediaStorage::putFile('vehicle_images', $request->file('image_back')),
            'image_left' => MediaStorage::putFile('vehicle_images', $request->file('image_left')),
            'image_right' => MediaStorage::putFile('vehicle_images', $request->file('image_right')),
        ]);

        $vehicleType = vehicleType::create([
            'vehicle_type' => $request->input('vehicle_type'),
            'description' => $request->input('description'),
            'image_id' => $vehicleImage->id,
        ]);

        $plateNumber = PlateNumber::create([
            'plate_number' => $request->input('plate_number'),
        ]);

        $driver = $request->user()->driver;

        if (! $driver) {
            return response()->json(['message' => 'Driver profile not found'], 422);
        }

        $vehicle = vehicle::create([
            'driver_id' => $driver->id,
            'vehicle_type_id' => $vehicleType->id,
            'plate_number_id' => $plateNumber->id,
            'status' => 'inactive', // default status
            'verification_status' => 'pending', // default verification status
        ]);

        $vehicleDetails = vehicle::with(['vehicleType', 'vehicleType.vehicleImage', 'plateNumber'])
            ->where('id', $vehicle->id)
            ->first();

        $this->appendVehicleImageUrls($vehicleDetails);

        return response()->json(['message' => 'Vehicle added successfully', 'vehicle' => $vehicleDetails], 201);
    }

    /*
        Endpoint: /api/vehicles
        Query Params (admin only): status (active, inactive, suspended)
    */
    public function listVehicledPerDriver(Request $request)
    {
        if (! $request->user() || ! $request->user()->hasRole('driver')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $driver = $request->user()->driver;

        if (! $driver) {
            return response()->json(['message' => 'Driver profile not found'], 422);
        }

        $vehicles = vehicle::with(['vehicleType', 'vehicleType.vehicleImage'])
            ->where('driver_id', $driver->id)
            ->get();

        $vehicles->each(fn ($vehicle) => $this->appendVehicleImageUrls($vehicle));

        return response()->json(['vehicles' => $vehicles], 200);
    }

    /* 
        Endpoint: /api/vehicles/{id}
        URL Params: id
        Body Params: vehicle_type, description, image_front, image_back, image_left, image_right, plate_number
    */
    public function updateVehicle(Request $request, $id): JsonResponse
    {
        if (! $request->isMethod('put')) {
            return response()->json(['message' => 'Method Not Allowed'], 405);
        }

        if (! $request->user() || ! $request->user()->hasRole('driver')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $driver = $request->user()->driver;

        if (! $driver) {
            return response()->json(['message' => 'Driver profile not found'], 422);
        }

        $vehicle = vehicle::where('id', $id)
            ->where('driver_id', $driver->id)
            ->first();

        $request->validate([
            'vehicle_type' => ['sometimes', ...InputValidation::safeStringRules(required: true, max: 255)],
            'description' => ['sometimes', ...InputValidation::safeStringRules(required: true, max: 500)],
            'image_front' => ['sometimes', 'required', ...MediaStorage::imageValidationRules()],
            'image_back' => ['sometimes', 'required', ...MediaStorage::imageValidationRules()],
            'image_left' => ['sometimes', 'required', ...MediaStorage::imageValidationRules()],
            'image_right' => ['sometimes', 'required', ...MediaStorage::imageValidationRules()],
            'plate_number' => 'sometimes|required|string|unique:plate_number,plate_number,'.$vehicle->plateNumber->id,
        ]);

        if ($request->hasFile('image_front') || $request->hasFile('image_back') || $request->hasFile('image_left') || $request->hasFile('image_right')) {
            $vehicleImage = $vehicle->vehicleType->vehicleImage;

            if ($request->hasFile('image_front')) {
                $vehicleImage->image_front = MediaStorage::putFile('vehicle_images', $request->file('image_front'));
            }
            if ($request->hasFile('image_back')) {
                $vehicleImage->image_back = MediaStorage::putFile('vehicle_images', $request->file('image_back'));
            }
            if ($request->hasFile('image_left')) {
                $vehicleImage->image_left = MediaStorage::putFile('vehicle_images', $request->file('image_left'));
            }
            if ($request->hasFile('image_right')) {
                $vehicleImage->image_right = MediaStorage::putFile('vehicle_images', $request->file('image_right'));
            }

            $vehicleImage->save();
        }

        if ($request->has('vehicle_type')) {
            $vehicle->vehicleType->vehicle_type = $request->input('vehicle_type');
        }

        if ($request->has('description')) {
            $vehicle->vehicleType->description = $request->input('description');
        }

        $vehicle->vehicleType->save();

        if ($request->has('plate_number')) {
            $vehicle->plateNumber->plate_number = $request->input('plate_number');
            $vehicle->plateNumber->save();
        }

        $vehicleDetails = vehicle::with(['vehicleType', 'vehicleType.vehicleImage', 'plateNumber'])
            ->where('id', $vehicle->id)
            ->first();

        $this->appendVehicleImageUrls($vehicleDetails);

        return response()->json(['message' => 'Vehicle updated successfully', 'vehicle' => $vehicleDetails], 200);
    }

    /* 
        Endpoint: /api/vehicles/{id}
        URL Params: id
    */
    public function deleteVehicle(Request $request, $id): JsonResponse
    {
        if (! $request->isMethod('delete')) {
            return response()->json(['message' => 'Method Not Allowed'], 405);
        }

        if (! $request->user() || ! $request->user()->hasRole('driver')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $driver = $request->user()->driver;

        if (! $driver) {
            return response()->json(['message' => 'Driver profile not found'], 422);
        }

        $vehicle = vehicle::where('id', $id)->where('driver_id', $driver->id)->first();

        if (! $vehicle) {
            return response()->json(['message' => 'Vehicle not found'], 404);
        }

        $vehicle->delete();

        return response()->json(['message' => 'Vehicle deleted successfully'], 200);
    }

    /* 
        Endpoint: /api/vehicles/{id}
        URL Params: id
    */
    public function restoreVehicle(Request $request, string $id): JsonResponse
    {
        if (! $request->isMethod('put')) {
            return response()->json(['message' => 'Method Not Allowed'], 405);
        }

        $user = $request->user();
        if (! $user || ! $user->hasRole('driver')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $driver = $user->driver;
        if (! $driver) {
            return response()->json(['message' => 'Driver profile not found'], 422);
        }

        $vehicle = vehicle::withTrashed()
            ->where('id', $id)
            ->where('driver_id', $driver->id)
            ->first();

        if (! $vehicle) {
            return response()->json(['message' => 'Vehicle not found'], 404);
        }

        $vehicle->restore();

        return response()->json([
            'message' => 'Vehicle restored successfully',
            'vehicle' => tap($vehicle->fresh(['vehicleType.vehicleImage', 'plateNumber']), function ($vehicle) {
                $this->appendVehicleImageUrls($vehicle);
            }),
        ]);
    }

    // Helper function
    private function appendVehicleImageUrls(?vehicle $vehicle): void
    {
        if (! $vehicle) {
            return;
        }

        $image = $vehicle->vehicleType?->vehicleImage;

        if ($image) {
            $image->setAttribute('image_front_url', $image->image_front ? MediaStorage::url($image->image_front) : null);
            $image->setAttribute('image_back_url', $image->image_back ? MediaStorage::url($image->image_back) : null);
            $image->setAttribute('image_left_url', $image->image_left ? MediaStorage::url($image->image_left) : null);
            $image->setAttribute('image_right_url', $image->image_right ? MediaStorage::url($image->image_right) : null);
        }
    }
}
