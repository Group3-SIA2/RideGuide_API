<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FareRate;
use App\Models\Organization;
use App\Models\OrganizationFareRate;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FareController extends Controller
{

    public function index(Request $request)
    {
    $this->authorizePermissions($request, 'view_fare_rates');

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

        $assignedTerminals = collect();
        $currentFareRate = null;
        $routeFares = collect();

        if ($managedOrganization) {
            $assignedTerminals = $managedOrganization->terminals()
                ->orderBy('terminal_name')
                ->get([
                    'terminals.id',
                    'terminals.terminal_name',
                    'terminals.barangay',
                    'terminals.city',
                    'terminals.latitude',
                    'terminals.longitude',
                ]);

            $currentFareRate = OrganizationFareRate::with(['fareRate', 'originTerminal', 'destinationTerminal'])
                ->where('organization_id', $managedOrganization->id)
                ->latest('created_at')
                ->first();

            $routeFares = OrganizationFareRate::with(['fareRate', 'originTerminal', 'destinationTerminal'])
                ->where('organization_id', $managedOrganization->id)
                ->latest('created_at')
                ->get();
        }

        return view('admin.organizations.fare', compact(
            'managedOrganization',
            'organizationsForAdmin',
            'selectedOrganizationId',
            'assignedTerminals',
            'currentFareRate',
            'routeFares'
        ));
    }

    public function overview(Request $request)
    {
    $this->authorizePermissions($request, 'view_fare_rates');

        $currentUser = $request->user();
        $organizationsForAdmin = collect();
        $selectedOrganizationId = $request->input('organization_id');
        $managedOrganization = null;

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

        $targetOrganizationId = $managedOrganization?->id;

        $fareRateOverview = OrganizationFareRate::with(['fareRate', 'organization', 'originTerminal', 'destinationTerminal'])
            ->when($targetOrganizationId, function ($query) use ($targetOrganizationId) {
                $query->where('organization_id', $targetOrganizationId);
            })
            ->latest('created_at')
            ->get()
            ->groupBy(function ($rate) {
                return implode('|', [
                    $rate->organization_id,
                    $rate->origin_terminal_id ?? 'any',
                    $rate->destination_terminal_id ?? 'any',
                ]);
            })
            ->map(function ($rates) {
                return $rates->first();
            })
            ->values();

        return view('admin.organizations.fare-overview', compact(
            'organizationsForAdmin',
            'selectedOrganizationId',
            'managedOrganization',
            'fareRateOverview'
        ));
    }

    public function store(Request $request)
    {
    $this->authorizePermissions($request, 'manage_fare_rates');

        $organization = $this->resolveTargetOrganizationForAssignment($request);

        $validated = $request->validate([
            'origin_terminal_id' => ['nullable', 'uuid', 'exists:terminals,id'],
            'destination_terminal_id' => ['nullable', 'uuid', 'exists:terminals,id'],
            'base_fare_4KM' => ['required', 'numeric', 'min:0'],
            'per_km_rate' => ['required', 'numeric', 'min:0'],
            'route_standard_fare' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
        ]);

        foreach (['origin_terminal_id' => 'Selected origin terminal', 'destination_terminal_id' => 'Selected destination terminal'] as $field => $label) {
            if (!empty($validated[$field])) {
                $terminalLinked = $organization->terminals()
                    ->where('terminals.id', $validated[$field])
                    ->exists();

                if (! $terminalLinked) {
                    return redirect()->route($this->panelRouteName($request, 'organizations.fares.index'), $this->buildOrganizationQuery($request))
                        ->with('error', $label . ' must belong to the organization.');
                }
            }
        }

        $originTerminalId = $validated['origin_terminal_id'] ?? null;
        $destinationTerminalId = $validated['destination_terminal_id'] ?? null;

        if ($originTerminalId && $destinationTerminalId && $originTerminalId === $destinationTerminalId) {
            return redirect()->route($this->panelRouteName($request, 'organizations.fares.index'), $this->buildOrganizationQuery($request))
                ->with('error', 'Origin and destination terminals must be different.');
        }

            $existingLink = OrganizationFareRate::query()
                ->where('organization_id', $organization->id)
                ->where(function ($query) use ($originTerminalId, $destinationTerminalId) {
                    $query->where(function ($inner) use ($originTerminalId, $destinationTerminalId) {
                        $inner->where('origin_terminal_id', $originTerminalId)
                            ->where('destination_terminal_id', $destinationTerminalId);
                    })->orWhere(function ($inner) use ($originTerminalId, $destinationTerminalId) {
                        $inner->where('origin_terminal_id', $destinationTerminalId)
                            ->where('destination_terminal_id', $originTerminalId);
                    });
                })
                ->first();

            if (! $existingLink) {
                return redirect()->route($this->panelRouteName($request, 'organizations.fares.index'), $this->buildOrganizationQuery($request))
                    ->with('error', 'Route fare not found. Use Add Fare Rate to create a new route.');
        }

        $fareRate = FareRate::create([
            'base_fare_4KM' => $validated['base_fare_4KM'],
            'per_km_rate' => $validated['per_km_rate'],
            'route_standard_fare' => $validated['route_standard_fare'],
            'effective_date' => $validated['effective_date'],
        ]);

            $existingLink->update([
                'fare_rate_id' => $fareRate->id,
            ]);

        return redirect()->route($this->panelRouteName($request, 'organizations.fares.index'), $this->buildOrganizationQuery($request))
            ->with('success', 'Fare rates updated successfully.');
    }

    public function updateRouteFare(Request $request, OrganizationFareRate $routeFare)
    {
        $this->authorizePermissions($request, 'manage_fare_rates');

        $organization = $this->resolveTargetOrganizationForAssignment($request);

        if ($routeFare->organization_id !== $organization->id) {
            abort(403, 'Route fare does not belong to this organization.');
        }

        $validated = $request->validate([
            'origin_terminal_id' => ['nullable', 'uuid', 'exists:terminals,id'],
            'destination_terminal_id' => ['nullable', 'uuid', 'exists:terminals,id'],
            'base_fare_4KM' => ['required', 'numeric', 'min:0'],
            'per_km_rate' => ['required', 'numeric', 'min:0'],
            'route_standard_fare' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
        ]);

        foreach (['origin_terminal_id' => 'Selected origin terminal', 'destination_terminal_id' => 'Selected destination terminal'] as $field => $label) {
            if (!empty($validated[$field])) {
                $terminalLinked = $organization->terminals()
                    ->where('terminals.id', $validated[$field])
                    ->exists();

                if (! $terminalLinked) {
                    return redirect()->route($this->panelRouteName($request, 'organizations.fares.index'), $this->buildOrganizationQuery($request))
                        ->with('error', $label . ' must belong to the organization.');
                }
            }
        }

        $originTerminalId = $validated['origin_terminal_id'] ?? null;
        $destinationTerminalId = $validated['destination_terminal_id'] ?? null;

        if ($originTerminalId && $destinationTerminalId && $originTerminalId === $destinationTerminalId) {
            return redirect()->route($this->panelRouteName($request, 'organizations.fares.index'), $this->buildOrganizationQuery($request))
                ->with('error', 'Origin and destination terminals must be different.');
        }

        $duplicateRouteExists = OrganizationFareRate::query()
            ->where('organization_id', $organization->id)
            ->where('id', '!=', $routeFare->id)
            ->where(function ($query) use ($originTerminalId, $destinationTerminalId) {
                $query->where(function ($inner) use ($originTerminalId, $destinationTerminalId) {
                    $inner->where('origin_terminal_id', $originTerminalId)
                        ->where('destination_terminal_id', $destinationTerminalId);
                })->orWhere(function ($inner) use ($originTerminalId, $destinationTerminalId) {
                    $inner->where('origin_terminal_id', $destinationTerminalId)
                        ->where('destination_terminal_id', $originTerminalId);
                });
            })
            ->exists();

        if ($duplicateRouteExists) {
            return redirect()->route($this->panelRouteName($request, 'organizations.fares.index'), $this->buildOrganizationQuery($request))
                ->with('error', 'This Route Fare is added already, Update Available at Fare Rate OverView');
        }

        $fareRate = FareRate::create([
            'base_fare_4KM' => $validated['base_fare_4KM'],
            'per_km_rate' => $validated['per_km_rate'],
            'route_standard_fare' => $validated['route_standard_fare'],
            'effective_date' => $validated['effective_date'],
        ]);

        $routeFare->update([
            'fare_rate_id' => $fareRate->id,
            'origin_terminal_id' => $originTerminalId,
            'destination_terminal_id' => $destinationTerminalId,
        ]);

        return redirect()->route($this->panelRouteName($request, 'organizations.fares.index'), $this->buildOrganizationQuery($request))
            ->with('success', 'Fare rate updated successfully.');
    }

    public function createRouteFare(Request $request)
    {
        $this->authorizePermissions($request, 'manage_fare_rates');

        $organization = $this->resolveTargetOrganizationForAssignment($request);

        $validated = $request->validate([
            'origin_terminal_id' => ['nullable', 'uuid', 'exists:terminals,id'],
            'destination_terminal_id' => ['nullable', 'uuid', 'exists:terminals,id'],
            'base_fare_4KM' => ['required', 'numeric', 'min:0'],
            'per_km_rate' => ['required', 'numeric', 'min:0'],
            'route_standard_fare' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
        ]);

        foreach (['origin_terminal_id' => 'Selected origin terminal', 'destination_terminal_id' => 'Selected destination terminal'] as $field => $label) {
            if (!empty($validated[$field])) {
                $terminalLinked = $organization->terminals()
                    ->where('terminals.id', $validated[$field])
                    ->exists();

                if (! $terminalLinked) {
                    return redirect()->route($this->panelRouteName($request, 'organizations.fares.index'), $this->buildOrganizationQuery($request))
                        ->with('error', $label . ' must belong to the organization.');
                }
            }
        }

        $originTerminalId = $validated['origin_terminal_id'] ?? null;
        $destinationTerminalId = $validated['destination_terminal_id'] ?? null;

        $duplicateRouteExists = OrganizationFareRate::query()
            ->where('organization_id', $organization->id)
            ->where(function ($query) use ($originTerminalId, $destinationTerminalId) {
                $query->where(function ($inner) use ($originTerminalId, $destinationTerminalId) {
                    $inner->where('origin_terminal_id', $originTerminalId)
                        ->where('destination_terminal_id', $destinationTerminalId);
                })->orWhere(function ($inner) use ($originTerminalId, $destinationTerminalId) {
                    $inner->where('origin_terminal_id', $destinationTerminalId)
                        ->where('destination_terminal_id', $originTerminalId);
                });
            })
            ->exists();

        if ($duplicateRouteExists) {
            return redirect()->route($this->panelRouteName($request, 'organizations.fares.index'), $this->buildOrganizationQuery($request))
                ->with('error', 'This Route Fare is added already, Update Available at Fare Rate OverView');
        }

        $fareRate = FareRate::create([
            'base_fare_4KM' => $validated['base_fare_4KM'],
            'per_km_rate' => $validated['per_km_rate'],
            'route_standard_fare' => $validated['route_standard_fare'],
            'effective_date' => $validated['effective_date'],
        ]);

        OrganizationFareRate::create([
            'organization_id' => $organization->id,
            'fare_rate_id' => $fareRate->id,
            'origin_terminal_id' => $originTerminalId,
            'destination_terminal_id' => $destinationTerminalId,
        ]);

        return redirect()->route($this->panelRouteName($request, 'organizations.fares.index'), $this->buildOrganizationQuery($request))
            ->with('success', 'Fare rate added successfully.');
    }

    private function managedOrganizationFor($user): ?Organization
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
                'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            ]);

            return Organization::query()->findOrFail($validated['organization_id']);
        }

        $managedOrganization = $this->managedOrganizationFor($currentUser);
        if (! $managedOrganization) {
            abort(403, 'No managed organization is assigned to your account.');
        }

        return $managedOrganization;
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
}
