<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserLiveLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserLiveLocationController extends Controller
{
    /**
     * POST /api/map/live-location
     * Upsert current user’s shared position (non-driver roles; drivers use /api/drivers/location).
     *
     * Throttled by `live-location-post` (see AppServiceProvider); client should not POST every
     * GPS tick — see Flutter LiveLocationShareService (interval and distance gates).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'heading' => 'nullable|numeric|between:0,360',
            'accuracy' => 'nullable|numeric|min:0',
        ]);

        $userId = $request->user()->id;

        $row = UserLiveLocation::query()->where('user_id', $userId)->first();

        if ($row) {
            $row->update([
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'heading' => $validated['heading'] ?? null,
                'accuracy' => $validated['accuracy'] ?? null,
                'updated_at' => now(),
            ]);
        } else {
            $row = UserLiveLocation::create([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'heading' => $validated['heading'] ?? null,
                'accuracy' => $validated['accuracy'] ?? null,
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'latitude' => $row->latitude,
                'longitude' => $row->longitude,
                'heading' => $row->heading,
                'accuracy' => $row->accuracy,
                'updated_at' => $row->updated_at,
            ],
        ], 201);
    }

    /**
     * DELETE /api/map/live-location
     * Stop sharing (removes row for this user).
     */
    public function destroy(Request $request): JsonResponse
    {
        UserLiveLocation::query()->where('user_id', $request->user()->id)->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
