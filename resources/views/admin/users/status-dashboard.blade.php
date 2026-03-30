@extends('adminlte::page')

@section('title', 'User Management — RideGuide Admin')

@php
    $panelPrefix = request()->routeIs('super-admin.*') ? 'super-admin' : 'admin';
    $userStatusIndexRoute = $panelPrefix . '.user-status.index';
    $userStatusUsersUpdateRoute = $panelPrefix . '.user-status.users.update';
    $userStatusDriversUpdateRoute = $panelPrefix . '.user-status.drivers.update';
    $userStatusVehiclesUpdateRoute = $panelPrefix . '.user-status.vehicles.update';
    $userStatusDiscountsUpdateRoute = $panelPrefix . '.user-status.discounts.update';
    $userStatusRestoreSearchRoute = $panelPrefix . '.user-status.restore.search';
    $userStatusRestoreRecordRoute = $panelPrefix . '.user-status.restore.record';
@endphp

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">User Management</h4>
            <p class="rg-page-subtitle">Review accounts and update statuses across users, drivers, vehicles, and discounts.</p>
        </div>
        <div class="d-flex flex-column align-items-md-end">
            <a href="{{ route($panelPrefix . '.user-status.create') }}" class="btn btn-outline-success mb-2 align-self-md-end">
                <i class="fas fa-user-plus mr-1"></i>
                Create New User
            </a>
            <button type="button" class="btn btn-outline-primary mt-3 align-self-md-end" data-toggle="modal" data-target="#restoreRecordsModal">
                <i class="fas fa-history mr-1"></i>
                Restore Deleted Records
            </button>
        </div>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-warning">
            <strong>There were some problems with your submission:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rg-card">
        <div class="rg-card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div class="d-flex align-items-center">
        @if($discounts->count())
            @foreach($discounts as $discount)
                @php
                    $discountImage = optional($discount->idImage);
                    $discountModalId = 'discountImagesModal_' . $discount->id;
                @endphp
                @php
                    $discountFrontUrl = $discountImage && $discountImage->image_front
                        ? ($discountImage->image_front_url ?? \App\Support\MediaStorage::url($discountImage->image_front))
                        : null;
                    $discountBackUrl = $discountImage && $discountImage->image_back
                        ? ($discountImage->image_back_url ?? \App\Support\MediaStorage::url($discountImage->image_back))
                        : null;
                @endphp
                @if($discountFrontUrl || $discountBackUrl)
                    <div class="modal fade" id="{{ $discountModalId }}" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Discount ID Images — {{ optional(optional($discount->commuter)->user)->first_name }} {{ optional(optional($discount->commuter)->user)->last_name }}</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        @php $hasDiscountImages = false; @endphp
                                        @if($discountFrontUrl)
                                            @php $hasDiscountImages = true; @endphp
                                            <div class="col-md-6 mb-3">
                                                <img src="{{ $discountFrontUrl }}" class="img-fluid rounded shadow-sm" alt="Front ID Image">
                                                <small class="text-muted d-block mt-2">Front View</small>
                                            </div>
                                        @endif
                                        @if($discountBackUrl)
                                            @php $hasDiscountImages = true; @endphp
                                            <div class="col-md-6 mb-3">
                                                <img src="{{ $discountBackUrl }}" class="img-fluid rounded shadow-sm" alt="Back ID Image">
                                                <small class="text-muted d-block mt-2">Back View</small>
                                            </div>
                                        @endif
                                        @if(!$hasDiscountImages)
                                            <div class="col-12">
                                                <p class="text-muted mb-0">No ID images uploaded.</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        @endif
                <span class="rg-card-dot"></span>
                <h6 class="rg-card-title mb-0">User Accounts</h6>
            </div>
            <form class="rg-filter-bar mt-3 mt-md-0" method="GET" action="{{ route($userStatusIndexRoute) }}">
                <input type="text" name="search" class="rg-search-input" placeholder="Search user by name or email…" value="{{ $filters['search'] }}">
                <button type="submit" class="rg-btn-search">
                    <i class="fas fa-search"></i>
                    <span>Search</span>
                </button>
            </form>
        </div>
        <div class="rg-card-body p-0">
            <div class="table-responsive">
                <table class="rg-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Roles</th>
                            <th>Status</th>
                            <th>Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                        <tr>
                            <td>
                                <div class="rg-user-cell">
                                    <div class="rg-avatar">
                                        {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="rg-user-name mb-0">{{ $user->first_name }} {{ $user->last_name }}</p>
                                        <small class="text-muted">{{ $user->email }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @forelse($user->roles as $role)
                                    <span class="rg-role-badge">{{ ucwords(str_replace('_', ' ', $role->name)) }}</span>
                                @empty
                                    <span class="text-muted">No role</span>
                                @endforelse
                            </td>
                            <td>
                                <span class="rg-status-badge {{ $user->status === 'active' ? 'rg-status-active' : ($user->status === 'suspended' ? 'rg-status-danger' : 'rg-status-pending') }}">
                                    {{ ucfirst($user->status) }}
                                </span>
                                @if($user->status_reason)
                                    <small class="text-muted d-block mt-1">{{ $user->status_reason }}</small>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route($userStatusUsersUpdateRoute, $user) }}" method="POST" class="form-inline">
                                    @csrf
                                    @method('PATCH')
                                    <div class="form-row w-100">
                                        <div class="col-md-4 mb-2 mb-md-0">
                                            <select name="status" class="form-control form-control-sm">
                                                @foreach($userStatusOptions as $value => $label)
                                                    <option value="{{ $value }}" @selected($user->status === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-5 mb-2 mb-md-0">
                                            <input type="text" name="status_reason" class="form-control form-control-sm" placeholder="Reason (optional)" value="{{ $user->status_reason }}">
                                        </div>
                                        <div class="col-md-3 text-md-right">
                                            <button type="submit" class="btn btn-sm btn-primary btn-block">
                                                Update
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="rg-empty">No users found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($users->hasPages())
        <div class="rg-card-footer">
            {{ $users->appends(['search' => $filters['search']])->links() }}
        </div>
        @endif
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="rg-card h-100">
                <div class="rg-card-header">
                    <div class="d-flex align-items-center">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">Driver Verification</h6>
                    </div>
                </div>
                <div class="rg-card-body p-0">
                    <div class="table-responsive">
                        <table class="rg-table">
                            <thead>
                                <tr>
                                    <th>Driver</th>
                                    <th>License</th>
                                    <th>Status</th>
                                    <th>Update</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($drivers as $driver)
                                @php
                                    $license = $driver->licenseId;
                                    $licenseNumber = $license?->license_id;
                                    $licenseImage = $license ? $license->image : null;
                                    $licenseStatus = $license->verification_status ?? 'unverified';
                                    $licenseModalId = 'driverLicenseModal_' . $driver->id;
                                    $licenseFrontUrl = $licenseImage && $licenseImage->image_front
                                        ? ($licenseImage->image_front_url ?? \App\Support\MediaStorage::url($licenseImage->image_front))
                                        : null;
                                    $licenseBackUrl = $licenseImage && $licenseImage->image_back
                                        ? ($licenseImage->image_back_url ?? \App\Support\MediaStorage::url($licenseImage->image_back))
                                        : null;
                                    $hasLicenseImages = $licenseFrontUrl || $licenseBackUrl;
                                @endphp
                                <tr>
                                    <td>
                                        <p class="mb-0 font-weight-semibold">
                                            {{ optional($driver->user)->first_name }} {{ optional($driver->user)->last_name }}
                                        </p>
                                        <small class="text-muted">{{ optional($driver->user)->email }}</small>
                                    </td>
                                    <td>
                                        <span class="text-muted d-block">{{ $licenseNumber ?? '—' }}</span>
                                        @if($hasLicenseImages)
                                            <button type="button" class="btn btn-link btn-sm px-0" data-toggle="modal" data-target="#{{ $licenseModalId }}">
                                                <i class="fas fa-id-card mr-1"></i> View License ID ({{ $licenseNumber ?? 'N/A' }})
                                            </button>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="rg-status-badge {{ $licenseStatus === 'verified' ? 'rg-status-active' : ($licenseStatus === 'rejected' ? 'rg-status-danger' : 'rg-status-pending') }}">
                                            {{ ucfirst($licenseStatus) }}
                                        </span>
                                        @if($license && $license->rejection_reason)
                                            <small class="text-muted d-block mt-1">{{ $license->rejection_reason }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <form action="{{ route($userStatusDriversUpdateRoute, $driver) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <div class="form-row align-items-start">
                                                <div class="col-12 col-md-5 mb-2 mb-md-0">
                                                    <select name="verification_status" class="form-control form-control-sm">
                                                        @foreach($driverVerificationOptions as $value => $label)
                                                            <option value="{{ $value }}" @selected($licenseStatus === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-5 mb-2 mb-md-0">
                                                    <input type="text" name="rejection_reason" class="form-control form-control-sm" placeholder="Reason (optional)" value="{{ $license->rejection_reason ?? '' }}">
                                                </div>
                                                <div class="col-12 col-md-2 text-md-right">
                                                    <button type="submit" class="btn btn-sm btn-primary">Update</button>
                                                </div>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="rg-empty">No drivers found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @foreach($drivers as $driver)
                        @php
                            $license = $driver->licenseId;
                            $licenseNumber = $license?->license_id;
                            $licenseImage = $license ? $license->image : null;
                            $licenseFrontUrl = $licenseImage && $licenseImage->image_front
                                ? ($licenseImage->image_front_url ?? \App\Support\MediaStorage::url($licenseImage->image_front))
                                : null;
                            $licenseBackUrl = $licenseImage && $licenseImage->image_back
                                ? ($licenseImage->image_back_url ?? \App\Support\MediaStorage::url($licenseImage->image_back))
                                : null;
                            $hasLicenseImages = $licenseFrontUrl || $licenseBackUrl;
                            $licenseModalId = 'driverLicenseModal_' . $driver->id;
                        @endphp
                        @if($hasLicenseImages)
                            <div class="modal fade" id="{{ $licenseModalId }}" tabindex="-1" role="dialog" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Driver License Images — {{ optional($driver->user)->first_name }} {{ optional($driver->user)->last_name }}</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="text-muted mb-3">License ID Number: <strong>{{ $licenseNumber ?? 'N/A' }}</strong></p>
                                            <div class="row">
                                                @if($licenseFrontUrl)
                                                    <div class="col-md-6 mb-3">
                                                        <img src="{{ $licenseFrontUrl }}" class="img-fluid rounded shadow-sm" alt="Driver License Front">
                                                        <small class="text-muted d-block mt-2">Front View</small>
                                                    </div>
                                                @endif
                                                @if($licenseBackUrl)
                                                    <div class="col-md-6 mb-3">
                                                        <img src="{{ $licenseBackUrl }}" class="img-fluid rounded shadow-sm" alt="Driver License Back">
                                                        <small class="text-muted d-block mt-2">Back View</small>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
                @if($drivers->hasPages())
                <div class="rg-card-footer">
                    {{ $drivers->links() }}
                </div>
                @endif
            </div>
        </div>
        <div class="col-12 mt-4">
            <div class="rg-card h-100">
                <div class="rg-card-header">
                    <div class="d-flex align-items-center">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">Vehicle Verification</h6>
                    </div>
                </div>
                <div class="rg-card-body p-0">
                    <div class="table-responsive">
                        <table class="rg-table">
                            <thead>
                                <tr>
                                    <th>Vehicle</th>
                                    <th>Status</th>
                                    <th>Update</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($vehicles as $vehicle)
                                <tr>
                                    <td>
                                        <p class="mb-0 font-weight-semibold">Plate: {{ optional($vehicle->plateNumber)->plate_number ?? 'N/A' }}</p>
                                        <small class="text-muted">Driver: {{ optional(optional($vehicle->driver)->user)->first_name }} {{ optional(optional($vehicle->driver)->user)->last_name }}</small>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="rg-status-badge {{ $vehicle->status === 'active' ? 'rg-status-active' : 'rg-status-pending' }}">
                                                {{ ucfirst($vehicle->status) }}
                                            </span>
                                            <span class="rg-status-badge ml-1 {{ $vehicle->verification_status === 'verified' ? 'rg-status-active' : ($vehicle->verification_status === 'rejected' ? 'rg-status-danger' : 'rg-status-pending') }}">
                                                {{ ucfirst($vehicle->verification_status) }}
                                            </span>
                                        </div>
                                        @if($vehicle->rejection_reason)
                                            <small class="text-muted d-block mt-1">{{ $vehicle->rejection_reason }}</small>
                                        @endif
                                        @php
                                            $vehicleImage = optional(optional($vehicle->vehicleType)->vehicleImage);
                                            $vehicleModalId = 'vehicleImagesModal_' . $vehicle->id;
                                            $vehicleHasImages = $vehicleImage && ($vehicleImage->image_front || $vehicleImage->image_back || $vehicleImage->image_left || $vehicleImage->image_right);
                                        @endphp
                                        @if($vehicleHasImages)
                                            <button type="button" class="btn btn-link btn-sm px-0 mt-2" data-toggle="modal" data-target="#{{ $vehicleModalId }}">
                                                <i class="fas fa-images mr-1"></i> View Photos
                                            </button>
                                        @endif
                                    </td>
                                    <td>
                                        <form action="{{ route($userStatusVehiclesUpdateRoute, $vehicle) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <div class="form-row">
                                                <div class="col-12 col-md-4 mb-2 mb-md-0">
                                                    <select name="status" class="form-control form-control-sm">
                                                        @foreach($vehicleStatusOptions as $value => $label)
                                                            <option value="{{ $value }}" @selected($vehicle->status === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-4 mb-2 mb-md-0">
                                                    <select name="verification_status" class="form-control form-control-sm">
                                                        @foreach($vehicleVerificationOptions as $value => $label)
                                                            <option value="{{ $value }}" @selected($vehicle->verification_status === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-4">
                                                    <input type="text" name="rejection_reason" class="form-control form-control-sm" placeholder="Reason (optional)" value="{{ $vehicle->rejection_reason }}">
                                                </div>
                                                <div class="col-12 mt-2">
                                                    <button type="submit" class="btn btn-sm btn-primary btn-block">Update</button>
                                                </div>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="rg-empty">No vehicles found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($vehicles->hasPages())
                <div class="rg-card-footer">
                    {{ $vehicles->links() }}
                </div>
                @endif
            </div>
            @if($vehicles->count())
                @foreach($vehicles as $vehicle)
                    @php
                        $vehicleImage = optional(optional($vehicle->vehicleType)->vehicleImage);
                        $vehicleModalId = 'vehicleImagesModal_' . $vehicle->id;
                    @endphp
                    @if($vehicleImage && ($vehicleImage->image_front || $vehicleImage->image_back || $vehicleImage->image_left || $vehicleImage->image_right))
                        @php
                            $vehicleImages = collect([
                                'Front' => 'image_front',
                                'Back' => 'image_back',
                                'Left' => 'image_left',
                                'Right' => 'image_right',
                            ])->mapWithKeys(function ($field, $label) use ($vehicleImage) {
                                $path = $vehicleImage->{$field};
                                $urlAttribute = $field . '_url';

                                return [
                                    $label => [
                                        'path' => $path,
                                        'url' => $vehicleImage->{$urlAttribute} ?? ($path ? \App\Support\MediaStorage::url($path) : null),
                                    ],
                                ];
                            })->toArray();
                        @endphp
                        <div class="modal fade" id="{{ $vehicleModalId }}" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Vehicle Photos — {{ optional(optional($vehicle->driver)->user)->first_name }} {{ optional(optional($vehicle->driver)->user)->last_name }}</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            @php $hasVehicleImages = false; @endphp
                                            @foreach($vehicleImages as $label => $imageData)
                                                @php
                                                    $imagePath = $imageData['path'];
                                                    $imageUrl = $imageData['url'] ?? null;

                                                    if (!$imageUrl && $imagePath) {
                                                        $imageUrl = asset('storage/' . $imagePath);
                                                    }
                                                @endphp
                                                @if($imageUrl)
                                                    @php $hasVehicleImages = true; @endphp
                                                    <div class="col-md-6 mb-3">
                                                        <div class="rg-image-preview">
                                                            <img src="{{ $imageUrl }}" class="img-fluid rounded shadow-sm" alt="{{ $label }} view">
                                                            <small class="text-muted d-block mt-2">{{ $label }} View</small>
                                                        </div>
                                                    </div>
                                                @endif
                                            @endforeach
                                            @if(!$hasVehicleImages)
                                                <div class="col-12">
                                                    <p class="text-muted mb-0">No vehicle images uploaded.</p>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            @endif
        </div>
    </div>

    <div class="rg-card mt-4">
        <div class="rg-card-header">
            <div class="d-flex align-items-center">
                <span class="rg-card-dot"></span>
                <h6 class="rg-card-title mb-0">Discount Verification</h6>
            </div>
        </div>
        <div class="rg-card-body p-0">
            <div class="table-responsive">
                <table class="rg-table">
                    <thead>
                        <tr>
                            <th>Commuter</th>
                            <th>ID Details</th>
                            <th>Status</th>
                            <th>Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($discounts as $discount)
                        <tr>
                            <td>
                                <p class="mb-0 font-weight-semibold">
                                    {{ optional(optional($discount->commuter)->user)->first_name }} {{ optional(optional($discount->commuter)->user)->last_name }}
                                </p>
                                <small class="text-muted">{{ optional(optional($discount->commuter)->user)->email }}</small>
                            </td>
                            <td>
                                <span class="d-block">ID #: {{ $discount->ID_number }}</span>
                                <small class="text-muted">{{ optional($discount->classificationType)->name ?? 'No classification' }}</small>
                            </td>
                            <td>
                                <span class="rg-status-badge {{ $discount->verification_status === 'verified' ? 'rg-status-active' : ($discount->verification_status === 'rejected' ? 'rg-status-danger' : 'rg-status-pending') }}">
                                    {{ ucfirst($discount->verification_status) }}
                                </span>
                                @if($discount->rejection_reason)
                                    <small class="text-muted d-block mt-1">{{ $discount->rejection_reason }}</small>
                                @endif
                                @php
                                    $discountImage = optional($discount->idImage);
                                    $discountModalId = 'discountImagesModal_' . $discount->id;
                                    $discountFrontUrl = $discountImage && $discountImage->image_front
                                        ? ($discountImage->image_front_url ?? \App\Support\MediaStorage::url($discountImage->image_front))
                                        : null;
                                    $discountBackUrl = $discountImage && $discountImage->image_back
                                        ? ($discountImage->image_back_url ?? \App\Support\MediaStorage::url($discountImage->image_back))
                                        : null;
                                    $discountHasImages = $discountFrontUrl || $discountBackUrl;
                                @endphp
                                @if($discountHasImages)
                                    <button type="button" class="btn btn-link btn-sm px-0 mt-2" data-toggle="modal" data-target="#{{ $discountModalId }}">
                                        <i class="fas fa-id-card mr-1"></i> View ID Images
                                    </button>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route($userStatusDiscountsUpdateRoute, $discount) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <div class="form-row">
                                        <div class="col-12 col-md-4 mb-2 mb-md-0">
                                            <select name="verification_status" class="form-control form-control-sm">
                                                @foreach($discountVerificationOptions as $value => $label)
                                                    <option value="{{ $value }}" @selected($discount->verification_status === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-5 mb-2 mb-md-0">
                                            <input type="text" name="rejection_reason" class="form-control form-control-sm" placeholder="Reason (optional)" value="{{ $discount->rejection_reason }}">
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <button type="submit" class="btn btn-sm btn-primary btn-block">Update</button>
                                        </div>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="rg-empty">No discount applications found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($discounts->hasPages())
        <div class="rg-card-footer">
            {{ $discounts->links() }}
        </div>
        @endif
    </div>

    <div class="modal fade" id="restoreRecordsModal" tabindex="-1" role="dialog" aria-labelledby="restoreRecordsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="restoreRecordsModalLabel">Restore Deleted Records</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Search soft-deleted users, drivers, vehicles, or discounts and restore them instantly.</p>
                    <form id="restoreSearchForm" class="mb-3">
                        <div class="form-row">
                            <div class="col-md-4 mb-2 mb-md-0">
                                <select id="restoreEntity" class="form-control">
                                    <option value="user">Users</option>
                                    <option value="driver">Drivers</option>
                                    <option value="vehicle">Vehicles</option>
                                    <option value="discount">Discounts</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <div class="input-group">
                                    <input type="text" id="restoreQuery" class="form-control" placeholder="Search by name, email, plate number…" autocomplete="off">
                                    <div class="input-group-append">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="fas fa-search mr-1"></i> Search
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    <div id="restoreSearchFeedback" class="alert d-none" role="alert"></div>
                    <div id="restoreSearchSpinner" class="text-center py-4 d-none">
                        <i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i>
                        <p class="mt-2 mb-0 text-muted">Searching deleted records…</p>
                    </div>
                    <div id="restoreEmptyState" class="text-center text-muted py-4 d-none">
                        <i class="fas fa-database fa-2x mb-2"></i>
                        <p class="mb-0">No deleted records match your search.</p>
                    </div>
                    <div id="restoreResults" class="list-group d-none"></div>
                </div>
            </div>
        </div>
    </div>
@stop

@push('js')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = $('#restoreRecordsModal');
    const form = document.getElementById('restoreSearchForm');
    const entitySelect = document.getElementById('restoreEntity');
    const queryInput = document.getElementById('restoreQuery');
    const resultsContainer = document.getElementById('restoreResults');
    const feedback = document.getElementById('restoreSearchFeedback');
    const spinner = document.getElementById('restoreSearchSpinner');
    const emptyState = document.getElementById('restoreEmptyState');
    const searchUrl = '{{ route($userStatusRestoreSearchRoute) }}';
    const restoreUrl = '{{ route($userStatusRestoreRecordRoute) }}';
    const csrfTokenMeta = document.head.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : '';

    modal.on('shown.bs.modal', () => {
        queryInput.focus();
        loadResults();
    });

    modal.on('hidden.bs.modal', () => {
        hideFeedback();
    });

    entitySelect.addEventListener('change', () => loadResults());

    form.addEventListener('submit', event => {
        event.preventDefault();
        loadResults();
    });

    resultsContainer.addEventListener('click', event => {
        const button = event.target.closest('button[data-action="restore-record"]');
        if (!button) {
            return;
        }

        restoreRecord(button);
    });

    function loadResults() {
        const entity = entitySelect.value;
        const query = queryInput.value.trim();

        showSpinner();
        hideFeedback();
        resultsContainer.classList.add('d-none');
        emptyState.classList.add('d-none');

        fetch(`${searchUrl}?entity=${encodeURIComponent(entity)}&query=${encodeURIComponent(query)}`, {
            headers: {
                'Accept': 'application/json'
            }
        })
            .then(handleResponse)
            .then(data => {
                renderResults(entity, data.results || []);
            })
            .catch(error => {
                hideSpinner();
                showFeedback(error.message || 'Unable to load deleted records. Please try again.', 'danger');
            });
    }

    function renderResults(entity, items) {
        hideSpinner();
        resultsContainer.innerHTML = '';

        if (!items.length) {
            resultsContainer.classList.add('d-none');
            emptyState.classList.remove('d-none');
            return;
        }

        emptyState.classList.add('d-none');
        resultsContainer.classList.remove('d-none');

        items.forEach(item => {
            const row = document.createElement('div');
            row.className = 'list-group-item d-flex flex-column flex-md-row justify-content-between align-items-md-center';

            const details = document.createElement('div');
            details.className = 'mb-2 mb-md-0 mr-md-3';

            const title = document.createElement('h6');
            title.className = 'mb-1 font-weight-semibold';
            title.textContent = item.title || 'Untitled';
            details.appendChild(title);

            if (item.subtitle) {
                const subtitle = document.createElement('div');
                subtitle.className = 'text-muted small';
                subtitle.textContent = item.subtitle;
                details.appendChild(subtitle);
            }

            if (item.meta) {
                const badge = document.createElement('span');
                badge.className = 'badge badge-light text-primary mt-1 mr-2';
                badge.textContent = item.meta;
                details.appendChild(badge);
            }

            if (item.deleted_at) {
                const deletedAt = document.createElement('small');
                deletedAt.className = 'd-block text-muted';
                deletedAt.textContent = `Deleted ${item.deleted_at}`;
                details.appendChild(deletedAt);
            }

            const actions = document.createElement('div');
            actions.className = 'text-md-right w-100 w-md-auto';

            const restoreButton = document.createElement('button');
            restoreButton.type = 'button';
            restoreButton.className = 'btn btn-sm btn-success';
            restoreButton.dataset.action = 'restore-record';
            restoreButton.dataset.entity = entity;
            restoreButton.dataset.id = item.id;
            restoreButton.textContent = 'Restore';

            actions.appendChild(restoreButton);

            row.appendChild(details);
            row.appendChild(actions);

            resultsContainer.appendChild(row);
        });
    }

    function restoreRecord(button) {
        const entity = button.dataset.entity;
        const id = button.dataset.id;
        const originalText = button.textContent;

        button.disabled = true;
        button.textContent = 'Restoring…';

        fetch(restoreUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ entity, id }),
        })
            .then(handleResponse)
            .then(data => {
                showFeedback(data.message || 'Record restored successfully.', 'success');
                const item = button.closest('.list-group-item');
                if (item) {
                    item.remove();
                }
                if (!resultsContainer.querySelector('.list-group-item')) {
                    resultsContainer.classList.add('d-none');
                    emptyState.classList.remove('d-none');
                }
            })
            .catch(error => {
                showFeedback(error.message || 'Unable to restore the record.', 'danger');
                button.disabled = false;
                button.textContent = originalText;
            })
            .finally(() => {
                if (!button.disabled) {
                    button.textContent = originalText;
                }
            });
    }

    function handleResponse(response) {
        return response.json().catch(() => ({})).then(data => {
            if (!response.ok) {
                throw new Error(data.message || 'Something went wrong.');
            }
            return data;
        });
    }

    function showSpinner() {
        spinner.classList.remove('d-none');
    }

    function hideSpinner() {
        spinner.classList.add('d-none');
    }

    function showFeedback(message, type) {
        feedback.textContent = message;
        feedback.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        feedback.classList.add('alert-' + type);
    }

    function hideFeedback() {
        feedback.classList.add('d-none');
        feedback.classList.remove('alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        feedback.textContent = '';
    }
});
</script>
@endpush
