<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
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
        $showDeleted = $request->input('status') === 'deleted';

        $query = $showDeleted
            ? Organization::onlyTrashed()->withCount('drivers')
            : Organization::withCount('drivers');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($request->input('type')) {
            $query->where('type', $request->input('type'));
        }

        if (!$showDeleted && $request->input('status')) {
            $query->where('status', $request->input('status'));
        }

        $organizations = $query->orderBy('name')->paginate(15)->withQueryString();
        $types         = Organization::distinct()->pluck('type')->filter();

        if ($request->ajax()) {
            return response()->json([
                'rows'        => view('admin.organizations._rows', compact('organizations', 'showDeleted'))->render(),
                'pagination'  => $organizations->hasPages() ? (string) $organizations->links() : '',
                'total'       => $organizations->total(),
            ]);
        }

        return view('admin.organizations.index', compact('organizations', 'types', 'showDeleted'));
    }

    public function create()
    {
        $this->authorize('create', Organization::class);

        return view('admin.organizations.create');
    }

    public function store(StoreOrganizationRequest $request)
    {
        $this->authorize('create', Organization::class);

        Organization::create($request->validated());

        return redirect()->route('admin.organizations.index')
            ->with('success', 'Organization created successfully.');
    }

    public function edit(string $id)
    {
        $organization = Organization::findOrFail($id);
        $this->authorize('update', $organization);

        return view('admin.organizations.edit', compact('organization'));
    }

    public function update(UpdateOrganizationRequest $request, string $id)
    {
        $organization = Organization::findOrFail($id);
        $this->authorize('update', $organization);

        $organization->update($request->validated());

        return redirect()->route('admin.organizations.index')
            ->with('success', 'Organization updated successfully.');
    }

    public function destroy(string $id)
    {
        $organization = Organization::findOrFail($id);
        $this->authorize('delete', $organization);

        $organization->delete();

        return redirect()->route('admin.organizations.index')
            ->with('success', 'Organization deleted successfully.');
    }

    public function restore(string $id)
    {
        $organization = Organization::withTrashed()->findOrFail($id);
        $this->authorize('restore', $organization);

        $organization->restore();

        return redirect()->route('admin.organizations.index')
            ->with('success', 'Organization restored successfully.');
    }
}
