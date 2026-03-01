@extends('adminlte::page')

@section('title', 'Profile — RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">My Profile</h4>
            <p class="rg-page-subtitle">Your account details.</p>
        </div>
    </div>
@stop

@section('content')

    <div class="row">

        {{-- Profile Card --}}
        <div class="col-12 col-lg-4">
            <div class="rg-card">
                <div class="rg-card-body" style="padding: 28px; text-align: center;">
                    <div class="rg-avatar-lg mx-auto">
                        {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
                    </div>
                    <h5 class="rg-profile-name mt-3">{{ $user->first_name }} {{ $user->last_name }}</h5>
                    <p class="rg-td-muted mb-1" style="font-size:0.82rem;">{{ $user->email }}</p>
                    <span class="rg-role-badge">{{ ucfirst($user->role?->name ?? 'N/A') }}</span>
                </div>
            </div>
        </div>

        {{-- Details Card --}}
        <div class="col-12 col-lg-8">
            <div class="rg-card">
                <div class="rg-card-header">
                    <div class="d-flex align-items-center">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">Account Information</h6>
                    </div>
                </div>
                <div class="rg-card-body p-0">
                    <table class="rg-table">
                        <tbody>
                            <tr>
                                <td class="rg-td-muted" style="width:180px;">First Name</td>
                                <td>{{ $user->first_name }}</td>
                            </tr>
                            <tr>
                                <td class="rg-td-muted">Last Name</td>
                                <td>{{ $user->last_name }}</td>
                            </tr>
                            <tr>
                                <td class="rg-td-muted">Middle Name</td>
                                <td>{{ $user->middle_name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="rg-td-muted">Email Address</td>
                                <td>{{ $user->email }}</td>
                            </tr>
                            <tr>
                                <td class="rg-td-muted">Role</td>
                                <td><span class="rg-role-badge">{{ ucfirst($user->role?->name ?? 'N/A') }}</span></td>
                            </tr>
                            <tr>
                                <td class="rg-td-muted">Email Verified</td>
                                <td>
                                    <span class="rg-status-badge {{ $user->email_verified_at ? 'rg-status-active' : 'rg-status-pending' }}">
                                        {{ $user->email_verified_at ? $user->email_verified_at->format('M d, Y') : 'Not Verified' }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="rg-td-muted">Member Since</td>
                                <td>{{ $user->created_at->format('F d, Y') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

@stop

@section('css')
    <style>
        .rg-avatar-lg {
            width: 72px;
            height: 72px;
            border-radius: 16px;
            background: var(--rg-accent-light);
            color: var(--rg-accent);
            font-size: 1.4rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            letter-spacing: 1px;
        }
        .rg-profile-name {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--rg-text);
            margin-bottom: 2px;
        }
    </style>
@stop
