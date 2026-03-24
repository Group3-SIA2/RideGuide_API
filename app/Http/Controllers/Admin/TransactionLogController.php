<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminTransactionLog;
use Illuminate\Http\Request;

class TransactionLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorizePermissions($request, 'view_transactions');

        $query = AdminTransactionLog::query()->latest();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('actor_email', 'like', '%' . $search . '%')
                  ->orWhere('transaction_type', 'like', '%' . $search . '%')
                  ->orWhere('module', 'like', '%' . $search . '%')
                  ->orWhere('reference_type', 'like', '%' . $search . '%')
                  ->orWhere('reference_id', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('module')) {
            $query->where('module', $request->input('module'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $logs = $query->paginate(15)->withQueryString();

        return view('admin.transactions.index', [
            'logs' => $logs,
            'filters' => [
                'search' => $request->input('search'),
                'module' => $request->input('module'),
                'status' => $request->input('status'),
            ],
        ]);
    }
}