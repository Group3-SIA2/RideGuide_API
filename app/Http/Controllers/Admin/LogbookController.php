<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminTransactionLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LogbookController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorizePermissions($request, 'view_transactions');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'user' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:success,failed'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
        ]);

        $query = AdminTransactionLog::query()->latest();

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('actor_email', 'like', '%' . $search . '%')
                  ->orWhere('actor_user_id', 'like', '%' . $search . '%')
                  ->orWhere('transaction_type', 'like', '%' . $search . '%')
                  ->orWhere('module', 'like', '%' . $search . '%')
                  ->orWhere('reference_type', 'like', '%' . $search . '%')
                  ->orWhere('reference_id', 'like', '%' . $search . '%')
                  ->orWhere('reason', 'like', '%' . $search . '%')
                  ->orWhere('metadata', 'like', '%' . $search . '%')
                  ->orWhere('before_data', 'like', '%' . $search . '%')
                  ->orWhere('after_data', 'like', '%' . $search . '%');
            });
        }

        if (! empty($validated['user'])) {
            $userSearch = trim($validated['user']);
            $normalizedUserSearch = Str::lower($userSearch);

            $query->where(function ($q) use ($userSearch, $normalizedUserSearch) {
                if (in_array($normalizedUserSearch, ['anonymous', 'guest'], true)) {
                    $q->whereNull('actor_user_id');

                    return;
                }

                // If it's a numeric id, match actor_user_id exactly (the dropdown uses id values)
                if (ctype_digit($userSearch)) {
                    $q->where('actor_user_id', $userSearch);

                    return;
                }

                $q->where('actor_email', 'like', '%' . $userSearch . '%')
                    ->orWhere('actor_user_id', 'like', '%' . $userSearch . '%')
                    ->orWhere('metadata->actor_name', 'like', '%' . $userSearch . '%')
                    ->orWhere('metadata->attempted_identity', 'like', '%' . $userSearch . '%');
            });
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['created_from'])) {
            $query->whereDate('created_at', '>=', $validated['created_from']);
        }

        if (! empty($validated['created_to'])) {
            $query->whereDate('created_at', '<=', $validated['created_to']);
        }

        // Provide a dropdown of all users (so newly created users appear automatically)
        $users = User::query()
            ->selectRaw("id, CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) as name, email")
            ->orderByRaw("COALESCE(first_name,'')")
            ->orderByRaw("COALESCE(last_name,'')")
            ->orderBy('email')
            ->get();

        $logs = $query->paginate(15)->withQueryString();

        // Merge nearby page_time entries into the displayed logs so duration appears inline
        $pageLogs = $logs->items();
        if (! empty($pageLogs)) {
            $min = collect($pageLogs)->pluck('created_at')->filter()->min();
            $max = collect($pageLogs)->pluck('created_at')->filter()->max();

            if ($min && $max) {
                $windowStart = $min->copy()->subMinutes(10);
                $windowEnd = $max->copy()->addMinutes(10);

                $actorIds = collect($pageLogs)->pluck('actor_user_id')->filter()->unique()->values()->all();

                $pageTimeLogs = AdminTransactionLog::query()
                    ->where('transaction_type', 'page_time')
                    ->whereBetween('created_at', [$windowStart, $windowEnd])
                    ->when(! empty($actorIds), function ($q) use ($actorIds) {
                        $q->whereIn('actor_user_id', $actorIds);
                    })
                    ->get();

                foreach ($pageLogs as $log) {
                    $log->merged_duration = null;
                    foreach ($pageTimeLogs as $pt) {
                        if ($pt->reference_type === $log->reference_type
                            && ($pt->reference_id === $log->reference_id)
                            && ($pt->actor_user_id === $log->actor_user_id)
                        ) {
                            $after = $pt->after_data ?? [];
                            $log->merged_duration = data_get($after, 'duration_human') ?: null;
                            break;
                        }
                    }
                }
            }
        }

        return view('admin.transactions.index', [
            'logs' => $logs,
            'users' => $users,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'user' => $validated['user'] ?? null,
                'status' => $validated['status'] ?? null,
                'created_from' => $validated['created_from'] ?? null,
                'created_to' => $validated['created_to'] ?? null,
            ],
        ]);
    }
}