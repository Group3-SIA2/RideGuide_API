@extends('adminlte::page')

@section('title', 'Logbook - RideGuide Admin')

@php
    $panelPrefix = request()->routeIs('super-admin.*') ? 'super-admin' : 'admin';
    $transactionsIndexRoute = $panelPrefix . '.transactions.index';
@endphp

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Logbook</h4>
            <p class="rg-page-subtitle">Track admin-side transaction changes across modules.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="rg-badge">{{ $logs->total() }} record{{ $logs->total() !== 1 ? 's' : '' }}</span>
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

    <div class="rg-card">
        <div class="rg-card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div class="d-flex align-items-center">
                <span class="rg-card-dot"></span>
                <h6 class="rg-card-title mb-0">Transactions</h6>
            </div>

            <form class="rg-filter-bar mt-3 mt-md-0" method="GET" action="{{ route($transactionsIndexRoute) }}">
                <input
                    type="text"
                    name="search"
                    class="rg-search-input"
                    placeholder="Search actor, type, module, reference..."
                    value="{{ $filters['search'] ?? '' }}"
                >

                <select name="module" class="form-control form-control-sm mr-2" style="min-width: 160px;">
                    <option value="">All Modules</option>
                    <option value="users" @selected(($filters['module'] ?? '') === 'users')>Users</option>
                    <option value="organizations" @selected(($filters['module'] ?? '') === 'organizations')>Organizations</option>
                    <option value="authorization" @selected(($filters['module'] ?? '') === 'authorization')>Authorization</option>
                    <option value="status_dashboard" @selected(($filters['module'] ?? '') === 'status_dashboard')>Status Dashboard</option>
                    <option value="backups" @selected(($filters['module'] ?? '') === 'backups')>Backups</option>
                </select>

                <select name="status" class="form-control form-control-sm mr-2" style="min-width: 140px;">
                    <option value="">All Status</option>
                    <option value="success" @selected(($filters['status'] ?? '') === 'success')>Success</option>
                    <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>Failed</option>
                </select>

                <button type="submit" class="rg-btn-search">
                    <i class="fas fa-search"></i>
                    <span>Filter</span>
                </button>
            </form>
        </div>

        <div class="rg-card-body p-0">
            <div class="table-responsive">
                <table class="rg-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Actor</th>
                            <th>Module</th>
                            <th>Transaction</th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @include('admin.transactions._rows')
                    </tbody>
                </table>
            </div>
        </div>

        @if($logs->hasPages())
            <div class="rg-card-footer">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
@stop