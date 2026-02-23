<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DriverController extends Controller
{
    // Drivers Profile CRUD

    public function createProfile(Request $request): JsonResponse
    {
        // Code to create a new driver profile
    }

    public function readProfile($id): JsonResponse
    {
        // Code to read a driver profile
    }

    public function updateProfile(Request $request, $id): JsonResponse
    {
        // Code to update an existing driver profile
    }

    public function deleteProfile($id): JsonResponse  // Mark as deleted instead of actually deleting the record
    {
        // Code to delete a driver profile
    }
}
