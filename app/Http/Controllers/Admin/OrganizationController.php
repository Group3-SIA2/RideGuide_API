<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Driver;
use App\Models\DriverAssignTerminal;
use App\Models\DriverOrganizationAssignmentLog;
use App\Models\HqAddress;
use App\Models\Organization;
use App\Models\OrganizationType;
use App\Models\OrganizationUserRole;
use App\Models\OrganizationTerminal;
use App\Models\Role;
use App\Models\Terminal;
use App\Models\User;
use App\Support\TransactionLogbook;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class OrganizationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorizePermissions($request, 'view_organizations');
        $currentUser = $request->user();

        $showDeleted = $request->input('status') === 'deleted';

        $query = $showDeleted
            ? Organization::onlyTrashed()->withCount('drivers')->with(['hqAddress', 'organizationType'])
            : Organization::withCount('drivers')->with(['hqAddress', 'organizationType']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('organizationType', function ($typeQ) use ($search) {
                      $typeQ->where('name', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%");
                  })
                  ->orWhereHas('hqAddress', function ($addrQ) use ($search) {
                      $addrQ->where('barangay', 'like', "%{$search}%")
                            ->orWhere('street', 'like', "%{$search}%");
                  });
            });
        }

        if (!$showDeleted && $request->input('status')) {
            $query->where('status', $request->input('status'));
        }

        // Non-admin organization managers can only view organizations they own/manage.
        if ((
                $currentUser->hasRole(Role::ORGANIZATION)
                || $currentUser->hasAnyActiveOrganizationManagement()
            )
            && !$currentUser->hasRole(Role::ADMIN)
            && !$currentUser->hasRole(Role::SUPER_ADMIN)
        ) {
            $query->where(function ($managerScope) use ($currentUser) {
                $managerScope->where('owner_user_id', $currentUser->id)
                    ->orWhereHas('organizationUserRoles', function ($orgUserRoleQuery) use ($currentUser) {
                        $orgUserRoleQuery->where('user_id', $currentUser->id)
                            ->where('status', 'active');
                    });
            });
        }

        $organizations = $query->orderBy('name')->paginate(15)->withQueryString();

        if ($request->ajax()) {
            return response()->json([
                'rows'        => view('admin.organizations._rows', compact('organizations', 'showDeleted'))->render(),
                'pagination'  => $organizations->hasPages() ? (string) $organizations->links() : '',
                'total'       => $organizations->total(),
            ]);
        }

        return view('admin.organizations.index', compact('organizations', 'showDeleted'));
    }

    public function managerDashboard(Request $request)
    {
        $this->authorizePermissions($request, 'view_organization_dashboard');

        $currentUser = $request->user();
        $organizationsForAdmin = collect();
        $selectedOrganizationId = $request->input('organization_id');

        if ($currentUser->hasRole(Role::ADMIN) || $currentUser->hasRole(Role::SUPER_ADMIN)) {
            $organizationsForAdmin = Organization::query()
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name']);

            $managedOrganization = $selectedOrganizationId
                ? Organization::query()->whereNull('deleted_at')->find($selectedOrganizationId)
                : null;
        } else {
            $managedOrganization = $this->managedOrganizationFor($currentUser);
        }

        $totalAssignedDrivers = 0;
        $totalAssignedTerminals = 0;
        $unverifiedDriverLicenses = 0;
        $availableDriversCount = 0;
        $recentlyAssignedDrivers = collect();
        $assignedTerminals = collect();

        if ($managedOrganization) {
            $totalAssignedDrivers = Driver::where('organization_id', $managedOrganization->id)->count();

            $unverifiedDriverLicenses = Driver::where('organization_id', $managedOrganization->id)
                ->whereHas('licenseId', function ($query) {
                    $query->where('verification_status', '!=', 'verified');
                })
                ->count();

            $availableDriversCount = Driver::whereNull('organization_id')->count();

            $recentlyAssignedDrivers = Driver::with(['user', 'licenseId'])
                ->where('organization_id', $managedOrganization->id)
                ->latest('updated_at')
                ->take(10)
                ->get();

            $assignedTerminals = $managedOrganization->terminals()
                ->with([
                    'driverAssignments' => function ($query) use ($managedOrganization) {
                        $query->whereHas('driver', function ($driverQuery) use ($managedOrganization) {
                            $driverQuery->where('organization_id', $managedOrganization->id);
                        })->with(['driver.user', 'driver.licenseId']);
                    },
                ])
                ->orderBy('terminal_name')
                ->get([
                    'terminals.id',
                    'terminals.terminal_name',
                    'terminals.barangay',
                    'terminals.city',
                    'terminals.latitude',
                    'terminals.longitude',
                ]);

            $totalAssignedTerminals = $assignedTerminals->count();

            $managedOrganization->load('hqAddress');
        }

        return view('admin.organizations.manager-dashboard', compact(
            'managedOrganization',
            'totalAssignedDrivers',
            'totalAssignedTerminals',
            'unverifiedDriverLicenses',
            'availableDriversCount',
            'recentlyAssignedDrivers',
            'assignedTerminals',
            'organizationsForAdmin',
            'selectedOrganizationId'
        ));
    }

    public function assignmentIndex(Request $request)
    {
        $this->authorizePermissions($request, 'view_organization_assignments');

        $currentUser = $request->user();
        $organizationsForAdmin = collect();
        $selectedOrganizationId = $request->input('organization_id');

        if ($currentUser->hasRole(Role::ADMIN) || $currentUser->hasRole(Role::SUPER_ADMIN)) {
            $organizationsForAdmin = Organization::query()
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name']);

            $managedOrganization = $selectedOrganizationId
                ? Organization::query()->whereNull('deleted_at')->find($selectedOrganizationId)
                : null;
        } else {
            $managedOrganization = $this->managedOrganizationFor($currentUser);
        }

        $organizationTerminals = collect();
        $allTerminals = collect();

        if (!$managedOrganization) {
            return view('admin.organizations.assignments', [
                'managedOrganization' => null,
                'assignedDrivers' => Driver::query()->whereRaw('1 = 0')->paginate(10, ['*'], 'assigned_page'),
                'availableDrivers' => Driver::query()->whereRaw('1 = 0')->paginate(10, ['*'], 'available_page'),
                'organizationsForAdmin' => $organizationsForAdmin,
                'selectedOrganizationId' => $selectedOrganizationId,
                'organizationTerminals' => $organizationTerminals,
                'allTerminals' => $allTerminals,
            ]);
        }

        $search = $request->input('search');
        $status = $request->input('status');

        $assignedDriversQuery = Driver::with(['user', 'licenseId', 'organization'])
            ->where('organization_id', $managedOrganization->id);

        if ($search) {
            $assignedDriversQuery->where(function ($query) use ($search) {
                $query->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        if ($status) {
            $assignedDriversQuery->whereHas('licenseId', function ($licenseQuery) use ($status) {
                $licenseQuery->where('verification_status', $status);
            });
        }

        $assignedDrivers = $assignedDriversQuery
            ->latest('updated_at')
            ->paginate(10, ['*'], 'assigned_page')
            ->withQueryString();

        $availableDriversQuery = Driver::with(['user', 'licenseId'])
            ->whereNull('organization_id');

        if ($search) {
            $availableDriversQuery->where(function ($query) use ($search) {
                $query->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        if ($status) {
            $availableDriversQuery->whereHas('licenseId', function ($licenseQuery) use ($status) {
                $licenseQuery->where('verification_status', $status);
            });
        }

        $availableDrivers = $availableDriversQuery
            ->latest('created_at')
            ->paginate(10, ['*'], 'available_page')
            ->withQueryString();

        $organizationTerminals = $managedOrganization->terminals()->orderBy('terminal_name')->get();
        $allTerminals = Terminal::orderBy('terminal_name')->get(['id', 'terminal_name']);

        return view('admin.organizations.assignments', compact(
            'managedOrganization',
            'assignedDrivers',
            'availableDrivers',
            'organizationsForAdmin',
            'selectedOrganizationId',
            'organizationTerminals',
            'allTerminals'
        ));
    }

    public function storeTerminal(Request $request)
    {
        $organization = $this->resolveTargetOrganizationForAssignment($request);

        $validated = $request->validate([
            'terminal_id'   => ['nullable', 'uuid', Rule::exists('terminals', 'id')],
            'terminal_name' => [
                'required_without:terminal_id',
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!is_string($value) || trim($value) === '') {
                        return;
                    }

                    $hasOrganizationTypePrefix = preg_match(
                        '/^\s*(toda|puvmp\s*group|transport\s*cooperative|transport\s*alliance(?:\/association)?)(\b|\s*[-:])/i',
                        $value
                    ) === 1;

                    if ($hasOrganizationTypePrefix) {
                        $fail('Terminal name must be location-based only (e.g., "Lagao Public Transport Terminal") and must not include organization type labels.');
                    }
                },
            ],
            'barangay'      => ['required_without:terminal_id', 'nullable', 'string', 'max:255'],
            'city'          => ['required_without:terminal_id', 'nullable', 'string', 'max:255'],
            'latitude'      => ['nullable', 'numeric'],
            'longitude'     => ['nullable', 'numeric'],
        ], [
            'terminal_name.required_without' => 'Terminal name is required when not selecting an existing terminal.',
            'barangay.required_without' => 'Barangay is required when not selecting an existing terminal.',
            'city.required_without' => 'City is required when not selecting an existing terminal.',
        ]);

        if (empty($validated['terminal_id'])) {
            $this->authorizePermissions($request, 'create_organization_terminals');
        } else {
            $this->authorizePermissions($request, 'assign_organization_terminals');
        }

        if (empty($validated['terminal_id']) && empty($validated['terminal_name'])) {
            return redirect()->route($this->panelRouteName($request, 'organizations.assignments.index'), $this->buildOrganizationQuery($request))
                ->with('error', 'Please select an existing terminal or provide details for a new one.');
        }

        if (empty($validated['terminal_id'])) {
            $normalizedTerminalName = preg_replace('/\s+/', ' ', trim((string) $validated['terminal_name']));
            $normalizedBarangay = preg_replace('/\s+/', ' ', trim((string) $validated['barangay']));
            $normalizedCity = preg_replace('/\s+/', ' ', trim((string) $validated['city']));

            $terminal = Terminal::withTrashed()
                ->where('terminal_name', $normalizedTerminalName)
                ->where('barangay', $normalizedBarangay)
                ->where('city', $normalizedCity)
                ->first();

            if ($terminal) {
                if ($terminal->trashed()) {
                    $terminal->restore();
                }
            } else {
                $terminal = Terminal::create([
                    'terminal_name' => $normalizedTerminalName,
                    'barangay'      => $normalizedBarangay,
                    'city'          => $normalizedCity,
                    'latitude'      => $validated['latitude'] ?? null,
                    'longitude'     => $validated['longitude'] ?? null,
                ]);
            }
        } else {
            $terminal = Terminal::findOrFail($validated['terminal_id']);
        }

        $existingLink = OrganizationTerminal::withTrashed()
            ->where('organization_id', $organization->id)
            ->where('terminal_id', $terminal->id)
            ->first();

        if ($existingLink && !$existingLink->trashed()) {
            return redirect()->route($this->panelRouteName($request, 'organizations.assignments.index'), $this->buildOrganizationQuery($request))
                ->with('error', 'This terminal is already linked to your organization.');
        }

        if ($existingLink && $existingLink->trashed()) {
            $existingLink->restore();

            return redirect()->route($this->panelRouteName($request, 'organizations.assignments.index'), $this->buildOrganizationQuery($request))
                ->with('success', 'Terminal linked to your organization successfully.');
        }

        OrganizationTerminal::create([
            'organization_id' => $organization->id,
            'terminal_id'     => $terminal->id,
        ]);

        return redirect()->route($this->panelRouteName($request, 'organizations.assignments.index'), $this->buildOrganizationQuery($request))
            ->with('success', 'Terminal added to your organization successfully.');
    }

    public function removeTerminal(Request $request, Terminal $terminal)
    {
        $this->authorizePermissions($request, 'delete_organization_terminals');

        $organization = $this->resolveTargetOrganizationForAssignment($request);

        $link = OrganizationTerminal::query()
            ->where('organization_id', $organization->id)
            ->where('terminal_id', $terminal->id)
            ->first();

        if (!$link) {
            return redirect()->route($this->panelRouteName($request, 'organizations.assignments.index'), $this->buildOrganizationQuery($request))
                ->with('error', 'Terminal is not linked to the selected organization.');
        }

        $link->delete();

        return redirect()->route($this->panelRouteName($request, 'organizations.assignments.index'), $this->buildOrganizationQuery($request))
            ->with('success', 'Terminal removed from your organization successfully.');
    }

    public function unassignTerminal(Request $request, Terminal $terminal)
    {
        return $this->removeTerminal($request, $terminal);
    }

    public function assignDriver(Request $request, Driver $driver)
    {
        $this->authorizePermissions($request, 'assign_drivers_to_organization');

        $targetOrganization = $this->resolveTargetOrganizationForAssignment($request);

        $this->authorize('assignToOwnedOrganization', [$driver, $targetOrganization]);

        $oldOrganizationId = $driver->organization_id;
        $oldOrganizationName = $oldOrganizationId
            ? Organization::query()->whereKey($oldOrganizationId)->value('name')
            : null;
        $driver->update(['organization_id' => $targetOrganization->id]);

        $this->logDriverAssignmentAction(
            $driver,
            $oldOrganizationId,
            $targetOrganization->id,
            $request->user()->id,
            $oldOrganizationId ? DriverOrganizationAssignmentLog::ACTION_REASSIGN : DriverOrganizationAssignmentLog::ACTION_ASSIGN
        );

        $this->writeDriverAssignmentLog(
            request: $request,
            driver: $driver,
            transactionType: $oldOrganizationId ? 'reassign_driver' : 'assign_driver',
            before: [
                'organization_id' => $oldOrganizationId,
                'organization_name' => $oldOrganizationName,
            ],
            after: [
                'organization_id' => $targetOrganization->id,
                'organization_name' => $targetOrganization->name,
                'terminal_id' => null,
                'terminal_name' => null,
            ]
        );

        $query = [];
        if ($request->filled('organization_id')) {
            $query['organization_id'] = $request->input('organization_id');
        }

        return redirect()->route($this->panelRouteName($request, 'organizations.assignments.index'), $query)
            ->with('success', 'Driver assigned to your organization successfully.');
    }

    public function updateDriverAssignment(Request $request, Driver $driver)
    {
        $this->authorizePermissions($request, 'assign_drivers_to_organization');

        $targetOrganization = $this->resolveTargetOrganizationForAssignment($request);

        $this->authorize('assignToOwnedOrganization', [$driver, $targetOrganization]);

        $validated = $request->validate([
            'terminal_id' => ['required', 'uuid', Rule::exists('terminals', 'id')],
        ]);

        $terminalId = $validated['terminal_id'];
        $newTerminal = Terminal::query()->findOrFail($terminalId);
        $terminalLinked = OrganizationTerminal::query()
            ->where('organization_id', $targetOrganization->id)
            ->where('terminal_id', $terminalId)
            ->exists();

        if (! $terminalLinked) {
            return redirect()->route($this->panelRouteName($request, 'organizations.assignments.index'), $this->buildOrganizationQuery($request))
                ->with('error', 'Please select a terminal linked to the selected organization.');
        }

        $oldOrganizationId = $driver->organization_id;
        $oldOrganizationName = $oldOrganizationId
            ? Organization::query()->whereKey($oldOrganizationId)->value('name')
            : null;
        $oldTerminalId = DriverAssignTerminal::query()
            ->where('driver_id', $driver->id)
            ->whereNull('deleted_at')
            ->value('terminal_id');
        $oldTerminalName = $oldTerminalId
            ? Terminal::query()->whereKey($oldTerminalId)->value('terminal_name')
            : null;
        $driver->update(['organization_id' => $targetOrganization->id]);

        DriverAssignTerminal::query()
            ->where('driver_id', $driver->id)
            ->where('terminal_id', '!=', $terminalId)
            ->delete();

        $assignment = DriverAssignTerminal::withTrashed()
            ->where('driver_id', $driver->id)
            ->where('terminal_id', $terminalId)
            ->first();

        if ($assignment) {
            if ($assignment->trashed()) {
                $assignment->restore();
            }
        } else {
            DriverAssignTerminal::create([
                'driver_id' => $driver->id,
                'terminal_id' => $terminalId,
            ]);
        }

        $this->logDriverAssignmentAction(
            $driver,
            $oldOrganizationId,
            $targetOrganization->id,
            $request->user()->id,
            $oldOrganizationId ? DriverOrganizationAssignmentLog::ACTION_REASSIGN : DriverOrganizationAssignmentLog::ACTION_ASSIGN
        );

        $this->writeDriverAssignmentLog(
            request: $request,
            driver: $driver,
            transactionType: 'assign_driver_area',
            before: [
                'organization_id' => $oldOrganizationId,
                'organization_name' => $oldOrganizationName,
                'terminal_id' => $oldTerminalId,
                'terminal_name' => $oldTerminalName,
            ],
            after: [
                'organization_id' => $targetOrganization->id,
                'organization_name' => $targetOrganization->name,
                'terminal_id' => $terminalId,
                'terminal_name' => $newTerminal->terminal_name,
            ]
        );

        $query = [];
        if ($request->filled('organization_id')) {
            $query['organization_id'] = $request->input('organization_id');
        }

        return redirect()->route($this->panelRouteName($request, 'organizations.assignments.index'), $query)
            ->with('success', 'Driver assignment updated successfully.');
    }

    public function unassignDriver(Request $request, Driver $driver)
    {
        $this->authorizePermissions($request, 'unassign_drivers_from_organization');

        if (!$driver->organization_id) {
            return redirect()->route($this->panelRouteName($request, 'organizations.assignments.index'), [
                'organization_id' => $request->input('organization_id'),
            ])->with('error', 'Driver is not assigned to any organization.');
        }

        $organizationForPolicy = Organization::query()->findOrFail($driver->organization_id);

        $this->authorize('unassignFromOwnedOrganization', [$driver, $organizationForPolicy]);

        $query = [];
        if ($request->filled('organization_id')) {
            $query['organization_id'] = $request->input('organization_id');
        }

        $oldOrganizationId = $driver->organization_id;
        $oldOrganizationName = Organization::query()->whereKey($oldOrganizationId)->value('name');
        $oldTerminalId = DriverAssignTerminal::query()
            ->where('driver_id', $driver->id)
            ->whereNull('deleted_at')
            ->value('terminal_id');
        $oldTerminalName = $oldTerminalId
            ? Terminal::query()->whereKey($oldTerminalId)->value('terminal_name')
            : null;
        $driver->update(['organization_id' => null]);

        $this->logDriverAssignmentAction(
            $driver,
            $oldOrganizationId,
            null,
            $request->user()->id,
            DriverOrganizationAssignmentLog::ACTION_UNASSIGN
        );

        $this->writeDriverAssignmentLog(
            request: $request,
            driver: $driver,
            transactionType: 'unassign_driver',
            before: [
                'organization_id' => $oldOrganizationId,
                'organization_name' => $oldOrganizationName,
                'terminal_id' => $oldTerminalId,
                'terminal_name' => $oldTerminalName,
            ],
            after: [
                'organization_id' => null,
                'organization_name' => null,
                'terminal_id' => null,
                'terminal_name' => null,
            ]
        );

        return redirect()->route($this->panelRouteName($request, 'organizations.assignments.index'), $query)
            ->with('success', 'Driver unassigned successfully.');
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

        $existingTypes = OrganizationType::query()
            ->orderBy('name')
            ->get(['id', 'name', 'description']);


        return view('admin.organizations.create', compact('eligibleOwners', 'existingNames', 'existingTypes'));
    }

    public function store(StoreOrganizationRequest $request)
    {
        $this->authorizePermissions($request, 'create_organizations');
        $this->authorize('create', Organization::class);

        $validated = $request->validated();

        if (empty($validated['organization_type_id']) && !empty($validated['organization_type'])) {
            $validated['organization_type_id'] = OrganizationType::query()
                ->where('name', trim((string) $validated['organization_type']))
                ->value('id');
        }

        unset($validated['organization_type']);

        if (array_key_exists('owner_user_id', $validated)) {
            $ownerUser = User::withTrashed()->find($validated['owner_user_id']);
            $this->authorize('assignOwner', [Organization::class, $ownerUser]);
            $this->ensureOwnerHasOrganizationRole($validated['owner_user_id']);
        }

        // Handle hq_address as a related record
        $hqAddressId = null;
        if (!empty($validated['hq_barangay']) || !empty($validated['hq_street'])) {
            $hqAddress = HqAddress::create([
                'barangay'        => $validated['hq_barangay'] ?? '',
                'street'          => $validated['hq_street'] ?? '',
                'subdivision'     => $validated['hq_subdivision'] ?? null,
                'floor_unit_room' => $validated['hq_floor_unit_room'] ?? null,
                'lat'             => $validated['hq_lat'] ?? null,
                'lng'             => $validated['hq_lng'] ?? null,
            ]);
            $hqAddressId = $hqAddress->id;
        }

        // Remove address sub-fields from validated data and set the FK
        $orgData = collect($validated)
            ->except(['hq_barangay', 'hq_street', 'hq_subdivision', 'hq_floor_unit_room', 'hq_lat', 'hq_lng'])
            ->put('hq_address', $hqAddressId)
            ->all();

        $organization = Organization::create($orgData);

        if (!empty($organization->owner_user_id)) {
            $organizationRoleId = Role::getIdbyName(Role::ORGANIZATION);

            if ($organizationRoleId) {
                OrganizationUserRole::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'user_id' => $organization->owner_user_id,
                        'role_id' => $organizationRoleId,
                    ],
                    [
                        'status' => 'active',
                        'invited_by_user_id' => $request->user()->id,
                    ]
                );
            }
        }

        return redirect()->route($this->panelRouteName($request, 'organizations.index'))
            ->with('success', 'Organization created successfully.');
    }

    public function edit(Request $request, string $id)
    {
        $this->authorizePermissions($request, 'edit_organizations');
        $organization = Organization::with(['hqAddress', 'organizationType'])->findOrFail($id);
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

        $existingTypes = OrganizationType::query()
            ->orderBy('name')
            ->get(['id', 'name', 'description']);


        return view('admin.organizations.edit', compact('organization', 'eligibleOwners', 'existingNames', 'existingTypes'));
    }

    public function update(UpdateOrganizationRequest $request, string $id)
    {
        $this->authorizePermissions($request, 'edit_organizations');
        $organization = Organization::with('hqAddress')->findOrFail($id);
        $this->authorize('update', $organization);

        $validated = $request->validated();
        unset($validated['organization_type']);

        if (array_key_exists('owner_user_id', $validated)) {
            $ownerUser = User::withTrashed()->find($validated['owner_user_id']);
            $this->authorize('assignOwner', [Organization::class, $ownerUser]);
            $this->ensureOwnerHasOrganizationRole($validated['owner_user_id']);
        }

        // Handle hq_address as a related record
        $addressFields = ['hq_barangay', 'hq_street', 'hq_subdivision', 'hq_floor_unit_room', 'hq_lat', 'hq_lng'];
        $hasAddressInput = collect($addressFields)->filter(fn($f) => !empty($validated[$f]))->isNotEmpty();

        if ($hasAddressInput) {
            $addressData = [
                'barangay'        => $validated['hq_barangay'] ?? '',
                'street'          => $validated['hq_street'] ?? '',
                'subdivision'     => $validated['hq_subdivision'] ?? null,
                'floor_unit_room' => $validated['hq_floor_unit_room'] ?? null,
                'lat'             => $validated['hq_lat'] ?? null,
                'lng'             => $validated['hq_lng'] ?? null,
            ];

            if ($organization->hqAddress) {
                // Update existing address record
                $organization->hqAddress->update($addressData);
            } else {
                // Create a new address record and link it
                $hqAddress = HqAddress::create($addressData);
                $validated['hq_address'] = $hqAddress->id;
            }
        }

        $orgData = collect($validated)
            ->except($addressFields)
            ->all();

        $organization->update($orgData);

        if (array_key_exists('owner_user_id', $validated) && !empty($validated['owner_user_id'])) {
            $organizationRoleId = Role::getIdbyName(Role::ORGANIZATION);

            if ($organizationRoleId) {
                OrganizationUserRole::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'user_id' => $validated['owner_user_id'],
                        'role_id' => $organizationRoleId,
                    ],
                    [
                        'status' => 'active',
                        'invited_by_user_id' => $request->user()->id,
                    ]
                );
            }
        }

        return redirect()->route($this->panelRouteName($request, 'organizations.index'))
            ->with('success', 'Organization updated successfully.');
    }

    public function updateAddress(Request $request, string $id)
    {
        $this->authorizePermissions($request, 'edit_organizations');
        $organization = Organization::with('hqAddress')->findOrFail($id);
        $this->authorize('update', $organization);

        $validated = $request->validate([
            'hq_barangay'        => ['required', 'string', 'max:255'],
            'hq_street'          => ['required', 'string', 'max:255'],
            'hq_subdivision'     => ['nullable', 'string', 'max:255'],
            'hq_floor_unit_room' => ['nullable', 'string', 'max:255'],
            'hq_lat'             => ['nullable', 'string', 'max:50'],
            'hq_lng'             => ['nullable', 'string', 'max:50'],
        ]);

        $addressData = [
            'barangay'        => $validated['hq_barangay'],
            'street'          => $validated['hq_street'],
            'subdivision'     => $validated['hq_subdivision'] ?? null,
            'floor_unit_room' => $validated['hq_floor_unit_room'] ?? null,
            'lat'             => $validated['hq_lat'] ?? null,
            'lng'             => $validated['hq_lng'] ?? null,
        ];

        if ($organization->hqAddress) {
            $organization->hqAddress->update($addressData);
        } else {
            $hqAddress = HqAddress::create($addressData);
            $organization->update(['hq_address' => $hqAddress->id]);
        }

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Address updated successfully.']);
        }

        return redirect()->route($this->panelRouteName($request, 'organizations.index'))
            ->with('success', 'Organization address updated successfully.');
    }

    public function destroy(Request $request, string $id)
    {
        $this->authorizePermissions($request, 'delete_organizations');
        $organization = Organization::findOrFail($id);
        $this->authorize('delete', $organization);

        $organization->delete();

        return redirect()->route($this->panelRouteName($request, 'organizations.index'))
            ->with('success', 'Organization deleted successfully.');
    }

    public function restore(Request $request, string $id)
    {
        $this->authorizePermissions($request, 'delete_organizations');
        $organization = Organization::withTrashed()->findOrFail($id);
        $this->authorize('restore', $organization);

        $organization->restore();

        return redirect()->route($this->panelRouteName($request, 'organizations.index'))
            ->with('success', 'Organization restored successfully.');
    }

    public function organizationTypesIndex(Request $request)
    {
        $this->authorizePermissions($request, 'manage_organization_types');

        $organizationTypes = OrganizationType::query()
            ->withCount('organizations')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.organizations.types-index', compact('organizationTypes'));
    }

    public function organizationTypesStore(Request $request)
    {
        $this->authorizePermissions($request, 'manage_organization_types');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $typeName = trim($validated['name']);
        $description = $this->normalizeDescription($validated['description'] ?? null);

        $organizationType = OrganizationType::withTrashed()->firstOrNew(['name' => $typeName]);

        if ($organizationType->exists && !$organizationType->trashed()) {
            return redirect()->route($this->panelRouteName($request, 'organizations.types.index'))
                ->with('error', 'Organization type already exists.')
                ->withInput();
        }

        if ($organizationType->trashed()) {
            $organizationType->restore();
        }

        $organizationType->name = $typeName;
        $organizationType->description = $description;
        $organizationType->save();

        return redirect()->route($this->panelRouteName($request, 'organizations.types.index'))
            ->with('success', 'Organization type created successfully.');
    }

    private function ensureOwnerHasOrganizationRole(?string $ownerUserId): void
    {
        if (!$ownerUserId) {
            return;
        }

        $organizationRoleId = Role::getIdbyName(Role::ORGANIZATION);
        if (!$organizationRoleId) {
            return;
        }

        $ownerUser = User::withTrashed()->find($ownerUserId);
        if (!$ownerUser || $ownerUser->trashed() || $ownerUser->status !== User::STATUS_ACTIVE) {
            return;
        }

        $ownerUser->roles()->syncWithoutDetaching([$organizationRoleId]);
    }

    private function normalizeDescription(?string $description): ?string
    {
        $normalized = trim((string) $description);
        return $normalized === '' ? null : $normalized;
    }

    private function managedOrganizationFor(User $user): ?Organization
    {
        if ($user->hasRole(Role::ADMIN) || $user->hasRole(Role::SUPER_ADMIN)) {
            return null;
        }

        $ownedOrganization = Organization::query()
            ->where('owner_user_id', $user->id)
            ->whereNull('deleted_at')
            ->first();

        if ($ownedOrganization) {
            return $ownedOrganization;
        }

        return Organization::query()
            ->whereNull('deleted_at')
            ->whereHas('organizationUserRoles', function ($orgUserRoleQuery) use ($user) {
                $orgUserRoleQuery->where('user_id', $user->id)
                    ->where('status', 'active');
            })
            ->first();
    }

    private function resolveTargetOrganizationForAssignment(Request $request): Organization
    {
        $currentUser = $request->user();

        if ($currentUser->hasRole(Role::ADMIN) || $currentUser->hasRole(Role::SUPER_ADMIN)) {
            $validated = $request->validate([
                'organization_id' => ['required', 'uuid', Rule::exists('organizations', 'id')],
            ]);

            return Organization::query()->findOrFail($validated['organization_id']);
        }

        $managedOrganization = $this->managedOrganizationFor($currentUser);
        if (!$managedOrganization) {
            abort(403, 'No managed organization is assigned to your account.');
        }

        return $managedOrganization;
    }

    private function logDriverAssignmentAction(
        Driver $driver,
        ?string $oldOrganizationId,
        ?string $newOrganizationId,
        ?string $actedByUserId,
        string $action
    ): void {
        DriverOrganizationAssignmentLog::create([
            'driver_id' => $driver->id,
            'old_organization_id' => $oldOrganizationId,
            'new_organization_id' => $newOrganizationId,
            'acted_by_user_id' => $actedByUserId,
            'action' => $action,
        ]);
    }

    private function buildOrganizationQuery(Request $request): array
    {
        if ($request->filled('organization_id')) {
            return ['organization_id' => $request->input('organization_id')];
        }

        return [];
    }

    private function panelRouteName(Request $request, string $suffix): string
    {
        $routeName = (string) optional($request->route())->getName();

        if (str_starts_with($routeName, 'org-manager.')) {
            return 'org-manager.' . $suffix;
        }

        if (str_starts_with($routeName, 'super-admin.')) {
            return 'super-admin.' . $suffix;
        }

        return 'admin.' . $suffix;
    }

    private function writeDriverAssignmentLog(
        Request $request,
        Driver $driver,
        string $transactionType,
        array $before,
        array $after
    ): void {
        try {
            $actor = $request->user();

            if (! $actor || ! ($actor->hasRole(Role::ADMIN) || $actor->hasRole(Role::SUPER_ADMIN))) {
                return;
            }

            TransactionLogbook::write(
                request: $request,
                module: 'driver_assignments',
                transactionType: $transactionType,
                status: 'success',
                referenceType: 'driver',
                referenceId: (string) $driver->id,
                before: $before,
                after: $after,
                metadata: [
                    'route_name' => optional($request->route())->getName(),
                    'actor_name' => $this->resolveActorName($actor),
                ],
                actorUserId: $actor?->id ? (string) $actor->id : null,
                actorEmail: $actor?->email
            );
        } catch (Throwable $e) {
            report($e);
        }
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

        return $fullName !== '' ? $fullName : null;
    }
}