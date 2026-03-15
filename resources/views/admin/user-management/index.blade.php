@extends('adminlte::page')

@section('title', 'User Authorization — RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">User Authorization</h4>
            <p class="rg-page-subtitle">Manage roles and what each role is allowed to do.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            @include('admin.partials.header_status_badges')
            <span class="rg-badge">{{ $roles->count() }} roles</span>
        </div>
    </div>
@stop

@section('content')

    {{-- Flash Messages --}}
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

    {{-- Roles & Permissions Matrix --}}
    <div class="row">
        <div class="col-12">
            <div class="rg-card">
                <div class="rg-card-header">
                    <div class="d-flex align-items-center">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">Roles & Permissions</h6>
                    </div>
                </div>
                <div class="rg-card-body p-0">
                    <div class="table-responsive">
                        <table class="rg-table">
                            <thead>
                                <tr>
                                    <th>Role</th>
                                    <th>Description</th>
                                    <th>Permissions</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($roles as $role)
                                <tr>
                                    <td>
                                        <span class="rg-role-badge">
                                            {{ ucwords(str_replace('_', ' ', $role->name)) }}
                                        </span>
                                    </td>
                                    <td class="rg-td-muted">{{ $role->description ?? '—' }}</td>
                                    <td>
                                        @if($role->name === \App\Models\Role::SUPER_ADMIN)
                                            <span class="badge badge-success">All Permissions</span>
                                        @elseif($role->permissions->count())
                                            @foreach($role->permissions->take(5) as $perm)
                                                <span class="badge badge-info mr-1">{{ $perm->display_name }}</span>
                                            @endforeach
                                            @if($role->permissions->count() > 5)
                                                <span class="badge badge-secondary">+{{ $role->permissions->count() - 5 }} more</span>
                                            @endif
                                        @else
                                            <span class="text-muted">No permissions</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($role->name !== \App\Models\Role::SUPER_ADMIN)
                                            <a href="{{ route('admin.user-management.edit-role', $role) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i> Edit Permissions
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Per-User Authorization --}}
    <div class="row mt-4">
        <div class="col-12">
            <div class="rg-card">
                <div class="rg-card-header">
                    <div class="d-flex align-items-center">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">User Role Assignments</h6>
                    </div>
                    <form id="rg-user-search-form" class="rg-filter-bar mt-2" method="GET" action="{{ route('admin.user-management.index') }}">
                        <input id="rg-user-search" type="text" name="user_search" class="rg-search-input" placeholder="Search user by name or email…" value="{{ request('user_search') }}">
                        <button type="submit" class="rg-btn-search"><i class="fas fa-search"></i> Search</button>
                    </form>
                </div>
                <div class="rg-card-body p-0">
                    <div class="table-responsive">
                        <table class="rg-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Current Roles</th>
                                    <th>Account</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $userQuery = \App\Models\User::with('roles')->whereNotNull('email_verified_at');
                                    if (request('user_search')) {
                                        $s = request('user_search');
                                        $userQuery->where(function ($q) use ($s) {
                                            $q->where('first_name', 'like', "%{$s}%")
                                              ->orWhere('last_name', 'like', "%{$s}%")
                                              ->orWhere('email', 'like', "%{$s}%");
                                        });
                                    }
                                    $users = $userQuery->latest()->paginate(15)->withQueryString();
                                @endphp

                                @forelse($users as $idx => $user)
                                <tr>
                                    <td class="rg-td-index">{{ $users->firstItem() + $idx }}</td>
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
                                        @forelse($user->roles as $r)
                                            <span class="rg-role-badge">{{ ucwords(str_replace('_', ' ', $r->name)) }}</span>
                                        @empty
                                            <span class="text-muted">None</span>
                                        @endforelse
                                    </td>
                                    <td>
                                        <span class="rg-status-badge {{ ($user->status ?? 'active') === 'active' ? 'rg-status-active' : 'rg-status-pending' }}">
                                            {{ ucfirst($user->status ?? 'active') }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.user-management.edit-user', $user) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-user-shield"></i> Manage Roles
                                        </a>
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

                @if($users->hasPages())
                <div class="rg-card-footer">
                    {{ $users->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>

@stop
