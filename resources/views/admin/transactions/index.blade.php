@extends('adminlte::page')

@section('title', 'Logbook - RideGuide Admin')

@php
    $panelPrefix = request()->routeIs('super-admin.*') ? 'super-admin' : 'admin';
    $transactionsIndexRoute = $panelPrefix . '.transactions.index';
@endphp

@section('css')
    <style>
        body:not(.dark-mode) .rg-filter-bar button.rg-btn-search {
            background: #0d1b36 !important;
            border: 1px solid #0d1b36 !important;
            color: #fff !important;
            display: inline-flex !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        body.dark-mode .rg-filter-bar button.rg-btn-search {
            background: #60a5fa !important;
            border: 1px solid #60a5fa !important;
            color: #0b1220 !important;
        }
    </style>
@stop

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Logbook</h4>
            <p class="rg-page-subtitle">Track system activity across modules.</p>
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
                <h6 class="rg-card-title mb-0">Activities</h6>
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
                    @foreach(($modules ?? []) as $module)
                        <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>
                            {{ ucfirst(str_replace('_', ' ', $module)) }}
                        </option>
                    @endforeach
                </select>

                <select name="status" class="form-control form-control-sm mr-2" style="min-width: 140px;">
                    <option value="">All Status</option>
                    <option value="success" @selected(($filters['status'] ?? '') === 'success')>Success</option>
                    <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>Failed</option>
                </select>

                <input
                    type="date"
                    name="created_from"
                    id="created_from"
                    class="form-control form-control-sm mr-2"
                    style="min-width: 160px;"
                    value="{{ $filters['created_from'] ?? '' }}"
                    title="From date"
                >

                <input
                    type="date"
                    name="created_to"
                    id="created_to"
                    class="form-control form-control-sm mr-2"
                    style="min-width: 160px;"
                    value="{{ $filters['created_to'] ?? '' }}"
                    title="To date"
                >

                <button type="submit" class="rg-btn-search btn">
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
                            <th>Activity</th>
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

@section('js')
    <script>
        (function () {
            const fromInput = document.getElementById('created_from');
            const toInput = document.getElementById('created_to');

            if (!fromInput || !toInput) {
                return;
            }

            const syncDateBounds = () => {
                const fromValue = fromInput.value;
                const toValue = toInput.value;

                toInput.min = fromValue || '';

                if (fromValue && toValue && toValue < fromValue) {
                    toInput.value = fromValue;
                }
            };

            fromInput.addEventListener('change', syncDateBounds);
            toInput.addEventListener('change', syncDateBounds);

            syncDateBounds();
        })();
    </script>
@stop