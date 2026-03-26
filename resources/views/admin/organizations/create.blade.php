@extends('adminlte::page')

@section('title', 'Add Organization — RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Add Organization</h4>
            <p class="rg-page-subtitle">Create a new driver organization.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            
            <a href="{{ route('admin.organizations.index') }}" class="rg-btn rg-btn-secondary rg-btn-sm">
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
                </div>
                <div class="p-4">

                    @if($errors->any())
                    <div class="rg-alert rg-alert-danger mb-3">
                        <i class="fas fa-exclamation-circle"></i>
                        Please fix the errors below before saving.
                    </div>
                    @endif

                    <form method="POST" action="{{ route('admin.organizations.store') }}">
                        @csrf

                        <div class="rg-form-group">
                            <label class="rg-form-label" for="name">
                                Name <span class="rg-required">*</span>
                            </label>
                            <input id="name" name="name" type="text" list="name-options"
                                   class="rg-form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}"
                                   placeholder="e.g. TODA - Lagao Terminal"
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
                            <label class="rg-form-label" for="description">Description</label>
                            <textarea id="description" name="description" rows="3"
                                      class="rg-form-control @error('description') is-invalid @enderror"
                                      placeholder="Brief description of this organization…">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="rg-form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- ── Head Office Address ── --}}
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
                                                   value="{{ old('hq_street') }}"
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
                                                   value="{{ old('hq_barangay') }}"
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
                                                   value="{{ old('hq_subdivision') }}"
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
                                                   value="{{ old('hq_floor_unit_room') }}"
                                                   placeholder="e.g. Unit 3B (optional)">
                                            @error('hq_floor_unit_room')
                                                <p class="rg-form-error">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="rg-form-group mb-0">
                                            <label class="rg-form-label rg-form-label-sm" for="hq_lat">Latitude</label>
                                            <input id="hq_lat" name="hq_lat" type="text"
                                                   class="rg-form-control @error('hq_lat') is-invalid @enderror"
                                                   value="{{ old('hq_lat') }}"
                                                   placeholder="e.g. 6.1164 (optional)">
                                            @error('hq_lat')
                                                <p class="rg-form-error">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="rg-form-group mb-0">
                                            <label class="rg-form-label rg-form-label-sm" for="hq_lng">Longitude</label>
                                            <input id="hq_lng" name="hq_lng" type="text"
                                                   class="rg-form-control @error('hq_lng') is-invalid @enderror"
                                                   value="{{ old('hq_lng') }}"
                                                   placeholder="e.g. 125.1716 (optional)">
                                            @error('hq_lng')
                                                <p class="rg-form-error">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
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
                                    <option value="{{ $owner->id }}" {{ old('owner_user_id') === $owner->id ? 'selected' : '' }}>
                                        {{ trim($owner->first_name . ' ' . $owner->last_name) ?: $owner->email }} ({{ $owner->email }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="rg-form-hint">Only users with admin, super_admin, or organization role are listed.</p>
                            @error('owner_user_id')
                                <p class="rg-form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rg-form-actions">
                            <button type="submit" class="rg-btn rg-btn-primary">
                                <i class="fas fa-plus"></i> Add Organization
                            </button>
                            <a href="{{ route('admin.organizations.index') }}" class="rg-btn rg-btn-secondary">
                                Cancel
                            </a>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

@stop
