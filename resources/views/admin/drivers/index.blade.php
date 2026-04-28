@extends('adminlte::page')

@section('title', 'Drivers — RideGuide Admin')

@php
    $panelPrefix = request()->routeIs('super-admin.*') ? 'super-admin' : 'admin';
    $driversIndexRoute = $panelPrefix . '.drivers.index';
@endphp

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Drivers</h4>
            <p class="rg-page-subtitle">View all registered driver profiles.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            
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
                    <form id="rg-filter-form" method="GET" action="{{ route($driversIndexRoute) }}" class="rg-filter-bar mt-2">
                        <input id="rg-search" type="text" name="search" class="rg-search-input" placeholder="Search name, email, license…" value="{{ request('search') }}">
                        <select id="rg-filter" name="status" class="rg-filter-select">
                            <option value="">All Statuses</option>
                            <option value="verified" {{ request('status') === 'verified' ? 'selected' : '' }}>Verified</option>
                            <option value="unverified" {{ in_array(request('status'), ['unverified', 'pending'], true) ? 'selected' : '' }}>Unverified</option>
                            <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
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
                                    <th>Vehicles</th>
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
                    <p class="text-muted mb-3">License ID Number: <strong id="driverLicenseNumber">N/A</strong></p>
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

    <div class="modal fade" id="driverVehiclesModal" tabindex="-1" role="dialog" aria-labelledby="driverVehiclesModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="driverVehiclesModalTitle">Vehicle Photos</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="driverVehiclesEmptyState" class="text-muted">Select a driver to view vehicle photos.</div>
                    <div id="driverVehiclesContent" class="d-none"></div>
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
    var licenseNumberEl = document.getElementById('driverLicenseNumber');
    var frontWrapper = document.getElementById('driverLicenseFrontWrapper');
    var backWrapper = document.getElementById('driverLicenseBackWrapper');
    var frontImg = document.getElementById('driverLicenseFront');
    var backImg = document.getElementById('driverLicenseBack');
    var emptyState = document.getElementById('driverLicenseEmptyState');
    var vehiclesModalTitle = document.getElementById('driverVehiclesModalTitle');
    var vehiclesEmptyState = document.getElementById('driverVehiclesEmptyState');
    var vehiclesContent = document.getElementById('driverVehiclesContent');
    var timer;

    function bindLicensePreview() {
        document.querySelectorAll('.rg-view-license').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var driverName = this.dataset.driver || 'Driver License';
                var licenseNumber = this.dataset.licenseNumber || 'N/A';
                var front = this.dataset.front || '';
                var back = this.dataset.back || '';

                modalTitle.textContent = 'Driver License Images — ' + driverName;
                if (licenseNumberEl) {
                    licenseNumberEl.textContent = licenseNumber;
                }

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
            bindVehicleButtons();
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

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (s) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[s];
        });
    }

    function renderVehiclePhotos(vehicle) {
        if (!vehicle.photos || !vehicle.photos.length) {
            return '<p class="text-muted mb-0">No vehicle images uploaded.</p>';
        }

        var html = '<div class="row">';
        vehicle.photos.forEach(function(photo) {
            html += '' +
                '<div class="col-md-6 mb-3">' +
                    '<div class="rg-image-preview">' +
                        '<img src="' + escapeHtml(photo.url) + '" class="img-fluid rounded shadow-sm" alt="' + escapeHtml(photo.label) + ' view">' +
                        '<small class="text-muted d-block mt-2">' + escapeHtml(photo.label) + ' View</small>' +
                    '</div>' +
                '</div>';
        });
        html += '</div>';
        return html;
    }

    function bindVehicleButtons() {
        document.querySelectorAll('.rg-view-vehicles').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var driverId = this.dataset.driverId;
                var driverName = this.dataset.driverName || 'Driver';

                if (!driverId) {
                    return;
                }

                vehiclesModalTitle.textContent = 'Vehicle Photos — ' + driverName;
                vehiclesEmptyState.classList.remove('d-none');
                vehiclesEmptyState.textContent = 'Loading vehicle photos...';
                vehiclesContent.classList.add('d-none');
                vehiclesContent.innerHTML = '';

                fetch('{{ route($driversIndexRoute) }}?driver_id=' + encodeURIComponent(driverId), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    var vehicles = d.vehicles || [];
                    var driver = d.driver || {};

                    if (!vehicles.length) {
                        vehiclesEmptyState.textContent = 'No vehicles found for ' + (driver.name || driverName) + '.';
                        return;
                    }

                    var html = '';
                    vehicles.forEach(function(vehicle) {
                        html += '<div class="card mb-3">' +
                            '<div class="card-body">' +
                                '<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">' +
                                    '<div>' +
                                        '<h6 class="mb-1">Plate No.: ' + escapeHtml(vehicle.plate_number || '—') + '</h6>' +
                                        '<p class="mb-0 text-muted">Type: ' + escapeHtml(vehicle.vehicle_type || '—') + '</p>' +
                                    '</div>' +
                                    '<div class="d-flex flex-wrap gap-2">' +
                                        '<span class="rg-status-badge ' + (vehicle.status === 'active' ? 'rg-status-active' : 'rg-status-pending') + '">' + escapeHtml((vehicle.status || 'inactive').replace(/_/g, ' ')) + '</span>' +
                                        '<span class="rg-status-badge ' + (vehicle.verification_status === 'verified' ? 'rg-status-active' : (vehicle.verification_status === 'rejected' ? 'rg-status-danger' : 'rg-status-pending')) + '">' + escapeHtml((vehicle.verification_status || 'pending').replace(/_/g, ' ')) + '</span>' +
                                    '</div>' +
                                '</div>' +
                                renderVehiclePhotos(vehicle) +
                            '</div>' +
                        '</div>';
                    });

                    vehiclesContent.innerHTML = html;
                    vehiclesEmptyState.classList.add('d-none');
                    vehiclesContent.classList.remove('d-none');
                })
                .catch(function() {
                    vehiclesEmptyState.textContent = 'Unable to load vehicle photos.';
                });
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
    bindVehicleButtons();
});
</script>
@stop
