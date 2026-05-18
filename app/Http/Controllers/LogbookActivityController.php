<?php

namespace App\Http\Controllers;

use App\Support\TransactionLogbook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\AdminTransactionLog;
use Illuminate\Support\Str;
use Carbon\Carbon;

class LogbookActivityController extends Controller
{
    public function pageTime(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
            'route_name' => ['nullable', 'string', 'max:255'],
            'session_key' => ['nullable', 'string', 'max:80'],
            'duration_seconds' => ['required', 'numeric', 'min:1', 'max:86400'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'visibility_state' => ['nullable', 'string', 'max:20'],
        ]);

        $durationSeconds = (int) round((float) ($validated['duration_seconds'] ?? 0));

        // If client sent 0 or missing, try to compute from started_at/ended_at timestamps as fallback.
        if ($durationSeconds < 1) {
            try {
                $started = ! empty($validated['started_at']) ? Carbon::parse($validated['started_at']) : null;
                $ended = ! empty($validated['ended_at']) ? Carbon::parse($validated['ended_at']) : null;

                if ($started && $ended) {
                    $diff = $ended->diffInSeconds($started);
                    $durationSeconds = max(1, (int) $diff);
                } elseif ($started && ! $ended) {
                    $diff = Carbon::now()->diffInSeconds($started);
                    $durationSeconds = max(1, (int) $diff);
                } else {
                    $durationSeconds = 1;
                }
            } catch (\Throwable $e) {
                $durationSeconds = 1;
            }
        }
        $durationSeconds = max(1, $durationSeconds);
        $path = $validated['path'];
        $user = $request->user();

        $durationHuman = $this->formatDuration($durationSeconds);

        $sessionKey = $validated['session_key'] ?? null;

        // Try to find a recent related activity log for the same page and actor and merge duration into it.
        $candidateQuery = AdminTransactionLog::query()
            ->whereBetween('created_at', [now()->subMinutes(15), now()])
            ->where(function ($q) use ($path, $validated) {
                $q->where(function ($q2) use ($path) {
                    $q2->where('reference_type', 'page')
                        ->where('reference_id', $path);
                })
                ->orWhere('after_data->accessed_page', $path);

                if (! empty($validated['route_name'])) {
                    $q->orWhere('metadata->route', $validated['route_name'])
                      ->orWhere('metadata->route_name', $validated['route_name']);
                }
            });

        if ($sessionKey) {
            $candidateQuery->where('metadata->session_key', $sessionKey);
        } elseif ($user) {
            $candidateQuery->where(function ($qa) use ($user) {
                $qa->where('actor_user_id', (string) $user->id)
                   ->orWhere('actor_email', $user->email);
            });
        } else {
            // for anonymous, match null actor_user_id and similar IP
            $candidateQuery->where(function ($qb) use ($request) {
                $qb->whereNull('actor_user_id')
                   ->where('metadata->ip', $request->ip());
            });
        }

        $candidate = $candidateQuery->latest()->first();

        $afterPatch = [
            'page_path' => $path,
            'page_title' => $validated['page_title'] ?? null,
            'page_url' => $validated['page_url'] ?? null,
            'referrer' => $validated['referrer'] ?? null,
            'duration_seconds' => $durationSeconds,
            'duration_human' => $durationHuman,
            'started_at' => $validated['started_at'] ?? null,
            'ended_at' => $validated['ended_at'] ?? null,
            'visibility_state' => $validated['visibility_state'] ?? null,
            'route_name' => $validated['route_name'] ?? null,
            'session_key' => $sessionKey,
        ];

        if ($candidate) {
            $existingAfter = (array) ($candidate->after_data ?? []);
            $existingMetadata = (array) ($candidate->metadata ?? []);

            $candidate->after_data = array_merge($existingAfter, $afterPatch);
            $candidate->metadata = array_merge($existingMetadata, [
                'duration_seconds' => $durationSeconds,
                'duration_human' => $durationHuman,
                'visibility_state' => $validated['visibility_state'] ?? null,
                'session_key' => $sessionKey,
            ]);
            try {
                if ($candidate instanceof AdminTransactionLog && method_exists($candidate, 'save')) {
                    $candidate->save();
                } else {
                    throw new \RuntimeException('Candidate is not an AdminTransactionLog model');
                }
            } catch (\Throwable $e) {
                // best-effort: if updating fails, write a new log instead
                TransactionLogbook::write(
                    request: $request,
                    module: $candidate->module ?? 'general',
                    transactionType: 'page_time',
                    status: 'success',
                    referenceType: 'page',
                    referenceId: $path,
                    after: $afterPatch,
                    metadata: array_merge([
                        'actor_type' => $user ? 'authenticated' : 'anonymous',
                        'actor_name' => $this->resolveActorName($user),
                        'page_title' => $validated['page_title'] ?? null,
                        'page_url' => $validated['page_url'] ?? null,
                        'referrer' => $validated['referrer'] ?? null,
                        'route_name' => $validated['route_name'] ?? null,
                    ], [
                        'duration_seconds' => $durationSeconds,
                        'duration_human' => $durationHuman,
                        'visibility_state' => $validated['visibility_state'] ?? null,
                        'session_key' => $sessionKey,
                    ]),
                    actorUserId: $user?->id ? (string) $user->id : null,
                    actorEmail: is_string($user?->email) ? $user->email : null,
                );
            }

            return response()->json(['success' => true, 'merged' => true]);
        }

        // No candidate log found — write a new entry but set module based on route_name or path
        $module = 'general';
        if (! empty($validated['route_name'])) {
            $parts = explode('.', $validated['route_name']);
            if (count($parts) >= 2 && $parts[1] !== '') {
                $module = Str::snake($parts[1]);
            }
        } else {
            $segment = trim(explode('/', ltrim($path, '/'))[0] ?? 'general');
            $module = Str::snake($segment ?: 'general');
        }

        TransactionLogbook::write(
            request: $request,
            module: $module,
            transactionType: 'page_time',
            status: 'success',
            referenceType: 'page',
            referenceId: $path,
            after: $afterPatch,
            metadata: array_merge([
                'actor_type' => $user ? 'authenticated' : 'anonymous',
                'actor_name' => $this->resolveActorName($user),
                'page_title' => $validated['page_title'] ?? null,
                'page_url' => $validated['page_url'] ?? null,
                'referrer' => $validated['referrer'] ?? null,
                'route_name' => $validated['route_name'] ?? null,
            ], [
                'duration_seconds' => $durationSeconds,
                'duration_human' => $durationHuman,
                'visibility_state' => $validated['visibility_state'] ?? null,
                'session_key' => $sessionKey,
            ]),
            actorUserId: $user?->id ? (string) $user->id : null,
            actorEmail: is_string($user?->email) ? $user->email : null,
        );

        return response()->json([
            'success' => true,
            'merged' => false,
        ]);
    }

    private function resolveActorName(mixed $user): ?string
    {
        if (! is_object($user)) {
            return null;
        }

        $name = trim((string) ($user->name ?? ''));

        if ($name !== '') {
            return $name;
        }

        $firstName = trim((string) ($user->first_name ?? ''));
        $lastName = trim((string) ($user->last_name ?? ''));
        $fullName = trim($firstName . ' ' . $lastName);

        if ($fullName !== '') {
            return $fullName;
        }

        $email = trim((string) ($user->email ?? ''));

        return $email !== '' ? $email : null;
    }

    private function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;
        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours . ' hour' . ($hours === 1 ? '' : 's');
        }

        if ($minutes > 0) {
            $parts[] = $minutes . ' minute' . ($minutes === 1 ? '' : 's');
        }

        if ($remainingSeconds > 0 || empty($parts)) {
            $parts[] = $remainingSeconds . ' second' . ($remainingSeconds === 1 ? '' : 's');
        }

        return implode(' ', $parts);
    }
}