@extends('adminlte::page')

@section('title', 'Edit Organization — RideGuide Admin')

@php
    $panelPrefix = request()->routeIs('super-admin.*') ? 'super-admin' : 'admin';
    $organizationsIndexRoute = $panelPrefix . '.organizations.index';
    $organizationsUpdateRoute = $panelPrefix . '.organizations.update';
    $organizationTypesIndexRoute = $panelPrefix . '.organizations.types.index';
@endphp

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Edit Organization</h4>
            <p class="rg-page-subtitle">Update details for {{ $organization->name }}.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
           
            <a href="{{ route($organizationsIndexRoute) }}" class="rg-btn rg-btn-secondary rg-btn-sm">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>
@stop

@section('content')

    <div class="row">
        <div class="col-12 col-lg-7">
            <div class="rg-card">
                <div class="rg-card-header">
                    <div class="d-flex align-items-center">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">Organization Details</h6>
                    </div>
                    <span class="rg-status-badge {{ $organization->status === 'active' ? 'rg-status-active' : 'rg-status-pending' }}">
                        {{ ucfirst($organization->status) }}
                    </span>
                </div>
                <div class="p-4">

                    @if($errors->any())
                    <div class="rg-alert rg-alert-danger mb-3">
                        <i class="fas fa-exclamation-circle"></i>
                        Please fix the errors below before saving.
                    </div>
                    @endif

                    <form method="POST" action="{{ route($organizationsUpdateRoute, $organization->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="rg-form-group">
                            <label class="rg-form-label" for="name">
                                Name <span class="rg-required">*</span>
                            </label>
                            <input id="name" name="name" type="text" list="name-options"
                                   class="rg-form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $organization->name) }}"
                                   required>
                            <datalist id="name-options">
                                @foreach($existingNames as $existingName)
                                    <option value="{{ $existingName }}">
                                @endforeach
                            </datalist>
                            <p class="rg-form-hint">Choose an existing name or type a new one.</p>
                            @error('name')
                                <p class="rg-form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rg-form-group">
                            <label class="rg-form-label" for="organization_type_id">
                                Organization Type <span class="rg-required">*</span>
                            </label>
                            <select id="organization_type_id" name="organization_type_id"
                                   class="rg-form-control @error('organization_type_id') is-invalid @enderror"
                                   required>
                                <option value="">Select organization type</option>
                                @foreach($existingTypes as $existingType)
                                    <option value="{{ $existingType->id }}" {{ old('organization_type_id', $organization->organization_type_id) === $existingType->id ? 'selected' : '' }}>
                                        {{ $existingType->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="rg-form-hint">Only existing organization types can be selected.</p>
                            <p class="rg-form-hint">
                                Need a new type?
                                <a href="{{ route($organizationTypesIndexRoute) }}">Create organization type here</a>.
                            </p>
                            @error('organization_type_id')
                                <p class="rg-form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- ── Head Office Address ── --}}
                        @php
                            $addr = $organization->hqAddress;
                        @endphp
                        <div class="rg-form-group">
                            <label class="rg-form-label">
                                <i class="fas fa-map-marker-alt mr-1" style="color:var(--rg-accent);"></i>
                                Head Office Address
                            </label>
                            <div class="rg-address-block">
                                <div class="row">
                                    <div class="col-12 col-md-6">
                                        <div class="rg-form-group mb-2">
                                            <label class="rg-form-label rg-form-label-sm" for="hq_street">
                                                Street <span class="rg-required">*</span>
                                            </label>
                                            <input id="hq_street" name="hq_street" type="text"
                                                   class="rg-form-control @error('hq_street') is-invalid @enderror"
                                                   value="{{ old('hq_street', $addr->street ?? '') }}"
                                                   placeholder="e.g. J. Catolico Ave">
                                            @error('hq_street')
                                                <p class="rg-form-error">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="rg-form-group mb-2">
                                            <label class="rg-form-label rg-form-label-sm" for="hq_barangay">
                                                Barangay <span class="rg-required">*</span>
                                            </label>
                                            <input id="hq_barangay" name="hq_barangay" type="text"
                                                   class="rg-form-control @error('hq_barangay') is-invalid @enderror"
                                                   value="{{ old('hq_barangay', $addr->barangay ?? '') }}"
                                                   placeholder="e.g. Lagao">
                                            @error('hq_barangay')
                                                <p class="rg-form-error">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="rg-form-group mb-2">
                                            <label class="rg-form-label rg-form-label-sm" for="hq_subdivision">Subdivision</label>
                                            <input id="hq_subdivision" name="hq_subdivision" type="text"
                                                   class="rg-form-control @error('hq_subdivision') is-invalid @enderror"
                                                   value="{{ old('hq_subdivision', $addr->subdivision ?? '') }}"
                                                   placeholder="e.g. Heritage Homes (optional)">
                                            @error('hq_subdivision')
                                                <p class="rg-form-error">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="rg-form-group mb-2">
                                            <label class="rg-form-label rg-form-label-sm" for="hq_floor_unit_room">Floor / Unit / Room</label>
                                            <input id="hq_floor_unit_room" name="hq_floor_unit_room" type="text"
                                                   class="rg-form-control @error('hq_floor_unit_room') is-invalid @enderror"
                                                   value="{{ old('hq_floor_unit_room', $addr->floor_unit_room ?? '') }}"
                                                   placeholder="e.g. Unit 3B (optional)">
                                            @error('hq_floor_unit_room')
                                                <p class="rg-form-error">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                    <input id="hq_lat" name="hq_lat" type="hidden"
                                           value="{{ old('hq_lat', $addr->lat ?? '') }}">
                                    <input id="hq_lng" name="hq_lng" type="hidden"
                                           value="{{ old('hq_lng', $addr->lng ?? '') }}">
                                </div>
                                <div class="mt-3">
                                    <label class="rg-form-label rg-form-label-sm">Select Location on Map</label>
                                    <div id="hq-map" class="rg-map-modal"></div>
                                    <small class="text-muted">Click on the map to set latitude and longitude.</small>
                                </div>
                            </div>
                            <p class="rg-form-hint">Street and Barangay are required if you want to set a head office address.</p>
                        </div>

                        <div class="rg-form-group">
                            <label class="rg-form-label" for="owner_user_id">Owner User</label>
                            <select id="owner_user_id" name="owner_user_id"
                                    class="rg-form-control @error('owner_user_id') is-invalid @enderror">
                                <option value="">No Owner</option>
                                @foreach($eligibleOwners as $owner)
                                    <option value="{{ $owner->id }}" {{ old('owner_user_id', $organization->owner_user_id) === $owner->id ? 'selected' : '' }}>
                                        {{ trim($owner->first_name . ' ' . $owner->last_name) ?: $owner->email }} ({{ $owner->email }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="rg-form-hint">Only users with admin, super_admin, or organization role are listed.</p>
                            @error('owner_user_id')
                                <p class="rg-form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rg-form-group">
                            <label class="rg-form-label" for="status">Status <span class="rg-required">*</span></label>
                            <select id="status" name="status"
                                    class="rg-form-control @error('status') is-invalid @enderror">
                                <option value="active"   {{ old('status', $organization->status) === 'active'   ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status', $organization->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('status')
                                <p class="rg-form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rg-form-actions">
                            <button type="submit" class="rg-btn rg-btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <a href="{{ route($organizationsIndexRoute) }}" class="rg-btn rg-btn-secondary">
                                Cancel
                            </a>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

@stop

@section('css')
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        .rg-map-modal {
            height: 260px;
            width: 100%;
            border-radius: 10px;
            border: 1px solid rgba(0,0,0,.08);
            margin-top: 0.35rem;
        }
    </style>
@stop

@section('js')
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var mapContainer = document.getElementById('hq-map');
            if (!mapContainer) {
                return;
            }

            var latInput = document.getElementById('hq_lat');
            var lngInput = document.getElementById('hq_lng');
            var lat = latInput && latInput.value ? parseFloat(latInput.value) : 6.1164;
            var lng = lngInput && lngInput.value ? parseFloat(lngInput.value) : 125.1716;

            var hqMap = L.map('hq-map', {
                center: [lat, lng],
                zoom: 13,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(hqMap);

            var hqMarker = null;
            if (latInput && lngInput && latInput.value && lngInput.value) {
                hqMarker = L.circleMarker([lat, lng], {
                    radius: 7,
                    color: '#2563eb',
                    fillColor: '#3b82f6',
                    fillOpacity: 0.9,
                }).addTo(hqMap);
            }

            hqMap.on('click', function (event) {
                var point = event.latlng;
                if (hqMarker) {
                    hqMap.removeLayer(hqMarker);
                }
                hqMarker = L.marker(point).addTo(hqMap);

                if (latInput) {
                    latInput.value = point.lat.toFixed(6);
                }
                if (lngInput) {
                    lngInput.value = point.lng.toFixed(6);
                }
            });
        });
    </script>
@stop
