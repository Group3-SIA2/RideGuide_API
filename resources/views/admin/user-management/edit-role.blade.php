@extends('adminlte::page')

@section('title', 'Edit Role Permissions — RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Edit Permissions: {{ ucwords(str_replace('_', ' ', $role->name)) }}</h4>
            <p class="rg-page-subtitle">Use the checkboxes below to set what this role can do.</p>
        </div>
        <a href="{{ route('admin.user-management.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
@stop

@section('content')

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

    <form action="{{ route('admin.user-management.update-role', $role) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-12">
                <div class="rg-card">
                    <div class="rg-card-header">
                        <div class="d-flex align-items-center">
                            <span class="rg-card-dot"></span>
                            <h6 class="rg-card-title mb-0">Permissions for "{{ ucwords(str_replace('_', ' ', $role->name)) }}"</h6>
                        </div>
                        <div class="mt-2">
                            <button type="button" id="btn-select-all" class="btn btn-xs btn-outline-success mr-1">
                                <i class="fas fa-check-double"></i> Select All
                            </button>
                            <button type="button" id="btn-deselect-all" class="btn btn-xs btn-outline-danger">
                                <i class="fas fa-times"></i> Deselect All
                            </button>
                        </div>
                    </div>
                    <div class="rg-card-body">

                        @foreach($permissionGroups as $group => $groupPermissions)
                            <div class="mb-4">
                                <h6 class="text-uppercase text-muted font-weight-bold mb-3" style="letter-spacing:.5px; font-size:0.8rem;">
                                    <i class="fas fa-folder mr-1"></i> {{ ucwords(str_replace('_', ' ', $group)) }}
                                </h6>
                                <div class="row">
                                    @foreach($groupPermissions as $permission)
                                        <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-2">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox"
                                                       class="custom-control-input permission-checkbox"
                                                       id="perm_{{ $permission->id }}"
                                                       name="permissions[]"
                                                       value="{{ $permission->id }}"
                                                       {{ in_array($permission->id, $rolePermissionIds) ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="perm_{{ $permission->id }}">
                                                    {{ $permission->display_name }}
                                                </label>
                                                @if($permission->description)
                                                    <br>
                                                    <small class="text-muted">{{ $permission->description }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            @if(!$loop->last)
                                <hr>
                            @endif
                        @endforeach

                        @if($permissions->isEmpty())
                            <p class="text-muted text-center py-4">
                                <i class="fas fa-info-circle mr-1"></i>
                                No permissions have been defined yet. Run the permission seeder first.
                            </p>
                        @endif

                    </div>
                    <div class="rg-card-footer d-flex justify-content-end">
                        <a href="{{ route('admin.user-management.index') }}" class="btn btn-secondary mr-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Save Permissions
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

@stop

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btn-select-all').addEventListener('click', function () {
        document.querySelectorAll('.permission-checkbox').forEach(function (cb) {
            cb.checked = true;
        });
    });
    document.getElementById('btn-deselect-all').addEventListener('click', function () {
        document.querySelectorAll('.permission-checkbox').forEach(function (cb) {
            cb.checked = false;
        });
    });
});
</script>
@stop
