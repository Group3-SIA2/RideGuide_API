@extends('adminlte::page')

@section('title', 'Organization Dashboard - RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Organization Dashboard</h4>
            <p class="rg-page-subtitle">Overview of your organization and driver assignments.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.organizations.assignments.index', array_filter(['organization_id' => $selectedOrganizationId ?? null])) }}" class="rg-btn rg-btn-primary rg-btn-sm">
                <i class="fas fa-user-plus"></i> Manage Driver Assignments
            </a>
        </div>
    </div>
@stop

@section('content')
    @if(($organizationsForAdmin ?? collect())->isNotEmpty())
        <div class="row mb-3">
            <div class="col-12">
                <div class="rg-card">
                    <div class="rg-card-header">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rg-card-dot"></span>
                            <h6 class="rg-card-title mb-0">Select Organization</h6>
                        </div>
                        <form method="GET" action="{{ route('admin.organizations.manager-dashboard') }}" class="rg-filter-bar mt-2" id="organization-dashboard-filter-form">
                            <select name="organization_id" class="rg-filter-select" required onchange="this.form.submit()" aria-label="Select organization">
                                <option value="">Select Organization</option>
                                @foreach($organizationsForAdmin as $adminOrg)
                                    <option value="{{ $adminOrg->id }}" {{ ($selectedOrganizationId ?? '') === $adminOrg->id ? 'selected' : '' }}>
                                        {{ $adminOrg->name }}
                                    </option>
                                @endforeach
                            </select>
                            <a href="{{ route('admin.organizations.manager-dashboard') }}" class="rg-btn-clear">Clear</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(!$managedOrganization)
        <div class="alert alert-warning">
            @if(($organizationsForAdmin ?? collect())->isNotEmpty())
                Please select an organization to view dashboard metrics.
            @else
                No organization is currently assigned to your account. Please contact an administrator.
            @endif
        </div>
    @else
        <div class="row align-items-stretch">
            <div class="col-12 col-md-6 col-xl-3 mb-3 mb-xl-0">
                <div class="rg-stat-card rg-stat-card-equal h-100">
                    <div class="rg-stat-icon"><i class="fas fa-building"></i></div>
                    <div class="rg-stat-body">
                        <p class="rg-stat-label">My Organization</p>
                        <h3 class="rg-stat-value rg-stat-value-name">{{ $managedOrganization->name }}</h3>
                        <span class="rg-stat-sub rg-stat-sub-org">{{ $managedOrganization->type }}</span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-3 mb-3 mb-xl-0">
                <div class="rg-stat-card rg-stat-card-equal h-100">
                    <div class="rg-stat-icon"><i class="fas fa-id-card"></i></div>
                    <div class="rg-stat-body">
                        <p class="rg-stat-label">Total Assigned Drivers</p>
                        <h3 class="rg-stat-value">{{ number_format($totalAssignedDrivers) }}</h3>
                        <span class="rg-stat-sub">Currently assigned</span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-3 mb-3 mb-xl-0">
                <div class="rg-stat-card rg-stat-card-equal h-100">
                    <div class="rg-stat-icon"><i class="fas fa-id-badge"></i></div>
                    <div class="rg-stat-body">
                        <p class="rg-stat-label">Unverified Licenses</p>
                        <h3 class="rg-stat-value">{{ number_format($unverifiedDriverLicenses) }}</h3>
                        <span class="rg-stat-sub">Pending verification</span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-3 mb-3 mb-xl-0">
                <div class="rg-stat-card rg-stat-card-equal h-100">
                    <div class="rg-stat-icon"><i class="fas fa-user-clock"></i></div>
                    <div class="rg-stat-body">
                        <p class="rg-stat-label">Available Drivers</p>
                        <h3 class="rg-stat-value">{{ number_format($availableDriversCount) }}</h3>
                        <span class="rg-stat-sub">Unassigned pool</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-2">
            <div class="col-12">
                <div class="rg-card">
                    <div class="rg-card-header d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rg-card-dot"></span>
                            <h6 class="rg-card-title mb-0">Recently Assigned Drivers</h6>
                        </div>
                    </div>
                    <div class="rg-card-body p-0">
                        <div class="table-responsive">
                            <table class="rg-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>License Status</th>
                                        <th>Last Assignment Update</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentlyAssignedDrivers as $index => $driver)
                                        <tr>
                                            <td class="rg-td-index">{{ $index + 1 }}</td>
                                            <td>{{ $driver->user->first_name ?? 'N/A' }} {{ $driver->user->last_name ?? '' }}</td>
                                            <td class="rg-td-muted">{{ $driver->user->email ?? 'N/A' }}</td>
                                            <td>
                                                @php $status = $driver->licenseId->verification_status ?? 'unverified'; @endphp
                                                <span class="rg-status-badge {{ $status === 'verified' ? 'rg-status-active' : 'rg-status-pending' }}">
                                                    {{ ucfirst($status) }}
                                                </span>
                                            </td>
                                            <td class="rg-td-muted">{{ optional($driver->updated_at)->format('M d, Y h:i A') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="rg-empty">No assigned drivers yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@stop
