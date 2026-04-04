@extends('adminlte::page')

@section('title', 'Driver Assignments - RideGuide Admin')

@php
    $panelPrefix = request()->routeIs('org-manager.*')
        ? 'org-manager'
        : (request()->routeIs('super-admin.*') ? 'super-admin' : 'admin');
    $dashboardRoute = $panelPrefix . '.organizations.manager-dashboard';
    $assignmentsRoute = $panelPrefix . '.organizations.assignments.index';
    $assignRoute = $panelPrefix . '.organizations.assignments.assign';
    $updateRoute = $panelPrefix . '.organizations.assignments.update';
    $unassignRoute = $panelPrefix . '.organizations.assignments.unassign';
    $terminalsStoreRoute = $panelPrefix . '.organizations.terminals.store';
    $terminalsRemoveRoute = $panelPrefix . '.organizations.terminals.remove';
    $driverTerminalMapData = ($organizationTerminals ?? collect())->map(function ($terminal) {
        return [
            'id' => $terminal->id,
            'name' => $terminal->terminal_name,
            'barangay' => $terminal->barangay,
            'city' => $terminal->city,
            'lat' => $terminal->latitude,
            'lng' => $terminal->longitude,
        ];
    })->values();
@endphp

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Driver Assignments</h4>
            <p class="rg-page-subtitle">Assign and unassign drivers for {{ $managedOrganization->name ?? 'your selected organization' }}.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route($dashboardRoute) }}" class="rg-btn rg-btn-secondary rg-btn-sm">
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
                    <form method="GET" action="{{ route($assignmentsRoute) }}" class="rg-filter-bar mt-2" id="organization-assignments-filter-form">
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
                        <a href="{{ route($assignmentsRoute) }}" class="rg-btn-clear">Clear</a>
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
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary js-driver-update"
                                                    data-update-url="{{ route($updateRoute, $driver->id) }}"
                                                    data-driver-name="{{ trim(($driver->user->first_name ?? '') . ' ' . ($driver->user->last_name ?? '')) ?: 'Driver' }}"
                                                >
                                                    Update
                                                </button>
                                                <form method="POST" action="{{ route($unassignRoute, $driver->id) }}" onsubmit="return confirm('Unassign this driver from your organization?');">
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
                                            <form method="POST" action="{{ route($assignRoute, $driver->id) }}">
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

    <div class="row mt-4">
        <div class="col-12 col-xl-6 mb-3 mb-xl-0">
            <div class="rg-card h-100">
                <div class="rg-card-header" style="justify-content:flex-start;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">Add Terminal</h6>
                    </div>
                </div>
                <div class="rg-card-body pt-3">
                    <form method="POST" action="{{ route($terminalsStoreRoute) }}">
                        @csrf
                        @if(!empty($selectedOrganizationId))
                            <input type="hidden" name="organization_id" value="{{ $selectedOrganizationId }}">
                        @endif

                        <div class="form-group mb-3 p-3">
                            <label for="terminal_id">Use Existing Terminal</label>
                            <select name="terminal_id" id="terminal_id" class="form-control">
                                <option value="">-- Select terminal --</option>
                                @foreach($allTerminals as $terminalOption)
                                    <option value="{{ $terminalOption->id }}" {{ old('terminal_id') === $terminalOption->id ? 'selected' : '' }}>
                                        {{ $terminalOption->terminal_name }}
                                    </option>
                                @endforeach
                            </select>
                            <small id="terminal-mode-hint" class="form-text text-info">Select an existing terminal or enter details for a new terminal.</small>
                        </div>

                        <div class="form-group mb-3 p-3">
                            <label for="terminal_name">Terminal Name</label>
                            <input type="text" id="terminal_name" name="terminal_name" class="form-control" value="{{ old('terminal_name') }}" placeholder="Lagao Public Transport Terminal">
                            <small class="form-text text-muted">Use location-based names only. Do not include organization type labels (TODA, PUVMP Group, etc.).</small>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6 p-3">
                                <label for="barangay">Barangay</label>
                                <input type="text" id="barangay" name="barangay" class="form-control" value="{{ old('barangay') }}">
                            </div>
                            <div class="form-group col-md-6 p-3">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city" class="form-control" value="{{ old('city') }}">
                            </div>
                        </div>

                        <input type="hidden" id="terminal_latitude" name="latitude" value="{{ old('latitude') }}">
                        <input type="hidden" id="terminal_longitude" name="longitude" value="{{ old('longitude') }}">

                        <div class="form-group px-3 pb-3">
                            <button type="button" class="btn btn-outline-info btn-sm" id="terminal-map-picker">
                                Select Coordinates on Map
                            </button>
                            <small class="form-text text-muted">Optional: pin terminal location on the map.</small>
                        </div>

                        <button type="submit" class="rg-btn rg-btn-primary mb-3" style="margin-left: 1rem;">
                            <i class="fas fa-plus-circle mr-1"></i> Add Terminal
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="rg-card h-100">
                <div class="rg-card-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">Assigned Terminals</h6>
                    </div>
                    <span class="rg-badge">{{ $organizationTerminals->count() }} total</span>
                </div>
                <div class="rg-card-body p-0">
                    @if($organizationTerminals->isEmpty())
                        <p class="rg-empty mb-0 p-3">No terminals linked yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="rg-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Location</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($organizationTerminals as $terminal)
                                        <tr>
                                            <td>{{ $terminal->terminal_name }}</td>
                                            <td class="rg-td-muted">{{ $terminal->barangay }}, {{ $terminal->city }}</td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-info js-terminal-view"
                                                        data-terminal-name="{{ $terminal->terminal_name }}"
                                                        data-terminal-barangay="{{ $terminal->barangay }}"
                                                        data-terminal-city="{{ $terminal->city }}"
                                                        data-terminal-latitude="{{ $terminal->latitude }}"
                                                        data-terminal-longitude="{{ $terminal->longitude }}"
                                                    >
                                                        View
                                                    </button>

                                                    <form method="POST" action="{{ route($terminalsRemoveRoute, $terminal->id) }}" onsubmit="return confirm('Remove this terminal from the selected organization?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        @if(!empty($selectedOrganizationId))
                                                            <input type="hidden" name="organization_id" value="{{ $selectedOrganizationId }}">
                                                        @endif
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                                    </form>
                                                </div>
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

    <div class="modal fade" id="terminalDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terminal Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-2"><strong>Name:</strong> <span id="terminal-detail-name">-</span></p>
                    <p class="mb-2"><strong>Barangay:</strong> <span id="terminal-detail-barangay">-</span></p>
                    <p class="mb-2"><strong>City:</strong> <span id="terminal-detail-city">-</span></p>
                    <p class="mb-3"><strong>Coordinates:</strong> <span id="terminal-detail-coordinates">-</span></p>
                    <div id="terminal-detail-map" class="rg-map-modal"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="driverUpdateModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form method="POST" id="driver-update-form">
                    @csrf
                    @method('PUT')
                    @if(!empty($selectedOrganizationId))
                        <input type="hidden" name="organization_id" value="{{ $selectedOrganizationId }}">
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title" id="driver-update-title">Assign Terminal</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="driver-terminal-select">Select Terminal</label>
                            <select name="terminal_id" id="driver-terminal-select" class="form-control" required>
                                <option value="">-- Select terminal --</option>
                                @foreach($organizationTerminals as $terminal)
                                    <option value="{{ $terminal->id }}">
                                        {{ $terminal->terminal_name }} ({{ $terminal->barangay }}, {{ $terminal->city }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Drivers can be linked to one terminal at a time.</small>
                        </div>
                        <div id="driver-terminal-map" class="rg-map-modal"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="terminalMapModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Terminal Location</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-2 text-muted">Click on the map to set latitude and longitude.</p>
                    <div id="terminal-picker-map" class="rg-map-modal"></div>
                    <p class="mt-3 mb-0"><strong>Selected:</strong> <span id="terminal-picker-coordinates">Not set</span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@stop

@section('css')
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        .rg-map-modal {
            height: 320px;
            width: 100%;
            border-radius: 10px;
        }
    </style>
@stop

@section('js')
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var terminalSelect = document.getElementById('terminal_id');
    var terminalNameInput = document.getElementById('terminal_name');
    var barangayInput = document.getElementById('barangay');
    var cityInput = document.getElementById('city');
    var terminalMapButton = document.getElementById('terminal-map-picker');

    if (!terminalSelect || !terminalNameInput || !barangayInput || !cityInput) {
        return;
    }

    var newTerminalFields = [terminalNameInput, barangayInput, cityInput];

    function hasNewTerminalValue() {
        return newTerminalFields.some(function (field) {
            return field.value.trim() !== '';
        });
    }

    function setNewTerminalFieldsDisabled(disabled) {
        newTerminalFields.forEach(function (field) {
            field.disabled = disabled;
        });

        if (terminalMapButton) {
            terminalMapButton.disabled = disabled;
        }
    }

    function syncTerminalInputMode(source) {
        var hasExistingTerminal = terminalSelect.value !== '';

        if (hasExistingTerminal) {
            newTerminalFields.forEach(function (field) {
                field.value = '';
            });

            setNewTerminalFieldsDisabled(true);
            terminalSelect.disabled = false;
            updateModeHint('existing');
            return;
        }

        var hasManualInput = hasNewTerminalValue();

        if (hasManualInput) {
            terminalSelect.value = '';
            terminalSelect.disabled = true;
            setNewTerminalFieldsDisabled(false);
            updateModeHint('manual');
            return;
        }

        terminalSelect.disabled = false;
        setNewTerminalFieldsDisabled(false);
        updateModeHint('neutral');
    }

    function updateModeHint(mode) {
        var modeHint = document.getElementById('terminal-mode-hint');

        if (!modeHint) {
            return;
        }

        if (mode === 'existing') {
            modeHint.textContent = 'Existing terminal selected.';
            return;
        }

        if (mode === 'manual') {
            modeHint.textContent = 'New terminal, Create a new terminal.';
            return;
        }

        modeHint.textContent = 'Select an existing terminal or enter details for a new terminal.';
    }

    terminalSelect.addEventListener('change', function () {
        syncTerminalInputMode('existing');
    });

    newTerminalFields.forEach(function (field) {
        field.addEventListener('input', function () {
            syncTerminalInputMode('manual');
        });
    });

    syncTerminalInputMode('initial');

    var terminalViewButtons = document.querySelectorAll('.js-terminal-view');
    var terminalDetailsModal = document.getElementById('terminalDetailsModal');
    var terminalDetailMap;
    var terminalDetailMarker;

    if (terminalViewButtons.length && terminalDetailsModal) {
        terminalViewButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var name = button.getAttribute('data-terminal-name') || '-';
                var barangay = button.getAttribute('data-terminal-barangay') || '-';
                var city = button.getAttribute('data-terminal-city') || '-';
                var latitude = button.getAttribute('data-terminal-latitude') || '';
                var longitude = button.getAttribute('data-terminal-longitude') || '';
                var coordinates = (latitude && longitude) ? (latitude + ', ' + longitude) : '-';

                document.getElementById('terminal-detail-name').textContent = name;
                document.getElementById('terminal-detail-barangay').textContent = barangay;
                document.getElementById('terminal-detail-city').textContent = city;
                document.getElementById('terminal-detail-coordinates').textContent = coordinates;

                $('#terminalDetailsModal').modal('show');

                $('#terminalDetailsModal').off('shown.bs.modal').on('shown.bs.modal', function () {
                    var mapContainer = document.getElementById('terminal-detail-map');
                    if (!mapContainer) {
                        return;
                    }

                    var lat = latitude ? parseFloat(latitude) : 6.1164;
                    var lng = longitude ? parseFloat(longitude) : 125.1716;

                    if (!terminalDetailMap) {
                        terminalDetailMap = L.map('terminal-detail-map', {
                            center: [lat, lng],
                            zoom: latitude && longitude ? 15 : 13,
                        });

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; OpenStreetMap contributors'
                        }).addTo(terminalDetailMap);
                    }

                    terminalDetailMap.setView([lat, lng], latitude && longitude ? 15 : 13);
                    terminalDetailMap.invalidateSize();

                    if (terminalDetailMarker) {
                        terminalDetailMap.removeLayer(terminalDetailMarker);
                    }

                    if (latitude && longitude) {
                        terminalDetailMarker = L.marker([lat, lng]).addTo(terminalDetailMap);
                    }
                });
            });
        });
    }

    var updateButtons = document.querySelectorAll('.js-driver-update');
    var driverUpdateModal = document.getElementById('driverUpdateModal');
    var driverUpdateForm = document.getElementById('driver-update-form');
    var driverUpdateTitle = document.getElementById('driver-update-title');
    var driverTerminalMap;
    var driverTerminalMarkers = [];
    var driverTerminalData = @json($driverTerminalMapData);

    if (updateButtons.length && driverUpdateModal && driverUpdateForm) {
        updateButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var updateUrl = button.getAttribute('data-update-url');
                var driverName = button.getAttribute('data-driver-name') || 'Driver';

                driverUpdateForm.setAttribute('action', updateUrl);
                if (driverUpdateTitle) {
                    driverUpdateTitle.textContent = 'Assign Terminal for ' + driverName;
                }

                $('#driverUpdateModal').modal('show');

                $('#driverUpdateModal').off('shown.bs.modal').on('shown.bs.modal', function () {
                    var mapContainer = document.getElementById('driver-terminal-map');
                    if (!mapContainer) {
                        return;
                    }

                    if (!driverTerminalMap) {
                        driverTerminalMap = L.map('driver-terminal-map', {
                            center: [6.1164, 125.1716],
                            zoom: 13,
                        });

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; OpenStreetMap contributors'
                        }).addTo(driverTerminalMap);
                    }

                    driverTerminalMap.invalidateSize();

                    driverTerminalMarkers.forEach(function (marker) {
                        driverTerminalMap.removeLayer(marker);
                    });
                    driverTerminalMarkers = [];

                    var bounds = L.latLngBounds();

                    driverTerminalData.forEach(function (terminal) {
                        if (!terminal.lat || !terminal.lng) {
                            return;
                        }

                        var marker = L.marker([terminal.lat, terminal.lng]).addTo(driverTerminalMap);
                        marker.bindPopup('<strong>' + terminal.name + '</strong><br>' + [terminal.barangay, terminal.city].filter(Boolean).join(', '));
                        marker.on('click', function () {
                            var terminalSelect = document.getElementById('driver-terminal-select');
                            if (terminalSelect) {
                                terminalSelect.value = terminal.id;
                            }
                        });
                        driverTerminalMarkers.push(marker);
                        bounds.extend([terminal.lat, terminal.lng]);
                    });

                    if (bounds.isValid()) {
                        driverTerminalMap.fitBounds(bounds.pad(0.2));
                    }
                });
            });
        });
    }

    var terminalMapButton = document.getElementById('terminal-map-picker');
    var terminalMapModal = document.getElementById('terminalMapModal');
    var terminalPickerMap;
    var terminalPickerMarker;
    var terminalLatInput = document.getElementById('terminal_latitude');
    var terminalLngInput = document.getElementById('terminal_longitude');
    var terminalPickerCoordinates = document.getElementById('terminal-picker-coordinates');

    if (terminalMapButton && terminalMapModal) {
        terminalMapButton.addEventListener('click', function () {
            $('#terminalMapModal').modal('show');

            $('#terminalMapModal').off('shown.bs.modal').on('shown.bs.modal', function () {
                var mapContainer = document.getElementById('terminal-picker-map');
                if (!mapContainer) {
                    return;
                }

                var lat = terminalLatInput && terminalLatInput.value ? parseFloat(terminalLatInput.value) : 6.1164;
                var lng = terminalLngInput && terminalLngInput.value ? parseFloat(terminalLngInput.value) : 125.1716;

                if (!terminalPickerMap) {
                    terminalPickerMap = L.map('terminal-picker-map', {
                        center: [lat, lng],
                        zoom: 13,
                    });

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(terminalPickerMap);

                    terminalPickerMap.on('click', function (event) {
                        var point = event.latlng;
                        if (terminalPickerMarker) {
                            terminalPickerMap.removeLayer(terminalPickerMarker);
                        }
                        terminalPickerMarker = L.marker(point).addTo(terminalPickerMap);

                        if (terminalLatInput) {
                            terminalLatInput.value = point.lat.toFixed(6);
                        }
                        if (terminalLngInput) {
                            terminalLngInput.value = point.lng.toFixed(6);
                        }
                        if (terminalPickerCoordinates) {
                            terminalPickerCoordinates.textContent = point.lat.toFixed(6) + ', ' + point.lng.toFixed(6);
                        }
                    });
                }

                terminalPickerMap.setView([lat, lng], 13);
                terminalPickerMap.invalidateSize();

                if (terminalPickerMarker) {
                    terminalPickerMap.removeLayer(terminalPickerMarker);
                }

                if (terminalLatInput && terminalLngInput && terminalLatInput.value && terminalLngInput.value) {
                    terminalPickerMarker = L.marker([lat, lng]).addTo(terminalPickerMap);
                    if (terminalPickerCoordinates) {
                        terminalPickerCoordinates.textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
                    }
                }
            });
        });
    }
});
</script>
@stop
