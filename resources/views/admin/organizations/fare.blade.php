@extends('adminlte::page')

@section('title', 'Organization Fare Management - RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Organization Fare Management</h4>
            <p class="rg-page-subtitle">Set base fares, route fares, and review terminal locations.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            @php
                $panelPrefix = request()->routeIs('org-manager.*')
                    ? 'org-manager'
                    : (request()->routeIs('super-admin.*') ? 'super-admin' : 'admin');
                $dashboardRoute = $panelPrefix . '.organizations.manager-dashboard';
                $fareOverviewRoute = $panelPrefix . '.organizations.fares.overview';
                $overviewQuery = array_filter(['organization_id' => request('organization_id')]);
            @endphp
            <a href="{{ route($fareOverviewRoute, $overviewQuery) }}" class="rg-btn rg-btn-primary rg-btn-sm">
                <i class="fas fa-chart-line"></i> Fare Rate Overview
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
        $fareRoute = $panelPrefix . '.organizations.fares.index';
    $fareCreateRoute = $panelPrefix . '.organizations.fares.routes.store';
    $fareUpdateRoute = $panelPrefix . '.organizations.fares.routes.update';
        $assignmentsRoute = $panelPrefix . '.organizations.assignments.index';
        $selectedOrgId = $selectedOrganizationId ?? ($managedOrganization->id ?? null);
        $terminalMapData = ($assignedTerminals ?? collect())->map(function ($terminal) {
            return [
                'id' => $terminal->id,
                'name' => $terminal->terminal_name,
                'barangay' => $terminal->barangay,
                'city' => $terminal->city,
                'lat' => $terminal->latitude,
                'lng' => $terminal->longitude,
            ];
        })->values();
        $routeLineData = ($routeFares ?? collect())->map(function ($routeFare) {
            return [
                'id' => $routeFare->id,
                'origin_name' => $routeFare->originTerminal?->terminal_name,
                'destination_name' => $routeFare->destinationTerminal?->terminal_name,
                'origin_lat' => $routeFare->originTerminal?->latitude,
                'origin_lng' => $routeFare->originTerminal?->longitude,
                'destination_lat' => $routeFare->destinationTerminal?->latitude,
                'destination_lng' => $routeFare->destinationTerminal?->longitude,
            ];
        })->values();
    $fareUpdateRouteTemplate = route($fareUpdateRoute, ['routeFare' => '__ROUTE__']);
        $routeFareFormData = ($routeFares ?? collect())->map(function ($routeFare) {
            return [
                'id' => $routeFare->id,
                'origin_id' => $routeFare->origin_terminal_id,
                'destination_id' => $routeFare->destination_terminal_id,
                'base_fare_4KM' => $routeFare->fareRate?->base_fare_4KM,
                'per_km_rate' => $routeFare->fareRate?->per_km_rate,
                'route_standard_fare' => $routeFare->fareRate?->route_standard_fare,
                'effective_date' => optional($routeFare->fareRate?->effective_date)->format('Y-m-d'),
                'origin_lat' => $routeFare->originTerminal?->latitude,
                'origin_lng' => $routeFare->originTerminal?->longitude,
                'destination_lat' => $routeFare->destinationTerminal?->latitude,
                'destination_lng' => $routeFare->destinationTerminal?->longitude,
            ];
        })->values();
    $activeFare = $currentFareRate?->fareRate;
    $activeOriginTerminalId = $currentFareRate?->origin_terminal_id;
    $activeDestinationTerminalId = $currentFareRate?->destination_terminal_id;
    @endphp

    @if(session('success'))
        <div class="rg-alert rg-alert-success mb-3">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger mb-3">{{ session('error') }}</div>
    @endif

    @if(($organizationsForAdmin ?? collect())->isNotEmpty())
        <div class="row mb-3">
            <div class="col-12">
                <div class="rg-card">
                    <div class="rg-card-header">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rg-card-dot"></span>
                            <h6 class="rg-card-title mb-0">Select Organization</h6>
                        </div>
                        <form method="GET" action="{{ route($fareRoute) }}" class="rg-filter-bar mt-2">
                            <select name="organization_id" class="rg-filter-select" required onchange="this.form.submit()" aria-label="Select organization">
                                <option value="">Select Organization</option>
                                @foreach($organizationsForAdmin as $adminOrg)
                                    <option value="{{ $adminOrg->id }}" {{ ($selectedOrganizationId ?? '') === $adminOrg->id ? 'selected' : '' }}>
                                        {{ $adminOrg->name }}
                                    </option>
                                @endforeach
                            </select>
                            <a href="{{ route($fareRoute) }}" class="rg-btn-clear">Clear</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(!$managedOrganization)
        <div class="alert alert-warning">
            @if(($organizationsForAdmin ?? collect())->isNotEmpty())
                Please select an organization to manage fare rates.
            @else
                No organization is currently assigned to your account. Please contact an administrator.
            @endif
        </div>
    @else
        <div class="row g-3 g-xl-4">
            <div class="col-12 col-xl-5">
                <div class="rg-card p-3" id="fare-rate-form">
                    <div class="rg-card-header d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rg-card-dot"></span>
                            <h6 class="rg-card-title mb-0">Fare Rate Form</h6>
                        </div>
                        <a href="{{ route($assignmentsRoute, array_filter(['organization_id' => $selectedOrgId])) }}" class="rg-btn rg-btn-secondary rg-btn-sm">
                            <i class="fas fa-user-plus"></i> Assign Drivers
                        </a>
                    </div>
                    <div class="rg-card-body">
                        <form method="POST" action="{{ route($fareCreateRoute) }}" data-create-action="{{ route($fareCreateRoute) }}" data-update-template="{{ $fareUpdateRouteTemplate }}">
                            @csrf
                            @if(!empty($selectedOrgId))
                                <input type="hidden" name="organization_id" value="{{ $selectedOrgId }}">
                            @endif
                            <input type="hidden" name="_method" value="POST">

                            <div class="form-group mb-3">
                                <label for="route_fare_id">Route Mode</label>
                                <select id="route_fare_id" class="form-control">
                                    <option value="">Create new route</option>
                                    @foreach($routeFares as $routeFare)
                                        <option value="{{ $routeFare->id }}">
                                            Update: {{ $routeFare->originTerminal?->terminal_name ?? 'Any terminal' }}
                                            →
                                            {{ $routeFare->destinationTerminal?->terminal_name ?? 'Any terminal' }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Choose a route to update; leave blank to create a new one.</small>
                            </div>

                            <div class="form-group mb-3">
                                <label for="origin_terminal_id">Origin Terminal (Point A)</label>
                                <select id="origin_terminal_id" name="origin_terminal_id" class="form-control">
                                    <option value="">No specific terminal</option>
                                    @foreach($assignedTerminals as $terminal)
                                        <option value="{{ $terminal->id }}" {{ old('origin_terminal_id') === $terminal->id ? 'selected' : '' }}>
                                            {{ $terminal->terminal_name }} ({{ $terminal->barangay }}, {{ $terminal->city }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Pick Point A here or click a marker on the map. First click sets Point A.</small>
                            </div>

                            <div class="form-group mb-3">
                                <label for="destination_terminal_id">Destination Terminal (Point B)</label>
                                <select id="destination_terminal_id" name="destination_terminal_id" class="form-control">
                                    <option value="">No destination terminal</option>
                                    @foreach($assignedTerminals as $terminal)
                                        <option value="{{ $terminal->id }}" {{ old('destination_terminal_id') === $terminal->id ? 'selected' : '' }}>
                                            {{ $terminal->terminal_name }} ({{ $terminal->barangay }}, {{ $terminal->city }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Second map click sets Point B.</small>
                            </div>

                            <div class="form-group mb-3">
                                <label for="base_fare_4KM">Base Fare (first 4 km)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="base_fare_4KM" name="base_fare_4KM"
                                       value="{{ old('base_fare_4KM') }}" required>
                            </div>

                            <div class="form-group mb-3">
                                <label for="per_km_rate">Per KM Rate</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="per_km_rate" name="per_km_rate"
                                       value="{{ old('per_km_rate') }}" required>
                            </div>

                            <div class="form-group mb-3">
                                <label for="route_standard_fare">Terminal-to-Terminal Fare</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="route_standard_fare" name="route_standard_fare"
                                       value="{{ old('route_standard_fare') }}" required>
                            </div>

                            <div class="form-group mb-3">
                                <label for="effective_date">Effective Date</label>
                                <input type="date" class="form-control" id="effective_date" name="effective_date"
                                       value="{{ old('effective_date', now()->format('Y-m-d')) }}" required>
                            </div>

                            <button type="submit" class="rg-btn rg-btn-primary">
                                <i class="fas fa-save"></i> Save Fare Rate
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-7">
                <div class="rg-card">
                    <div class="rg-card-header d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rg-card-dot"></span>
                            <h6 class="rg-card-title mb-0">Organization Terminals</h6>
                        </div>
                        <span class="rg-badge">{{ ($assignedTerminals ?? collect())->count() }} total</span>
                    </div>
                    <div class="rg-card-body">
                        <div id="org-fare-map" class="rg-map"></div>
                        @if(($assignedTerminals ?? collect())->isEmpty())
                            <p class="rg-empty mt-3">No terminals linked yet.</p>
                        @else
                            <div class="table-responsive mt-3">
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
                            <div class="mt-4">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h6 class="rg-card-title mb-0">Existing Route Fares</h6>
                                    <span class="rg-badge">{{ ($routeFares ?? collect())->count() }} total</span>
                                </div>
                                @if(($routeFares ?? collect())->isEmpty())
                                    <p class="rg-empty mt-2">No route fares added yet.</p>
                                @else
                                    <div class="table-responsive mt-2">
                                        <table class="rg-table mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Origin</th>
                                                    <th>Destination</th>
                                                    <th>Base Fare</th>
                                                    <th>Per KM</th>
                                                    <th>Terminal Fare</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($routeFares as $routeFare)
                                                    <tr>
                                                        <td>{{ $routeFare->originTerminal?->terminal_name ?? 'Any terminal' }}</td>
                                                        <td>{{ $routeFare->destinationTerminal?->terminal_name ?? 'Any terminal' }}</td>
                                                        <td>{{ number_format((float) optional($routeFare->fareRate)->base_fare_4KM, 2) }}</td>
                                                        <td>{{ number_format((float) optional($routeFare->fareRate)->per_km_rate, 2) }}</td>
                                                        <td>{{ number_format((float) optional($routeFare->fareRate)->route_standard_fare, 2) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
@stop

@section('css')
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        .rg-map {
            height: 320px;
            width: 100%;
            border-radius: 12px;
        }
    </style>
@stop

@section('js')
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var mapElement = document.getElementById('org-fare-map');
            if (!mapElement) {
                return;
            }

            var terminals = @json($terminalMapData);
            var routeLines = @json($routeLineData);
            var routeFareLookup = @json($routeFareFormData);
            var center = [6.1164, 125.1716];
            if (terminals.length && terminals[0].lat && terminals[0].lng) {
                center = [terminals[0].lat, terminals[0].lng];
            }

            var map = L.map('org-fare-map', {
                center: center,
                zoom: 13,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            var bounds = L.latLngBounds();
            var terminalSelect = document.getElementById('origin_terminal_id');
            var destinationSelect = document.getElementById('destination_terminal_id');
            var routeModeSelect = document.getElementById('route_fare_id');
            var fareForm = routeModeSelect ? routeModeSelect.closest('form') : null;
            var methodInput = fareForm ? fareForm.querySelector('input[name="_method"]') : null;
            var baseFareInput = document.getElementById('base_fare_4KM');
            var perKmInput = document.getElementById('per_km_rate');
            var terminalFareInput = document.getElementById('route_standard_fare');
            var effectiveDateInput = document.getElementById('effective_date');
            var terminalMarkers = {};
            var terminalPoints = {};
            var routeLine = null;
            var updateRouteLine = null;
            var existingRouteLayers = [];
            var routeColors = ['#2563eb', '#10b981', '#f97316', '#a855f7', '#ef4444', '#0ea5e9', '#facc15'];

            terminals.forEach(function (terminal) {
                if (!terminal.lat || !terminal.lng) {
                    return;
                }
                var locationLabel = [terminal.barangay, terminal.city].filter(Boolean).join(', ');
                var marker = L.marker([terminal.lat, terminal.lng])
                    .addTo(map)
                    .bindPopup('<strong>' + terminal.name + '</strong><br>' + (locationLabel || 'Location not set'));
                marker.on('click', function () {
                    if (terminalSelect && !terminalSelect.value) {
                        terminalSelect.value = terminal.id;
                        updateRouteLine();
                        return;
                    }

                    if (destinationSelect && !destinationSelect.value) {
                        destinationSelect.value = terminal.id;
                        updateRouteLine();
                        return;
                    }

                    if (terminalSelect) {
                        terminalSelect.value = terminal.id;
                        if (destinationSelect) {
                            destinationSelect.value = '';
                        }
                        updateRouteLine();
                    }
                });
                terminalMarkers[terminal.id] = marker;
                terminalPoints[terminal.id] = [terminal.lat, terminal.lng];
                bounds.extend([terminal.lat, terminal.lng]);
            });

            routeLines.forEach(function (route, index) {
                if (!route.origin_lat || !route.origin_lng || !route.destination_lat || !route.destination_lng) {
                    return;
                }

                var color = routeColors[index % routeColors.length];
                var line = L.polyline([
                    [route.origin_lat, route.origin_lng],
                    [route.destination_lat, route.destination_lng]
                ], {
                    color: color,
                    weight: 3,
                    opacity: 0.8,
                    dashArray: '6, 8'
                }).addTo(map);

                line.bindTooltip((route.origin_name || 'Any terminal') + ' → ' + (route.destination_name || 'Any terminal'));
                existingRouteLayers.push(line);
                bounds.extend([route.origin_lat, route.origin_lng]);
                bounds.extend([route.destination_lat, route.destination_lng]);
            });

            function updateRouteLine() {
                if (routeLine) {
                    map.removeLayer(routeLine);
                    routeLine = null;
                }

                var originId = terminalSelect ? terminalSelect.value : '';
                var destinationId = destinationSelect ? destinationSelect.value : '';

                if (!originId || !destinationId) {
                    return;
                }

                var originPoint = terminalPoints[originId];
                var destinationPoint = terminalPoints[destinationId];

                if (!originPoint || !destinationPoint) {
                    return;
                }

                routeLine = L.polyline([originPoint, destinationPoint], {
                    color: '#2563eb',
                    weight: 4,
                    opacity: 0.85
                }).addTo(map);

                map.fitBounds(L.latLngBounds([originPoint, destinationPoint]).pad(0.25));
            }

            function updateSelectedRouteLine(originId, destinationId) {
                if (updateRouteLine) {
                    map.removeLayer(updateRouteLine);
                    updateRouteLine = null;
                }

                if (!originId || !destinationId) {
                    return;
                }

                var originPoint = terminalPoints[originId];
                var destinationPoint = terminalPoints[destinationId];

                if (!originPoint || !destinationPoint) {
                    return;
                }

                updateRouteLine = L.polyline([originPoint, destinationPoint], {
                    color: '#0f172a',
                    weight: 5,
                    opacity: 0.9
                }).addTo(map);

                map.fitBounds(L.latLngBounds([originPoint, destinationPoint]).pad(0.25));
            }

            function focusMarker(id) {
                var marker = terminalMarkers[id];
                if (marker) {
                    marker.openPopup();
                    map.panTo(marker.getLatLng());
                }
            }

            if (terminalSelect) {
                terminalSelect.addEventListener('change', function () {
                    focusMarker(terminalSelect.value);
                    updateRouteLine();
                });
            }

            if (destinationSelect) {
                destinationSelect.addEventListener('change', function () {
                    focusMarker(destinationSelect.value);
                    updateRouteLine();
                });
            }

            if (routeModeSelect) {
                routeModeSelect.addEventListener('change', function () {
                    var selected = routeFareLookup.find(function (route) {
                        return String(route.id) === String(routeModeSelect.value);
                    });

                    if (fareForm && fareForm.dataset.updateTemplate && fareForm.dataset.createAction) {
                        if (routeModeSelect.value) {
                            fareForm.action = fareForm.dataset.updateTemplate.replace('__ROUTE__', routeModeSelect.value);
                            if (methodInput) {
                                methodInput.value = 'PUT';
                            }
                        } else {
                            fareForm.action = fareForm.dataset.createAction;
                            if (methodInput) {
                                methodInput.value = 'POST';
                            }
                        }
                    }

                    if (!selected) {
                        if (terminalSelect) {
                            terminalSelect.value = '';
                        }
                        if (destinationSelect) {
                            destinationSelect.value = '';
                        }
                        if (baseFareInput) {
                            baseFareInput.value = '';
                        }
                        if (perKmInput) {
                            perKmInput.value = '';
                        }
                        if (terminalFareInput) {
                            terminalFareInput.value = '';
                        }
                        if (effectiveDateInput) {
                            effectiveDateInput.value = '';
                        }
                        updateSelectedRouteLine('', '');
                        updateRouteLine();
                        return;
                    }

                    if (terminalSelect) {
                        terminalSelect.value = selected.origin_id || '';
                    }
                    if (destinationSelect) {
                        destinationSelect.value = selected.destination_id || '';
                    }
                    if (baseFareInput) {
                        baseFareInput.value = selected.base_fare_4KM ?? '';
                    }
                    if (perKmInput) {
                        perKmInput.value = selected.per_km_rate ?? '';
                    }
                    if (terminalFareInput) {
                        terminalFareInput.value = selected.route_standard_fare ?? '';
                    }
                    if (effectiveDateInput) {
                        effectiveDateInput.value = selected.effective_date ?? '';
                    }

                    focusMarker(selected.origin_id);
                    updateSelectedRouteLine(selected.origin_id, selected.destination_id);
                });
            }

            if (routeModeSelect) {
                var params = new URLSearchParams(window.location.search);
                var routeId = params.get('route_fare_id');
                if (routeId) {
                    routeModeSelect.value = routeId;
                    routeModeSelect.dispatchEvent(new Event('change'));
                }
            }

            if (bounds.isValid()) {
                map.fitBounds(bounds.pad(0.2));
            }

            updateRouteLine();
        });
    </script>
@stop
