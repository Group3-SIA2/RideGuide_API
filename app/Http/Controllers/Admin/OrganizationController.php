<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = Organization::withCount('drivers');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $organizations = $query->orderBy('name')->paginate(15)->withQueryString();

        $types = Organization::distinct()->pluck('type')->filter();

        if ($request->ajax()) {
            return response()->json([
                'rows'       => view('admin.organizations._rows', compact('organizations'))->render(),
                'pagination' => $organizations->hasPages() ? (string) $organizations->links() : '',
                'total'      => $organizations->total(),
            ]);
        }

        return view('admin.organizations.index', compact('organizations', 'types'));
    }
}
