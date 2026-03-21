@extends('adminlte::page')

@section('title', 'Drivers — RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Drivers</h4>
            <p class="rg-page-subtitle">View all registered driver profiles.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            @include('admin.partials.header_status_badges')
            <span class="rg-badge" id="rg-total">{{ $drivers->total() }} total</span>
        </div>
    </div>
@stop

@section('content')

    <div class="row">
        <div class="col-12">
            <div class="rg-card">
                <div class="rg-card-header">
                    <div class="d-flex align-items-center">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">Driver List</h6>
                    </div>
                    <form id="rg-filter-form" method="GET" action="{{ route('admin.drivers.index') }}" class="rg-filter-bar mt-2">
                        <input id="rg-search" type="text" name="search" class="rg-search-input" placeholder="Search name, email, license…" value="{{ request('search') }}">
                        <select id="rg-filter" name="status" class="rg-filter-select">
                            <option value="">All Statuses</option>
                            <option value="verified" {{ request('status') === 'verified' ? 'selected' : '' }}>Verified</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
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
                                    <th>Organization</th>
                                    <th>License No.</th>
                                    <th>Verification</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody id="rg-table-body">
                                @include('admin.drivers._rows', ['drivers' => $drivers])
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($drivers->hasPages())
                <div id="rg-pagination" class="rg-card-footer">
                    {{ $drivers->links() }}
                </div>
                @endif

            </div>
        </div>
    </div>

    <div class="modal fade" id="driverLicensePreviewModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="driverLicenseModalTitle">Driver License Images</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3" id="driverLicenseFrontWrapper">
                            <img src="" alt="Driver License Front" class="img-fluid rounded shadow-sm" id="driverLicenseFront">
                            <small class="text-muted d-block mt-2">Front View</small>
                        </div>
                        <div class="col-md-6 mb-3" id="driverLicenseBackWrapper">
                            <img src="" alt="Driver License Back" class="img-fluid rounded shadow-sm" id="driverLicenseBack">
                            <small class="text-muted d-block mt-2">Back View</small>
                        </div>
                        <div class="col-12" id="driverLicenseEmptyState" style="display: none;">
                            <p class="text-muted mb-0">No license images uploaded.</p>
                        </div>
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
    var pagin  = document.getElementById('rg-pagination');
    var total  = document.getElementById('rg-total');
    var modal = document.getElementById('driverLicensePreviewModal');
    var modalTitle = document.getElementById('driverLicenseModalTitle');
    var frontWrapper = document.getElementById('driverLicenseFrontWrapper');
    var backWrapper = document.getElementById('driverLicenseBackWrapper');
    var frontImg = document.getElementById('driverLicenseFront');
    var backImg = document.getElementById('driverLicenseBack');
    var emptyState = document.getElementById('driverLicenseEmptyState');
    var timer;

    function bindLicensePreview() {
        document.querySelectorAll('.rg-view-license').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var driverName = this.dataset.driver || 'Driver License';
                var front = this.dataset.front || '';
                var back = this.dataset.back || '';

                modalTitle.textContent = 'Driver License Images — ' + driverName;

                if (front) {
                    frontImg.src = front;
                    frontWrapper.style.display = '';
                } else {
                    frontWrapper.style.display = 'none';
                }

                if (back) {
                    backImg.src = back;
                    backWrapper.style.display = '';
                } else {
                    backWrapper.style.display = 'none';
                }

                emptyState.style.display = (front || back) ? 'none' : '';
            });
        });
    }

    function buildQS(page) {
        var p = new URLSearchParams();
        if (search && search.value.trim()) p.set('search', search.value.trim());
        if (filter && filter.value)        p.set('status', filter.value);
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
            bindLicensePreview();
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
    bindLicensePreview();
});
</script>
@stop
