@extends('adminlte::page')

@section('title', 'Users — RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">All Users</h4>
            <p class="rg-page-subtitle">Manage all registered and verified users.</p>
        </div>
        <span class="rg-badge" id="rg-total">{{ $users->total() }} total</span>
    </div>
@stop

@section('content')

    <div class="row">
        <div class="col-12">
            <div class="rg-card">
                <div class="rg-card-header">
                    <div class="d-flex align-items-center">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">User Directory</h6>
                    </div>
                    <form id="rg-filter-form" method="GET" action="{{ route('admin.users.index') }}" class="rg-filter-bar mt-2">
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
                                    <th>Verified</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody id="rg-table-body">
                                @forelse($users as $index => $user)
                                <tr>
                                    <td class="rg-td-index">{{ $users->firstItem() + $index }}</td>
                                    <td>
                                        <div class="rg-user-cell">
                                            <div class="rg-avatar">
                                                {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <p class="rg-user-name mb-0">{{ $user->first_name }} {{ $user->last_name }}</p>
                                                @if($user->middle_name)
                                                    <span class="rg-td-muted" style="font-size:0.72rem;">{{ $user->middle_name }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="rg-td-muted">{{ $user->email }}</td>
                                    <td>
                                        <span class="rg-role-badge">{{ ucfirst($user->role?->name ?? 'N/A') }}</span>
                                    </td>
                                    <td>
                                        <span class="rg-status-badge {{ $user->email_verified_at ? 'rg-status-active' : 'rg-status-pending' }}">
                                            {{ $user->email_verified_at ? 'Verified' : 'Pending' }}
                                        </span>
                                    </td>
                                    <td class="rg-td-muted">{{ $user->created_at->format('M d, Y') }}</td>
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
                <div id="rg-pagination" class="rg-card-footer">
                    {{ $users->links() }}
                </div>
                @endif

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
    var pagin  = document.getElementById('rg-pagination');
    var total  = document.getElementById('rg-total');
    var timer;

    function buildQS(page) {
        var p = new URLSearchParams();
        if (search && search.value.trim()) p.set('search', search.value.trim());
        if (filter && filter.value)        p.set('role', filter.value);
        if (page)                          p.set('page', page);
        return p;
    }

    function load(page) {
        var qs    = buildQS(page).toString();
        var barQS = buildQS().toString();
        history.replaceState(null, '', barQS ? '?' + barQS : window.location.pathname);
        tbody.style.opacity = '0.35';
        fetch(window.location.pathname + (qs ? '?' + qs : ''), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            tbody.innerHTML     = d.rows;
            tbody.style.opacity = '1';
            if (total) total.textContent = d.total + ' total';
            if (pagin) {
                pagin.innerHTML     = d.pagination || '';
                pagin.style.display = (d.pagination || '').trim() ? '' : 'none';
                bindPagin();
            }
        })
        .catch(function() { tbody.style.opacity = '1'; });
    }

    function bindPagin() {
        if (!pagin) return;
        pagin.querySelectorAll('a[href]').forEach(function(a) {
            a.addEventListener('click', function(e) {
                e.preventDefault();
                load(new URL(this.href).searchParams.get('page') || 1);
            });
        });
    }

    if (search) search.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(function() { load(); }, 350);
    });
    if (filter) filter.addEventListener('change', function() { load(); });
    document.getElementById('rg-clear').addEventListener('click', function() {
        if (search) search.value = '';
        if (filter) filter.value = '';
        load();
    });
    document.getElementById('rg-filter-form').addEventListener('submit', function(e) {
        e.preventDefault();
        load();
    });
    bindPagin();
});
</script>
@stop
