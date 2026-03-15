<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\vehicle;
use App\Models\vehicleImage;
use App\Models\vehicleType;
use App\Models\PlateNumber;
use App\Models\Driver;


class VehicleController extends Controller
{
    public function addVehicle(Request $request): JsonResponse
    {
        if (!$request->isMethod('POST')) {
            return response()->json(['message' => 'Method Not Allowed'], 405);
        }

        if (!$request->user() || !$request->user()->hasRole('driver')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'vehicle_type' => 'required|string',
            'description' => 'required|string',
            'image_front' => 'required|image',
            'image_back' => 'required|image',
            'image_left' => 'required|image',
            'image_right' => 'required|image',
            'plate_number' => 'required|string|unique:plate_number,plate_number',
        ]);

        $vehicleImage = vehicleImage::create([
            'image_front' => $request->file('image_front')->store('vehicle_images', 'public'),
            'image_back' => $request->file('image_back')->store('vehicle_images', 'public'),
            'image_left' => $request->file('image_left')->store('vehicle_images', 'public'),
            'image_right' => $request->file('image_right')->store('vehicle_images', 'public'),
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

        if (!$driver) {
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
        return response()->json(['message' => 'Vehicle added successfully', 'vehicle' => $vehicleDetails], 201);
    }

    public function listVehicledPerDriver(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole('driver')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $driver = $request->user()->driver;

        if (!$driver) {
            return response()->json(['message' => 'Driver profile not found'], 422);
        }

        $vehicles = vehicle::with(['vehicleType', 'vehicleType.vehicleImage'])
            ->where('driver_id', $driver->id)
            ->get();

        return response()->json(['vehicles' => $vehicles], 200);
    }

    public function updateVehicle(Request $request, $id): JsonResponse
    {
        if (!$request->isMethod('put')) {
            return response()->json(['message' => 'Method Not Allowed'], 405);
        }

        if (!$request->user() || !$request->user()->hasRole('driver')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $driver = $request->user()->driver;

        if (!$driver) {
            return response()->json(['message' => 'Driver profile not found'], 422);
        }

        $vehicle = vehicle::where('id', $id)
            ->where('driver_id', $driver->id)
            ->first();

        $request->validate([
            'vehicle_type' => 'sometimes|required|string',
            'description' => 'sometimes|required|string',
            'image_front' => 'sometimes|required|image',
            'image_back' => 'sometimes|required|image',
            'image_left' => 'sometimes|required|image',
            'image_right' => 'sometimes|required|image',
            'plate_number' => 'sometimes|required|string|unique:plate_number,plate_number,' . $vehicle->plateNumber->id,
        ]);

        if ($request->hasFile('image_front') || $request->hasFile('image_back') || $request->hasFile('image_left') || $request->hasFile('image_right')) {
            $vehicleImage = $vehicle->vehicleType->vehicleImage;

            if ($request->hasFile('image_front')) {
                $vehicleImage->image_front = $request->file('image_front')->store('vehicle_images', 'public');
            }
            if ($request->hasFile('image_back')) {
                $vehicleImage->image_back = $request->file('image_back')->store('vehicle_images', 'public');
            }
            if ($request->hasFile('image_left')) {
                $vehicleImage->image_left = $request->file('image_left')->store('vehicle_images', 'public');
            }
            if ($request->hasFile('image_right')) {
                $vehicleImage->image_right = $request->file('image_right')->store('vehicle_images', 'public');
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
        return response()->json(['message' => 'Vehicle updated successfully', 'vehicle' => $vehicleDetails], 200);
    }

    public function deleteVehicle(Request $request, $id): JsonResponse
    {
        if (!$request->isMethod('delete')) {
            return response()->json(['message' => 'Method Not Allowed'], 405);
        }

        if (!$request->user() || !$request->user()->hasRole('driver')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $driver = $request->user()->driver;

        if (!$driver) {
            return response()->json(['message' => 'Driver profile not found'], 422);
        }

        $vehicle = vehicle::where('id', $id)->where('driver_id', $driver->id)->first();

        if (!$vehicle) {
            return response()->json(['message' => 'Vehicle not found'], 404);
        }

        $vehicle->delete();

        return response()->json(['message' => 'Vehicle deleted successfully'], 200);
    }

    public function restoreVehicle(Request $request, string $id): JsonResponse
    {
        if (!$request->isMethod('put')) {
            return response()->json(['message' => 'Method Not Allowed'], 405);
        }

        $user = $request->user();
        if (!$user || !$user->hasRole('driver')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $driver = $user->driver;
        if (!$driver) {
            return response()->json(['message' => 'Driver profile not found'], 422);
        }

        $vehicle = vehicle::withTrashed()
            ->where('id', $id)
            ->where('driver_id', $driver->id)
            ->first();

        if (!$vehicle) {
            return response()->json(['message' => 'Vehicle not found'], 404);
        }

        $vehicle->restore();

        return response()->json([
            'message' => 'Vehicle restored successfully',
            'vehicle' => $vehicle->fresh(['vehicleType.vehicleImage', 'plateNumber']),
        ]);
    }
}