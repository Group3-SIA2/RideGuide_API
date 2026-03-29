@extends('adminlte::page')

@section('title', 'Organization Types — RideGuide Admin')

@php
    $panelPrefix = request()->routeIs('super-admin.*') ? 'super-admin' : 'admin';
    $organizationsIndexRoute = $panelPrefix . '.organizations.index';
    $organizationTypesIndexRoute = $panelPrefix . '.organizations.types.index';
    $organizationTypesStoreRoute = $panelPrefix . '.organizations.types.store';
@endphp

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Organization Types</h4>
            <p class="rg-page-subtitle">Create and manage the list of allowed organization types.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="{{ route($organizationsIndexRoute) }}" class="rg-btn rg-btn-secondary rg-btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Organizations
            </a>
            <span class="rg-badge">{{ $organizationTypes->total() }} total</span>
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
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12 col-lg-5">
            <div class="rg-card mb-3">
                <div class="rg-card-header">
                    <div class="d-flex align-items-center">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">Create Organization Type</h6>
                    </div>
                </div>
                <div class="p-4">
                    <form method="POST" action="{{ route($organizationTypesStoreRoute) }}">
                        @csrf

                        <div class="rg-form-group">
                            <label class="rg-form-label" for="name">
                                Type Name <span class="rg-required">*</span>
                            </label>
                            <input id="name" name="name" type="text"
                                   class="rg-form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}"
                                   placeholder="e.g. TODA"
                                   required>
                            @error('name')
                                <p class="rg-form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rg-form-group">
                            <label class="rg-form-label" for="description">Description</label>
                            <textarea id="description" name="description" rows="4"
                                      class="rg-form-control @error('description') is-invalid @enderror"
                                      placeholder="Brief description for this organization type...">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="rg-form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rg-form-actions">
                            <button type="submit" class="rg-btn rg-btn-primary">
                                <i class="fas fa-plus"></i> Add Type
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="rg-card">
                <div class="rg-card-header">
                    <div class="d-flex align-items-center">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">Existing Organization Types</h6>
                    </div>
                </div>
                <div class="rg-card-body p-0">
                    <div class="table-responsive">
                        <table class="rg-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Organizations</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($organizationTypes as $type)
                                    <tr>
                                        <td>
                                            <span class="rg-role-badge">{{ $type->name }}</span>
                                        </td>
                                        <td class="rg-td-muted">{{ $type->description ?: '—' }}</td>
                                        <td class="rg-td-muted">{{ $type->organizations_count }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">No organization types yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($organizationTypes->hasPages())
                    <div class="rg-card-footer">
                        {{ $organizationTypes->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

@stop
