@extends('adminlte::page')

@section('title', 'Dashboard — RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Dashboard</h4>
            <p class="rg-page-subtitle">Welcome back, {{ trim((auth()->user()->first_name ?? '') . ' ' . (auth()->user()->last_name ?? '')) }}.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="rg-badge">{{ now()->format('l, F j Y') }}</span>
        </div>
    </div>
@stop

@section('content')

    @php
        $dashboardRoute = request()->routeIs('super-admin.*') ? 'super-admin.dashboard' : 'admin.dashboard';
        $usersIndexRoute = request()->routeIs('super-admin.*') ? 'super-admin.users.index' : 'admin.users.index';
    @endphp

    {{-- Stats Row --}}
    <div class="row align-items-stretch">
        {{-- Total Users --}}
        <div class="col-12 col-sm-6 col-xl-3 mb-3">
            <a href="{{ route($usersIndexRoute) }}" class="rg-stat-card rg-stat-card-equal h-100 text-decoration-none text-reset d-block">
                <div class="rg-stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="rg-stat-body">
                    <p class="rg-stat-label">Total Users</p>
                    <h3 class="rg-stat-value">{{ number_format($totalVerifiedUsers) }}</h3>
                    <span class="rg-stat-sub">Verified accounts</span>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        <span class="rg-status-badge rg-status-active">Active: {{ number_format($totalActiveUsers) }}</span>
                        <span class="rg-status-badge rg-status-pending">Inactive: {{ number_format($totalInactiveUsers) }}</span>
                        <span class="rg-status-badge rg-status-error">Suspended: {{ number_format($totalSuspendedUsers) }}</span>
                    </div>
                </div>
            </a>
        </div>
        {{-- Total Terminals --}}
        <div class="col-12 col-sm-6 col-xl-3 mb-3">
            <div class="rg-stat-card rg-stat-card-equal h-100">
                <div class="rg-stat-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="rg-stat-body">
                    <p class="rg-stat-label">Total Terminals</p>
                    <h3 class="rg-stat-value">{{ number_format($totalTerminals) }}</h3>
                    <span class="rg-stat-sub">Registered terminals</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Dynamic Role Cards Row --}}
    <div class="row align-items-stretch mt-5">
        <div class="col-12 mb-3">
            <h4 class="rg-page-title">User Roles</h4>
        </div>
        @foreach($allRoles as $role)
            <div class="col-12 col-sm-6 col-xl-3 mb-3">
                    <a
                        href="{{ route($dashboardRoute, ['role' => $role->name]) }}"
                        class="rg-stat-card rg-stat-card-equal h-100 text-decoration-none text-reset d-block rg-role-card"
                        data-role="{{ $role->name }}"
                        data-role-label="{{ ucfirst(str_replace('_', ' ', $role->name)) }}"
                    >
                    <div class="rg-stat-icon">
                        <i class="fas fa-user-tag"></i>
                    </div>
                    <div class="rg-stat-body">
                        <p class="rg-stat-label">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</p>
                        <h3 class="rg-stat-value">{{ number_format($roleCounts[$role->name] ?? 0) }}</h3>
                        <span class="rg-stat-sub">{{ $role->description ?? 'Users with this role' }}</span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="modal fade" id="rg-role-modal" tabindex="-1" role="dialog" aria-labelledby="rg-role-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rg-role-modal-title">Role Users</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="rg-table mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody id="rg-role-modal-body">
                                <tr>
                                    <td colspan="2" class="rg-empty">Click a role card to load users.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Leaflet Map for General Santos City --}}
    <div class="row mt-4">
        <div class="col-12">
            <div class="rg-card">
                <div class="rg-card-header">
                    <h6 class="rg-card-title mb-0">Terminals</h6>
                </div>
                <div class="rg-card-body" style="height: 400px;">
                    <div id="gensan-map" style="width: 100%; height: 350px; border-radius: 8px;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Users --}}
    <div class="row mt-2 w-100">
        <div class="col-12">
            <div class="rg-card">
                <div class="rg-card-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">Recent Users</h6>
                        @if(!empty($selectedRole))
                            <span class="rg-status-badge rg-status-active">Role: {{ ucfirst(str_replace('_', ' ', $selectedRole)) }}</span>
                        @endif
                    </div>
                    <form id="rg-filter-form" method="GET" action="{{ route($dashboardRoute) }}" class="rg-filter-bar mt-2">
                        <input id="rg-search" type="text" name="search" class="rg-search-input" placeholder="Search name or email…" value="{{ request('search') }}">
                        <select id="rg-filter" name="role" class="rg-filter-select">
                            <option value="">All Roles</option>
                            @foreach($allRoles as $role)
                                <option value="{{ $role->name }}" {{ request('role') === $role->name ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                </option>
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
                                    <th>Name</th>
                                    <th>Email</th>
                                    @if(empty($selectedRole))
                                        <th>Role</th>
                                        <th>Joined</th>
                                        <th>Status</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody id="rg-table-body">
                                @include('admin.dashboard._recent_rows', ['recentUsers' => $recentUsers, 'selectedRole' => $selectedRole ?? null])
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@stop

@section('css')
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
@stop

@section('js')
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
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
            tbody.innerHTML = d.rows;
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

        var roleCards = document.querySelectorAll('.rg-role-card');
        var roleModalBody = document.getElementById('rg-role-modal-body');
        var roleModalTitle = document.getElementById('rg-role-modal-title');

        roleCards.forEach(function(card) {
            card.addEventListener('click', function(e) {
                e.preventDefault();
                var role = card.getAttribute('data-role');
                var roleLabel = card.getAttribute('data-role-label') || role;
                loadRoleUsers(role, roleLabel, card.getAttribute('href'));
            });
        });

        function escapeHtml(value) {
            return String(value).replace(/[&<>\"']/g, function (s) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                }[s];
            });
        }

        function loadRoleUsers(role, roleLabel, fallbackHref) {
            if (!roleModalBody || !roleModalTitle) {
                window.location.href = fallbackHref;
                return;
            }

            roleModalTitle.textContent = roleLabel + ' Users';
            roleModalBody.innerHTML = '<tr><td colspan="2" class="rg-empty">Loading users...</td></tr>';

            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
                window.jQuery('#rg-role-modal').modal('show');
            } else {
                window.location.href = fallbackHref;
                return;
            }

            var p = new URLSearchParams();
            p.set('role', role);

            fetch(window.location.pathname + '?' + p.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                roleModalBody.innerHTML = d.rows;
            })
            .catch(function() {
                roleModalBody.innerHTML = '<tr><td colspan="2" class="rg-empty">Unable to load users for ' + escapeHtml(roleLabel) + '.</td></tr>';
            });
        }

    var gensanMap = L.map('gensan-map', {
        center: [6.1164, 125.1716], // General Santos City coordinates
        zoom: 13,
        dragging: true,
        scrollWheelZoom: true,
        doubleClickZoom: true,
        boxZoom: true,
        keyboard: true,
        zoomControl: true,
        tap: true,
        touchZoom: true
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(gensanMap);

    // Terminal data from backend
    var terminals = @json($terminals);
    terminals.forEach(function(terminal) {
        if (terminal.latitude && terminal.longitude) {
            var popupContent = '<strong>' + terminal.terminal_name + '</strong><br>' +
                (terminal.barangay ? 'Barangay: ' + terminal.barangay + '<br>' : '') +
                (terminal.city ? 'City: ' + terminal.city : '');
            L.marker([terminal.latitude, terminal.longitude])
                .addTo(gensanMap)
                .bindPopup(popupContent);
        }
    });
});
</script>
@stop