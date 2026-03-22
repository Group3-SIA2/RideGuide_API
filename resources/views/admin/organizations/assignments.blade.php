@extends('adminlte::page')

@section('title', 'Driver Assignments - RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Driver Assignments</h4>
            <p class="rg-page-subtitle">Assign and unassign drivers for {{ $managedOrganization->name ?? 'your selected organization' }}.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.organizations.manager-dashboard') }}" class="rg-btn rg-btn-secondary rg-btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="rg-alert rg-alert-success mb-3">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger mb-3">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger mb-3">
            <ul class="mb-0 pl-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row mb-3">
        <div class="col-12">
            <div class="rg-card">
                <div class="rg-card-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">Search Drivers</h6>
                    </div>
                    <form method="GET" action="{{ route('admin.organizations.assignments.index') }}" class="rg-filter-bar mt-2" id="organization-assignments-filter-form">
                        @if(($organizationsForAdmin ?? collect())->isNotEmpty())
                            <select name="organization_id" class="rg-filter-select" required onchange="this.form.submit()" aria-label="Select organization">
                                <option value="">Select Organization</option>
                                @foreach($organizationsForAdmin as $adminOrg)
                                    <option value="{{ $adminOrg->id }}" {{ ($selectedOrganizationId ?? '') === $adminOrg->id ? 'selected' : '' }}>
                                        {{ $adminOrg->name }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                        <input type="text" name="search" class="rg-search-input" value="{{ request('search') }}" placeholder="Search name or email...">
                        <select name="status" class="rg-filter-select">
                            <option value="">All License Statuses</option>
                            <option value="verified" {{ request('status') === 'verified' ? 'selected' : '' }}>Verified</option>
                            <option value="unverified" {{ request('status') === 'unverified' ? 'selected' : '' }}>Unverified</option>
                            <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                        <button type="submit" class="rg-btn-search"><i class="fas fa-search"></i> Search</button>
                        <a href="{{ route('admin.organizations.assignments.index') }}" class="rg-btn-clear">Clear</a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if(!$managedOrganization)
        <div class="alert alert-warning">
            @if(($organizationsForAdmin ?? collect())->isNotEmpty())
                Please select an organization to manage assignments.
            @else
                No managed organization is assigned to your account. Please contact an administrator.
            @endif
        </div>
    @else

    <div class="row">
        <div class="col-12 col-xl-7 mb-3 mb-xl-0">
            <div class="rg-card h-100">
                <div class="rg-card-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">Assigned Drivers</h6>
                    </div>
                    <span class="rg-badge">{{ $assignedDrivers->total() }} total</span>
                </div>
                <div class="rg-card-body p-0">
                    <div class="table-responsive">
                        <table class="rg-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>License</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignedDrivers as $driver)
                                    <tr>
                                        <td>{{ $driver->user->first_name ?? 'N/A' }} {{ $driver->user->last_name ?? '' }}</td>
                                        <td class="rg-td-muted">{{ $driver->user->email ?? 'N/A' }}</td>
                                        <td>
                                            @php $status = $driver->licenseId->verification_status ?? 'unverified'; @endphp
                                            <span class="rg-status-badge {{ $status === 'verified' ? 'rg-status-active' : 'rg-status-pending' }}">
                                                {{ ucfirst($status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <form method="POST" action="{{ route('admin.organizations.assignments.update', $driver->id) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    @if(!empty($selectedOrganizationId))
                                                        <input type="hidden" name="organization_id" value="{{ $selectedOrganizationId }}">
                                                    @endif
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Update</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.organizations.assignments.unassign', $driver->id) }}" onsubmit="return confirm('Unassign this driver from your organization?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    @if(!empty($selectedOrganizationId))
                                                        <input type="hidden" name="organization_id" value="{{ $selectedOrganizationId }}">
                                                    @endif
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Unassign</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="rg-empty">No assigned drivers found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($assignedDrivers->hasPages())
                    <div class="rg-card-footer">
                        {{ $assignedDrivers->links() }}
                    </div>
                @endif
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="rg-card h-100">
                <div class="rg-card-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">Available Drivers</h6>
                    </div>
                    <span class="rg-badge">{{ $availableDrivers->total() }} total</span>
                </div>
                <div class="rg-card-body p-0">
                    <div class="table-responsive">
                        <table class="rg-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>License</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($availableDrivers as $driver)
                                    <tr>
                                        <td>{{ $driver->user->first_name ?? 'N/A' }} {{ $driver->user->last_name ?? '' }}</td>
                                        <td>
                                            @php $status = $driver->licenseId->verification_status ?? 'unverified'; @endphp
                                            <span class="rg-status-badge {{ $status === 'verified' ? 'rg-status-active' : 'rg-status-pending' }}">
                                                {{ ucfirst($status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" action="{{ route('admin.organizations.assignments.assign', $driver->id) }}">
                                                @csrf
                                                @if(!empty($selectedOrganizationId))
                                                    <input type="hidden" name="organization_id" value="{{ $selectedOrganizationId }}">
                                                @endif
                                                <button type="submit" class="btn btn-sm btn-primary">Assign</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="rg-empty">No available drivers found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($availableDrivers->hasPages())
                    <div class="rg-card-footer">
                        {{ $availableDrivers->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif
@stop
