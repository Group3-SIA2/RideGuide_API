<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\FeedbackImage;
use App\Models\FeedbackVideo;
use App\Support\MediaStorage;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeedbackController extends Controller
{
    public function newFeedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trip_id' => ['required', 'uuid', 'exists:trips,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp'],
            'videos' => ['sometimes', 'array'],
            'videos.*' => ['file', 'mimetypes:video/mp4,video/quicktime,video/x-m4v,video/x-msvideo'],
        ]);

        $commuter = $request->user()?->commuter;
        if (! $commuter) {
            return response()->json([
                'success' => false,
                'message' => 'Commuter profile not found for this user.',
            ], 403);
        }

        $isPassenger = DB::table('trip_passengers')
            ->where('trip_id', $validated['trip_id'])
            ->where('commuter_id', $commuter->id)
            ->exists();

        if (! $isPassenger) {
            return response()->json([
                'success' => false,
                'message' => 'Only commuters affiliated with this trip can leave feedback.',
            ], 403);
        }

        $feedback = Feedback::create([
            'commuter_id' => $commuter->id,
            'trip_id' => $validated['trip_id'],
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        foreach ($request->file('images', []) as $imageFile) {
            $path = MediaStorage::putFile('feedback/images', $imageFile);
            FeedbackImage::create([
                'feedback_id' => $feedback->id,
                'image' => $path,
            ]);
        }

        foreach ($request->file('videos', []) as $videoFile) {
            $path = MediaStorage::putFile('feedback/videos', $videoFile);
            FeedbackVideo::create([
                'feedback_id' => $feedback->id,
                'video' => $path,
            ]);
        }

        return response()->json([
            'success' => true,
            'feedback' => $feedback->load(['images', 'videos'])->setRelation(
                'images',
                $feedback->images->map(function ($image) {
                    $image->url = MediaStorage::url($image->image);
                    return $image;
                })
            )->setRelation(
                'videos',
                $feedback->videos->map(function ($video) {
                    $video->url = MediaStorage::url($video->video);
                    return $video;
                })
            ),
        ], 201);
    }

    public function updateFeedback(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'comment' => ['sometimes', 'nullable', 'string'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp'],
            'videos' => ['sometimes', 'array'],
            'videos.*' => ['file', 'mimetypes:video/mp4,video/quicktime,video/x-m4v,video/x-msvideo'],
        ]);

        $commuter = $request->user()?->commuter;
        if (! $commuter) {
            return response()->json([
                'success' => false,
                'message' => 'Commuter profile not found for this user.',
            ], 403);
        }

        $feedback = Feedback::query()->findOrFail($id);
        if ($feedback->commuter_id !== $commuter->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update this feedback.',
            ], 403);
        }

        $feedback->fill($validated);
        $feedback->save();

        if (array_key_exists('images', $validated)) {
            $feedback->images()->delete();
            foreach ($request->file('images', []) as $imageFile) {
                $path = MediaStorage::putFile('feedback/images', $imageFile);
                FeedbackImage::create([
                    'feedback_id' => $feedback->id,
                    'image' => $path,
                ]);
            }
        }

        if (array_key_exists('videos', $validated)) {
            $feedback->videos()->delete();
            foreach ($request->file('videos', []) as $videoFile) {
                $path = MediaStorage::putFile('feedback/videos', $videoFile);
                FeedbackVideo::create([
                    'feedback_id' => $feedback->id,
                    'video' => $path,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'feedback' => $feedback->load(['images', 'videos'])->setRelation(
                'images',
                $feedback->images->map(function ($image) {
                    $image->url = MediaStorage::url($image->image);
                    return $image;
                })
            )->setRelation(
                'videos',
                $feedback->videos->map(function ($video) {
                    $video->url = MediaStorage::url($video->video);
                    return $video;
                })
            ),
        ]);
    }

    public function getAllFeedbackByTrip(string $tripId): JsonResponse
    {
        $feedback = Feedback::query()
            ->where('trip_id', $tripId)
            ->with(['commuter.user', 'images', 'videos'])
            ->latest('created_at')
            ->get()
            ->map(function ($item) {
                $item->setRelation(
                    'images',
                    $item->images->map(function ($image) {
                        $image->url = MediaStorage::url($image->image);
                        return $image;
                    })
                );
                $item->setRelation(
                    'videos',
                    $item->videos->map(function ($video) {
                        $video->url = MediaStorage::url($video->video);
                        return $video;
                    })
                );

                return $item;
            });

        return response()->json([
            'success' => true,
            'data' => $feedback,
        ]);
    }

    public function deleteFeedback(Request $request, string $id): JsonResponse
    {
        $commuter = $request->user()?->commuter;
        if (! $commuter) {
            return response()->json([
                'success' => false,
                'message' => 'Commuter profile not found for this user.',
            ], 403);
        }

        $feedback = Feedback::query()->findOrFail($id);
        if ($feedback->commuter_id !== $commuter->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this feedback.',
            ], 403);
        }

        $feedback->delete();

        Feedback::onlyTrashed()
            ->where('deleted_at', '<=', Carbon::now()->subDays(30))
            ->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Feedback deleted successfully.',
        ]);
    }

    public function restoreFeedback(string $id): JsonResponse
    {
        $commuter = $request->user()?->commuter;
        if (! $commuter) {
            return response()->json([
                'success' => false,
                'message' => 'Commuter profile not found for this user.',
            ], 403);
        }

        $feedback = Feedback::onlyTrashed()->findOrFail($id);
        if ($feedback->commuter_id !== $commuter->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to restore this feedback.',
            ], 403);
        }

        $feedback->restore();

        return response()->json([
            'success' => true,
            'feedback' => $feedback,
        ]);
    }

    public function createFeedback(Request $request): JsonResponse
    {
        return $this->newFeedback($request);
    }

    public function readFeedback(string $id): JsonResponse
    {
        $feedback = Feedback::query()->with(['commuter.user', 'images', 'videos'])->findOrFail($id);
        $feedback->setRelation(
            'images',
            $feedback->images->map(function ($image) {
                $image->url = MediaStorage::url($image->image);
                return $image;
            })
        );
        $feedback->setRelation(
            'videos',
            $feedback->videos->map(function ($video) {
                $video->url = MediaStorage::url($video->video);
                return $video;
            })
        );

        return response()->json([
            'success' => true,
            'data' => $feedback,
        ]);
    }
}
