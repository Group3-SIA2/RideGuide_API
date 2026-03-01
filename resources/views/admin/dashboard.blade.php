@extends('adminlte::page')

@section('title', 'Dashboard — RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Dashboard</h4>
            <p class="rg-page-subtitle">Welcome back, {{ auth()->user()->first_name }}.</p>
        </div>
        <span class="rg-badge">{{ now()->format('l, F j Y') }}</span>
    </div>
@stop

@section('content')

    {{-- Stats Row --}}
    <div class="row">

        {{-- Total Users --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="rg-stat-card">
                <div class="rg-stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="rg-stat-body">
                    <p class="rg-stat-label">Total Users</p>
                    <h3 class="rg-stat-value">{{ number_format($totalVerifiedUsers) }}</h3>
                    <span class="rg-stat-sub">Verified accounts</span>
                </div>
            </div>
        </div>

        {{-- Commuters --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="rg-stat-card">
                <div class="rg-stat-icon">
                    <i class="fas fa-user-friends"></i>
                </div>
                <div class="rg-stat-body">
                    <p class="rg-stat-label">Commuters</p>
                    <h3 class="rg-stat-value">{{ number_format($totalCommuters) }}</h3>
                    <span class="rg-stat-sub">Registered commuters</span>
                </div>
            </div>
        </div>

        {{-- Drivers --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="rg-stat-card">
                <div class="rg-stat-icon">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="rg-stat-body">
                    <p class="rg-stat-label">Drivers</p>
                    <h3 class="rg-stat-value">{{ number_format($totalDrivers) }}</h3>
                    <span class="rg-stat-sub">{{ $totalDriverProfiles }} with profiles</span>
                </div>
            </div>
        </div>

        {{-- Admins --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="rg-stat-card">
                <div class="rg-stat-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="rg-stat-body">
                    <p class="rg-stat-label">Admins</p>
                    <h3 class="rg-stat-value">{{ number_format($totalAdmins) }}</h3>
                    <span class="rg-stat-sub">Panel administrators</span>
                </div>
            </div>
        </div>

    </div>

    {{-- Recent Users --}}
    <div class="row mt-2">
        <div class="col-12">
            <div class="rg-card">
                <div class="rg-card-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">Recent Users</h6>
                    </div>
                    <form id="rg-filter-form" method="GET" action="{{ route('admin.dashboard') }}" class="rg-filter-bar mt-2">
                        <input id="rg-search" type="text" name="search" class="rg-search-input" placeholder="Search name or email…" value="{{ request('search') }}">
                        <select id="rg-filter" name="role" class="rg-filter-select">
                            <option value="">All Roles</option>
                            @foreach(['admin', 'super_admin', 'driver', 'commuter'] as $r)
                                <option value="{{ $r }}" {{ request('role') === $r ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $r)) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="rg-btn-search"><i class="fas fa-search"></i> Search</button>
                        <button type="button" id="rg-clear" class="rg-btn-clear">Clear</button>
                    </form>
                </div>
                <div class="rg-card-body p-0">
                    <div class="table-responsive">
                        <table class="rg-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Joined</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="rg-table-body">
                                @forelse($recentUsers as $index => $user)
                                <tr>
                                    <td class="rg-td-index">{{ $index + 1 }}</td>
                                    <td>
                                        <div class="rg-user-cell">
                                            <div class="rg-avatar">
                                                {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <p class="rg-user-name mb-0">{{ $user->first_name }} {{ $user->last_name }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="rg-td-muted">{{ $user->email }}</td>
                                    <td>
                                        <span class="rg-role-badge">{{ ucfirst($user->role?->name ?? 'N/A') }}</span>
                                    </td>
                                    <td class="rg-td-muted">{{ $user->created_at->format('M d, Y') }}</td>
                                    <td>
                                        <span class="rg-status-badge {{ $user->email_verified_at ? 'rg-status-active' : 'rg-status-pending' }}">
                                            {{ $user->email_verified_at ? 'Verified' : 'Pending' }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="rg-empty">No users found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@stop

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var search = document.getElementById('rg-search');
    var filter = document.getElementById('rg-filter');
    var tbody  = document.getElementById('rg-table-body');
    var timer;

    function load() {
        var p = new URLSearchParams();
        if (search && search.value.trim()) p.set('search', search.value.trim());
        if (filter && filter.value)        p.set('role', filter.value);
        var qs = p.toString();
        history.replaceState(null, '', qs ? '?' + qs : window.location.pathname);
        tbody.style.opacity = '0.35';
        fetch(window.location.pathname + (qs ? '?' + qs : ''), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            tbody.innerHTML     = d.rows;
            tbody.style.opacity = '1';
        })
        .catch(function() { tbody.style.opacity = '1'; });
    }

    if (search) search.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(load, 350);
    });
    if (filter) filter.addEventListener('change', load);
    document.getElementById('rg-clear').addEventListener('click', function() {
        if (search) search.value = '';
        if (filter) filter.value = '';
        load();
    });
    document.getElementById('rg-filter-form').addEventListener('submit', function(e) {
        e.preventDefault();
        load();
    });
});
</script>
@stop
