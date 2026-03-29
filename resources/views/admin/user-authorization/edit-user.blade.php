@extends('adminlte::page')

@php
    $panelPrefix = request()->routeIs('super-admin.*') ? 'super-admin' : 'admin';
    $authorizationIndexRoute = $panelPrefix . '.user-authorization.index';
    $authorizationUpdateUserRoute = $panelPrefix . '.user-authorization.update-user';
@endphp

@section('title', 'Manage User Roles — RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Manage Roles: {{ $user->first_name }} {{ $user->last_name }}</h4>
            <p class="rg-page-subtitle">Assign roles to this user. Permissions are inherited from the assigned roles.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            
            <a href="{{ route($authorizationIndexRoute) }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
@stop

@section('content')

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    {{-- Flash Messages --}}
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

    <div class="row rg-manage-roles-layout">
        {{-- Role Assignment --}}
        <div class="col-12 col-lg-5">
            <form action="{{ route($authorizationUpdateUserRoute, $user) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="rg-card">
                    <div class="rg-card-header">
                        <div class="d-flex align-items-center">
                            <span class="rg-card-dot"></span>
                            <h6 class="rg-card-title mb-0">Assign Roles</h6>
                        </div>
                    </div>
                    <div class="rg-card-body rg-manage-roles-body">
                        <p class="text-muted mb-3">Select the roles for <strong>{{ $user->first_name }} {{ $user->last_name }}</strong> ({{ $user->email }}):</p>
                        <div class="alert alert-info py-2 px-3 mb-3">
                            <small>
                                Role rules: <strong>Super Admin</strong> must be standalone. <strong>Admin</strong> cannot be combined with Driver, Commuter, or Organization.
                            </small>
                        </div>

                        <div class="rg-manage-role-list">
                        @foreach($roles as $role)
                            @php
                                $isLocked = in_array($role->id, $lockedRoleIds ?? [], true);
                                $isRuleDisabled = in_array($role->id, $disabledRoleIds ?? [], true);
                                $isDisabled = $isLocked || $isRuleDisabled;
                            @endphp

                            <div class="rg-manage-role-item {{ $isDisabled ? 'rg-manage-role-item-locked' : '' }}">
                                <input type="checkbox"
                                       class="rg-manage-role-checkbox role-checkbox"
                                       id="role_{{ $role->id }}"
                                       name="roles[]"
                                       value="{{ $role->id }}"
                                       {{ $user->roles->contains('id', $role->id) ? 'checked' : '' }}
                                       {{ $isDisabled ? 'disabled' : '' }}>
                                <label class="rg-manage-role-label" for="role_{{ $role->id }}">
                                    <span class="rg-manage-role-name">{{ ucwords(str_replace('_', ' ', $role->name)) }}</span>
                                    @if($role->description)
                                        <small class="text-muted d-block">{{ $role->description }}</small>
                                    @endif
                                    @if($isLocked)
                                        <small class="text-muted d-block">Only Super Admin can change this role.</small>
                                    @elseif($isRuleDisabled)
                                        
                                    @endif
                                </label>
                            </div>
                        @endforeach
                        </div>
                    </div>
                    <div class="rg-card-footer d-flex justify-content-end">
                        <a href="{{ route($authorizationIndexRoute) }}" class="btn btn-secondary mr-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Save Roles
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Current Permissions (read-only) --}}
        <div class="col-12 col-lg-7">
            <div class="rg-card">
                <div class="rg-card-header">
                    <div class="d-flex align-items-center">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">Effective Permissions (from roles)</h6>
                    </div>
                    <span class="rg-status-badge {{ ($user->status ?? 'active') === 'active' ? 'rg-status-active' : 'rg-status-pending' }}">
                        {{ ucfirst($user->status ?? 'active') }}
                    </span>
                </div>
                <div class="rg-card-body rg-effective-permissions-body">
                    @if($user->hasRole(\App\Models\Role::SUPER_ADMIN))
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-crown mr-1"></i>
                            <strong>Super Admin</strong> — This user has unrestricted access to all features.
                        </div>
                    @else
                        @foreach($permissionGroups as $group => $groupPermissions)
                            <div class="mb-3 rg-effective-permission-group">
                                <h6 class="text-uppercase text-muted font-weight-bold mb-2" style="letter-spacing:.5px; font-size:0.8rem;">
                                    <i class="fas fa-folder mr-1"></i> {{ ucwords(str_replace('_', ' ', $group)) }}
                                </h6>
                                <div class="row">
                                    @foreach($groupPermissions as $permission)
                                        <div class="col-12 col-sm-6 col-md-4 mb-1">
                                            @if(in_array($permission->id, $userPermissionIds))
                                                <span class="text-success"><i class="fas fa-check-circle mr-1"></i> {{ $permission->display_name }}</span>
                                            @else
                                                <span class="text-muted"><i class="far fa-circle mr-1"></i> {{ $permission->display_name }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        @if($permissions->isEmpty())
                            <p class="text-muted text-center py-4">
                                <i class="fas fa-info-circle mr-1"></i>
                                No permissions have been defined yet.
                            </p>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

@stop
