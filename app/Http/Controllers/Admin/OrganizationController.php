<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorizePermissions($request, 'view_organizations');

        $showDeleted = $request->input('status') === 'deleted';

        $query = $showDeleted
            ? Organization::onlyTrashed()->withCount('drivers')
            : Organization::withCount('drivers');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('hq_address', 'like', "%{$search}%");
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

    public function create(Request $request)
    {
        $this->authorizePermissions($request, 'create_organizations');
        $this->authorize('create', Organization::class);

        $eligibleOwners = User::query()
            ->whereNull('deleted_at')
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', [Role::ADMIN, Role::SUPER_ADMIN, Role::ORGANIZATION]);
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        $existingNames = Organization::query()
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values();

        $existingTypes = Organization::query()
            ->orderBy('type')
            ->distinct()
            ->pluck('type')
            ->filter()
            ->values();

        return view('admin.organizations.create', compact('eligibleOwners', 'existingNames', 'existingTypes'));
    }

    public function store(StoreOrganizationRequest $request)
    {
        $this->authorizePermissions($request, 'create_organizations');
        $this->authorize('create', Organization::class);

        $validated = $request->validated();

        if (array_key_exists('owner_user_id', $validated)) {
            $ownerUser = User::withTrashed()->find($validated['owner_user_id']);
            $this->authorize('assignOwner', [Organization::class, $ownerUser]);
        }

        Organization::create($validated);

        return redirect()->route('admin.organizations.index')
            ->with('success', 'Organization created successfully.');
    }

    public function edit(Request $request, string $id)
    {
        $this->authorizePermissions($request, 'edit_organizations');
        $organization = Organization::findOrFail($id);
        $this->authorize('update', $organization);

        $eligibleOwners = User::query()
            ->whereNull('deleted_at')
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', [Role::ADMIN, Role::SUPER_ADMIN, Role::ORGANIZATION]);
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        $existingNames = Organization::query()
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values();

        $existingTypes = Organization::query()
            ->orderBy('type')
            ->distinct()
            ->pluck('type')
            ->filter()
            ->values();

        return view('admin.organizations.edit', compact('organization', 'eligibleOwners', 'existingNames', 'existingTypes'));
    }

    public function update(UpdateOrganizationRequest $request, string $id)
    {
        $this->authorizePermissions($request, 'edit_organizations');
        $organization = Organization::findOrFail($id);
        $this->authorize('update', $organization);

        $validated = $request->validated();

        if (array_key_exists('owner_user_id', $validated)) {
            $ownerUser = User::withTrashed()->find($validated['owner_user_id']);
            $this->authorize('assignOwner', [Organization::class, $ownerUser]);
        }

        $organization->update($validated);

        return redirect()->route('admin.organizations.index')
            ->with('success', 'Organization updated successfully.');
    }

    public function destroy(Request $request, string $id)
    {
        $this->authorizePermissions($request, 'delete_organizations');
        $organization = Organization::findOrFail($id);
        $this->authorize('delete', $organization);

        $organization->delete();

        return redirect()->route('admin.organizations.index')
            ->with('success', 'Organization deleted successfully.');
    }

    public function restore(Request $request, string $id)
    {
        $this->authorizePermissions($request, 'delete_organizations');
        $organization = Organization::withTrashed()->findOrFail($id);
        $this->authorize('restore', $organization);

        $organization->restore();

        return redirect()->route('admin.organizations.index')
            ->with('success', 'Organization restored successfully.');
    }
}
