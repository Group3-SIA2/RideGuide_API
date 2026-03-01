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
                <div class="rg-stat-icon" style="background: #BDDCFF;">
                    <i class="fas fa-users" style="color: #248AFF;"></i>
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
                <div class="rg-stat-icon" style="background: #BDDCFF;">
                    <i class="fas fa-user-friends" style="color: #248AFF;"></i>
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
                <div class="rg-stat-icon" style="background: #BDDCFF;">
                    <i class="fas fa-id-card" style="color: #248AFF;"></i>
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
                <div class="rg-stat-icon" style="background: #BDDCFF;">
                    <i class="fas fa-shield-alt" style="color: #248AFF;"></i>
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
                            <tbody>
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

@section('css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* ── Base Font ─────────────────────────────────── */
        body,
        .wrapper,
        .content-wrapper,
        .main-sidebar,
        .main-header,
        h1, h2, h3, h4, h5, h6, p, span, a, td, th, label, input, button {
            font-family: 'Inter', sans-serif !important;
        }

        /* ── Sidebar ───────────────────────────────────── */
        .main-sidebar {
            background: #248AFF !important;
        }
        .sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link.active,
        .sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link:hover {
            background: rgba(255,255,255,0.15) !important;
            color: #fff !important;
            border-radius: 8px;
        }
        .sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link {
            color: rgba(255,255,255,0.80) !important;
        }
        .brand-link {
            background: #1a78e8 !important;
            border-bottom: 1px solid rgba(255,255,255,0.15) !important;
        }
        .brand-link:hover {
            background: #1a78e8 !important;
        }
        .brand-text {
            color: #fff !important;
            font-weight: 700 !important;
            font-size: 1.1rem !important;
            letter-spacing: -0.3px;
        }

        /* ── Topbar ────────────────────────────────────── */
        .main-header.navbar {
            background: #fff !important;
            border-bottom: 1px solid #eef0f3 !important;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06) !important;
        }
        .navbar-nav .nav-link {
            color: #374151 !important;
        }

        /* ── Page Header ───────────────────────────────── */
        .content-header {
            padding: 20px 28px 0 28px !important;
        }
        .rg-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }
        .rg-page-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }
        .rg-page-subtitle {
            font-size: 0.8rem;
            color: #6b7280;
            margin: 2px 0 0;
        }
        .rg-badge {
            font-size: 0.75rem;
            font-weight: 500;
            color: #248AFF;
            background: #BDDCFF;
            padding: 5px 12px;
            border-radius: 20px;
        }

        /* ── Content Area ──────────────────────────────── */
        .content-wrapper {
            background: #f5f7fa !important;
            padding: 20px 28px !important;
        }

        /* ── Stat Cards ────────────────────────────────── */
        .rg-stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            border: 1px solid #eef0f3;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: box-shadow 0.2s ease;
        }
        .rg-stat-card:hover {
            box-shadow: 0 4px 12px rgba(36,138,255,0.12);
        }
        .rg-stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.3rem;
        }
        .rg-stat-body {
            flex: 1;
            min-width: 0;
        }
        .rg-stat-label {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #9ca3af;
            margin: 0 0 2px;
        }
        .rg-stat-value {
            font-size: 1.7rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            line-height: 1.1;
        }
        .rg-stat-sub {
            font-size: 0.72rem;
            color: #9ca3af;
            margin-top: 3px;
            display: block;
        }

        /* ── Card ──────────────────────────────────────── */
        .rg-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #eef0f3;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .rg-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .rg-card-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #248AFF;
            display: inline-block;
            margin-right: 8px;
        }
        .rg-card-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #111827;
        }
        .rg-card-body {
            padding: 0;
        }

        /* ── Table ─────────────────────────────────────── */
        .rg-table {
            width: 100%;
            border-collapse: collapse;
        }
        .rg-table thead tr {
            background: #f9fafb;
        }
        .rg-table thead th {
            padding: 10px 20px;
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #9ca3af;
            border-bottom: 1px solid #f3f4f6;
            white-space: nowrap;
        }
        .rg-table tbody tr {
            border-bottom: 1px solid #f9fafb;
            transition: background 0.15s ease;
        }
        .rg-table tbody tr:last-child {
            border-bottom: none;
        }
        .rg-table tbody tr:hover {
            background: #fafbff;
        }
        .rg-table tbody td {
            padding: 13px 20px;
            vertical-align: middle;
            font-size: 0.845rem;
            color: #374151;
        }
        .rg-td-index {
            color: #9ca3af !important;
            font-size: 0.78rem !important;
            width: 36px;
        }
        .rg-td-muted {
            color: #6b7280 !important;
        }

        /* ── User Cell ─────────────────────────────────── */
        .rg-user-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .rg-avatar {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: #BDDCFF;
            color: #248AFF;
            font-size: 0.7rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            letter-spacing: 0.5px;
        }
        .rg-user-name {
            font-size: 0.845rem;
            font-weight: 500;
            color: #111827;
        }

        /* ── Badges ────────────────────────────────────── */
        .rg-role-badge {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            background: #BDDCFF;
            color: #248AFF;
        }
        .rg-status-badge {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
        }
        .rg-status-active {
            background: #d1fae5;
            color: #065f46;
        }
        .rg-status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        /* ── Empty State ───────────────────────────────── */
        .rg-empty {
            text-align: center;
            color: #9ca3af !important;
            font-size: 0.845rem !important;
            padding: 40px 20px !important;
        }

        /* ── Sidebar nav search ─────────────────────────── */
        .sidebar .form-control {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.2);
            color: #fff;
        }
        .sidebar .form-control::placeholder {
            color: rgba(255,255,255,0.55);
        }
    </style>
@stop

@section('js')
@stop
