<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminTransactionLog;
use Illuminate\Http\Request;

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
            'module' => ['nullable', 'string', 'max:50'],
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

        if (! empty($validated['module'])) {
            $query->where('module', $validated['module']);
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

        $modules = AdminTransactionLog::query()
            ->select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        $logs = $query->paginate(15)->withQueryString();

        return view('admin.transactions.index', [
            'logs' => $logs,
            'modules' => $modules,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'module' => $validated['module'] ?? null,
                'status' => $validated['status'] ?? null,
                'created_from' => $validated['created_from'] ?? null,
                'created_to' => $validated['created_to'] ?? null,
            ],
        ]);
    }
}