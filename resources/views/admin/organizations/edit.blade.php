@extends('adminlte::page')

@section('title', 'Edit Organization — RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Edit Organization</h4>
            <p class="rg-page-subtitle">Update details for {{ $organization->name }}.</p>
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

                    <form method="POST" action="{{ route('admin.organizations.update', $organization->id) }}">
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
                            <label class="rg-form-label" for="type">
                                Type <span class="rg-required">*</span>
                            </label>
                            <input id="type" name="type" type="text" list="type-options"
                                   class="rg-form-control @error('type') is-invalid @enderror"
                                   value="{{ old('type', $organization->type) }}"
                                   required>
                            <datalist id="type-options">
                                @foreach($existingTypes as $existingType)
                                    <option value="{{ $existingType }}">
                                @endforeach
                            </datalist>
                            <p class="rg-form-hint">Choose an existing type or type a custom value.</p>
                            @error('type')
                                <p class="rg-form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rg-form-group">
                            <label class="rg-form-label" for="description">Description</label>
                            <textarea id="description" name="description" rows="3"
                                      class="rg-form-control @error('description') is-invalid @enderror"
                                      placeholder="Brief description of this organization…">{{ old('description', $organization->description) }}</textarea>
                            @error('description')
                                <p class="rg-form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rg-form-group">
                            <label class="rg-form-label" for="hq_address">Head Office Address</label>
                            <input id="hq_address" name="hq_address" type="text"
                                   class="rg-form-control @error('hq_address') is-invalid @enderror"
                                   value="{{ old('hq_address', $organization->hq_address) }}"
                                   placeholder="e.g. Lagao, General Santos City">
                            @error('hq_address')
                                <p class="rg-form-error">{{ $message }}</p>
                            @enderror
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
