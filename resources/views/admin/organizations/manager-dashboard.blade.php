@extends('adminlte::page')

@section('title', 'Organization Dashboard - RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Organization Dashboard</h4>
            <p class="rg-page-subtitle">Overview of your organization and driver assignments.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            @php
                $panelPrefix = request()->routeIs('org-manager.*')
                    ? 'org-manager'
                    : (request()->routeIs('super-admin.*') ? 'super-admin' : 'admin');
                $assignmentsRoute = $panelPrefix . '.organizations.assignments.index';
            @endphp
            <a href="{{ route($assignmentsRoute, array_filter(['organization_id' => $selectedOrganizationId ?? null])) }}" class="rg-btn rg-btn-primary rg-btn-sm">
                <i class="fas fa-user-plus"></i> Manage Driver Assignments
            </a>
        </div>
    </div>
@stop

@section('content')
    @php
        $panelPrefix = request()->routeIs('org-manager.*')
            ? 'org-manager'
            : (request()->routeIs('super-admin.*') ? 'super-admin' : 'admin');
        $dashboardRoute = $panelPrefix . '.organizations.manager-dashboard';
        $assignmentsRoute = $panelPrefix . '.organizations.assignments.index';
        $assignmentsQuery = array_filter([
            'organization_id' => $selectedOrganizationId ?? ($managedOrganization->id ?? null),
        ]);
        $terminalMapData = collect();
        $hqAddressData = null;
    @endphp

    @if(($organizationsForAdmin ?? collect())->isNotEmpty())
        <div class="row mb-3">
            <div class="col-12">
                <div class="rg-card">
                    <div class="rg-card-header">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rg-card-dot"></span>
                            <h6 class="rg-card-title mb-0">Select Organization</h6>
                        </div>
                        <form method="GET" action="{{ route($dashboardRoute) }}" class="rg-filter-bar mt-2" id="organization-dashboard-filter-form">
                            <select name="organization_id" class="rg-filter-select" required onchange="this.form.submit()" aria-label="Select organization">
                                <option value="">Select Organization</option>
                                @foreach($organizationsForAdmin as $adminOrg)
                                    <option value="{{ $adminOrg->id }}" {{ ($selectedOrganizationId ?? '') === $adminOrg->id ? 'selected' : '' }}>
                                        {{ $adminOrg->name }}
                                    </option>
                                @endforeach
                            </select>
                            <a href="{{ route($dashboardRoute) }}" class="rg-btn-clear">Clear</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($managedOrganization)
        @php
            $terminalMapData = ($assignedTerminals ?? collect())->map(function ($terminal) {
                $drivers = $terminal->driverAssignments ?? collect();

                return [
                    'id' => $terminal->id,
                    'name' => $terminal->terminal_name,
                    'lat' => $terminal->latitude,
                    'lng' => $terminal->longitude,
                    'barangay' => $terminal->barangay,
                    'city' => $terminal->city,
                    'drivers_count' => $drivers->filter(fn ($assignment) => (bool) $assignment->driver)->count(),
                ];
            })->values();

            $hqAddressData = null;
            if ($managedOrganization?->hqAddress) {
                $hqAddressData = [
                    'lat' => $managedOrganization->hqAddress->lat,
                    'lng' => $managedOrganization->hqAddress->lng,
                    'formatted' => $managedOrganization->hqAddress->formatted,
                ];
            }
        @endphp

        <div class="row mb-4">
            <div class="col-12">
                <div class="rg-card">
                    <div class="rg-card-header d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rg-card-dot"></span>
                            <h6 class="rg-card-title mb-0">Organization Map</h6>
                        </div>
                        <span class="rg-badge">{{ ($assignedTerminals ?? collect())->count() }} terminals</span>
                    </div>
                    <div class="rg-card-body">
                        <div id="organization-map" aria-label="Organization terminals and HQ address"></div>
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
        <div class="row align-items-stretch g-3 g-xl-4 mb-2">
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
                    <div class="rg-stat-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="rg-stat-body">
                        <p class="rg-stat-label">Assigned Terminals</p>
                        <h3 class="rg-stat-value">{{ number_format($totalAssignedTerminals) }}</h3>
                        <span class="rg-stat-sub">Linked to this organization</span>
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

        <div class="row mt-4 g-3 g-xl-4">
            <div class="col-12 col-xl-5 mb-3 mb-xl-0">
                <div class="rg-card h-100">
                    <div class="rg-card-header d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rg-card-dot"></span>
                            <h6 class="rg-card-title mb-0">Assigned Terminals</h6>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="rg-badge">{{ $assignedTerminals->count() }} total</span>
                            <a href="{{ route($assignmentsRoute, $assignmentsQuery) }}" class="rg-btn rg-btn-secondary rg-btn-sm" title="Open Driver Assignments for selected organization">
                                <i class="fas fa-external-link-alt"></i> Assign a Terminal
                            </a>
                        </div>
                    </div>
                    <div class="rg-card-body p-0">
                        @if($assignedTerminals->isEmpty())
                            <p class="rg-empty mb-0 p-3">No terminals linked yet.</p>
                        @else
                            <div class="table-responsive">
                                <table class="rg-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Location</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($assignedTerminals as $terminal)
                                            <tr>
                                                <td>{{ $terminal->terminal_name }}</td>
                                                <td class="rg-td-muted">{{ $terminal->barangay }}, {{ $terminal->city }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-7 mb-3 mb-xl-0">
                <div class="rg-card">
                    <div class="rg-card-header d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rg-card-dot"></span>
                            <h6 class="rg-card-title mb-0">Recently Assigned Drivers</h6>
                        </div>
                        <a href="{{ route($assignmentsRoute, $assignmentsQuery) }}" class="rg-btn rg-btn-secondary rg-btn-sm" title="Open Driver Assignments for selected organization">
                            <i class="fas fa-external-link-alt"></i> Assign a Driver
                        </a>
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

@section('css')
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        #organization-map {
            height: 360px;
            width: 100%;
            border-radius: 12px;
        }

        .rg-map-list {
            margin: 0.5rem 0 0;
            padding-left: 1.1rem;
        }

        .rg-map-empty {
            margin: 0.5rem 0 0;
            color: #6c757d;
        }
    </style>
@stop

@section('js')
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var mapElement = document.getElementById('organization-map');

            if (!mapElement) {
                return;
            }

            var hqAddress = @json($hqAddressData);
            var terminals = @json($terminalMapData);

            var fallbackCenter = [6.1164, 125.1716];
            var initialCenter = fallbackCenter;

            if (hqAddress && hqAddress.lat && hqAddress.lng) {
                initialCenter = [hqAddress.lat, hqAddress.lng];
            } else if (terminals.length && terminals[0].lat && terminals[0].lng) {
                initialCenter = [terminals[0].lat, terminals[0].lng];
            }

            var map = L.map('organization-map', {
                center: initialCenter,
                zoom: 13,
                dragging: true,
                scrollWheelZoom: true,
                doubleClickZoom: true,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            var bounds = L.latLngBounds();

            if (hqAddress && hqAddress.lat && hqAddress.lng) {
                var hqMarker = L.circleMarker([hqAddress.lat, hqAddress.lng], {
                    radius: 7,
                    color: '#2563eb',
                    fillColor: '#3b82f6',
                    fillOpacity: 0.9,
                });

                hqMarker.addTo(map)
                    .bindPopup('<strong>HQ Address</strong><br>' + (hqAddress.formatted || 'No address details'));

                bounds.extend([hqAddress.lat, hqAddress.lng]);
            }

            terminals.forEach(function (terminal) {
                if (!terminal.lat || !terminal.lng) {
                    return;
                }

                var driverCount = Number(terminal.drivers_count || 0);
                var driverSummary = driverCount
                    ? '<p class="rg-map-empty">' + driverCount + ' assigned driver' + (driverCount === 1 ? '' : 's') + '.</p>'
                    : '<p class="rg-map-empty">No assigned drivers.</p>';

                var locationLabel = [terminal.barangay, terminal.city].filter(Boolean).join(', ');
                var popupContent = '<strong>' + terminal.name + '</strong><br>' +
                    '<span class="rg-td-muted">' + (locationLabel || 'Location not set') + '</span>' +
                    '<div class="mt-2"><strong>Assigned Drivers</strong>' + driverSummary + '</div>';

                L.marker([terminal.lat, terminal.lng])
                    .addTo(map)
                    .bindPopup(popupContent);

                bounds.extend([terminal.lat, terminal.lng]);
            });

            if (bounds.isValid()) {
                map.fitBounds(bounds.pad(0.15));
            }
        });
    </script>
@stop
