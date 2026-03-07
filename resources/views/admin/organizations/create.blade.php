@extends('adminlte::page')

@section('title', 'Add Organization — RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Add Organization</h4>
            <p class="rg-page-subtitle">Create a new driver organization.</p>
        </div>
        <a href="{{ route('admin.organizations.index') }}" class="rg-btn rg-btn-secondary rg-btn-sm">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
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
                            <input id="name" name="name" type="text"
                                   class="rg-form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}"
                                   placeholder="e.g. TODA - Lagao Terminal"
                                   required>
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
                                   value="{{ old('type') }}"
                                   placeholder="e.g. TODA, MODA"
                                   required>
                            <datalist id="type-options">
                                <option value="TODA">
                                <option value="MODA">
                            </datalist>
                            <p class="rg-form-hint">Select a suggestion or type a custom value.</p>
                            @error('type')
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

                        <div class="rg-form-group">
                            <label class="rg-form-label" for="address">Address</label>
                            <input id="address" name="address" type="text"
                                   class="rg-form-control @error('address') is-invalid @enderror"
                                   value="{{ old('address') }}"
                                   placeholder="e.g. Lagao, General Santos City">
                            @error('address')
                                <p class="rg-form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rg-form-group">
                            <label class="rg-form-label" for="contact_number">Contact Number</label>
                            <input id="contact_number" name="contact_number" type="text"
                                   class="rg-form-control @error('contact_number') is-invalid @enderror"
                                   value="{{ old('contact_number') }}"
                                   placeholder="e.g. 0912-345-6789">
                            @error('contact_number')
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
