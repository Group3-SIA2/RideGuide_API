@extends('adminlte::page')

@section('title', 'Fare Rate Overview - RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Fare Rate Overview</h4>
            <p class="rg-page-subtitle">Review the latest fare rates per organization.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            @php
                $panelPrefix = request()->routeIs('org-manager.*')
                    ? 'org-manager'
                    : (request()->routeIs('super-admin.*') ? 'super-admin' : 'admin');
                $dashboardRoute = $panelPrefix . '.organizations.manager-dashboard';
                $fareRoute = $panelPrefix . '.organizations.fares.index';
                $overviewQuery = array_filter(['organization_id' => request('organization_id')]);
            @endphp
            <a href="{{ route($fareRoute, $overviewQuery) }}#create-fare-form" class="rg-btn rg-btn-primary rg-btn-sm">
                <i class="fas fa-plus"></i> Add Fare Rate
            </a>
            <a href="{{ route($dashboardRoute) }}" class="rg-btn rg-btn-secondary rg-btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
@stop

@section('content')
    @php
        $panelPrefix = request()->routeIs('org-manager.*')
            ? 'org-manager'
            : (request()->routeIs('super-admin.*') ? 'super-admin' : 'admin');
        $fareOverviewRoute = $panelPrefix . '.organizations.fares.overview';
        $fareRoute = $panelPrefix . '.organizations.fares.index';
        $selectedOrgId = $selectedOrganizationId ?? ($managedOrganization->id ?? null);
    @endphp

    @if(($organizationsForAdmin ?? collect())->isNotEmpty())
        <div class="row mb-3">
            <div class="col-12">
                <div class="rg-card">
                    <div class="rg-card-header">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rg-card-dot"></span>
                            <h6 class="rg-card-title mb-0">Filter by Organization</h6>
                        </div>
                        <form method="GET" action="{{ route($fareOverviewRoute) }}" class="rg-filter-bar mt-2">
                            <select name="organization_id" class="rg-filter-select" onchange="this.form.submit()" aria-label="Select organization">
                                <option value="">All Organizations</option>
                                @foreach($organizationsForAdmin as $adminOrg)
                                    <option value="{{ $adminOrg->id }}" {{ ($selectedOrganizationId ?? '') === $adminOrg->id ? 'selected' : '' }}>
                                        {{ $adminOrg->name }}
                                    </option>
                                @endforeach
                            </select>
                            <a href="{{ route($fareOverviewRoute) }}" class="rg-btn-clear">Clear</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(!$managedOrganization && ($organizationsForAdmin ?? collect())->isEmpty())
        <div class="alert alert-warning">
            No organization is currently assigned to your account. Please contact an administrator.
        </div>
    @else
        <div class="row g-3 g-xl-4">
            <div class="col-12">
                <div class="rg-card">
                    <div class="rg-card-header d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rg-card-dot"></span>
                            <h6 class="rg-card-title mb-0">Latest Fare Rates</h6>
                        </div>
                        <span class="rg-badge">{{ ($fareRateOverview ?? collect())->count() }} total</span>
                    </div>
                    <div class="rg-card-body">
                        @if(($fareRateOverview ?? collect())->isEmpty())
                            <p class="rg-empty mb-0">No fare rates configured yet.</p>
                        @else
                            <div class="table-responsive">
                                <table class="rg-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Organization</th>
                                            <th>Base Fare</th>
                                            <th>Per KM Rate</th>
                                            <th>Terminal Fare</th>
                                            <th>Effective Date</th>
                                            <th>Route</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($fareRateOverview as $overviewRate)
                                            <tr>
                                                <td>{{ $overviewRate->organization?->name ?? '—' }}</td>
                                                <td>
                                                    {{ optional($overviewRate->fareRate)->base_fare_4KM !== null
                                                        ? number_format((float) $overviewRate->fareRate->base_fare_4KM, 2)
                                                        : '—' }}
                                                </td>
                                                <td>
                                                    {{ optional($overviewRate->fareRate)->per_km_rate !== null
                                                        ? number_format((float) $overviewRate->fareRate->per_km_rate, 2)
                                                        : '—' }}
                                                </td>
                                                <td>
                                                    {{ optional($overviewRate->fareRate)->route_standard_fare !== null
                                                        ? number_format((float) $overviewRate->fareRate->route_standard_fare, 2)
                                                        : '—' }}
                                                </td>
                                                <td>{{ optional($overviewRate->fareRate?->effective_date)->format('M d, Y') ?? '—' }}</td>
                                                <td class="rg-td-muted">
                                                    {{ $overviewRate->originTerminal?->terminal_name ?? 'Any terminal' }}
                                                    →
                                                    {{ $overviewRate->destinationTerminal?->terminal_name ?? 'Any terminal' }}
                                                </td>
                                                <td>
                                                    <a href="{{ route($fareRoute, array_filter([
                                                        'organization_id' => $overviewRate->organization_id ?? $selectedOrgId,
                                                        'route_fare_id' => $overviewRate->id,
                                                    ])) }}#fare-rate-form" class="rg-btn rg-btn-primary rg-btn-sm">
                                                        <i class="fas fa-pen"></i> Update Fare Rate
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
@stop
