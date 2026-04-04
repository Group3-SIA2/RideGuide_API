@extends('adminlte::page')

@section('title', 'Organizations — RideGuide Admin')

@php
    $panelPrefix = request()->routeIs('super-admin.*') ? 'super-admin' : 'admin';
    $organizationsIndexRoute = $panelPrefix . '.organizations.index';
    $organizationsCreateRoute = $panelPrefix . '.organizations.create';
    $organizationTypesIndexRoute = $panelPrefix . '.organizations.types.index';
@endphp

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Organizations</h4>
            <p class="rg-page-subtitle">Manage TODA, MODA, and other driver organizations.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="rg-badge" id="rg-total">{{ $organizations->total() }} total</span>
            <a href="{{ route($organizationTypesIndexRoute) }}" class="rg-btn rg-btn-secondary rg-btn-sm">
                <i class="fas fa-tags"></i> Organization Types
            </a>
            <a href="{{ route($organizationsCreateRoute) }}" class="rg-btn rg-btn-primary rg-btn-sm">
                <i class="fas fa-plus"></i> Add Organization
            </a>
        </div>
    </div>
@stop

@section('content')

    @if(session('success'))
    <div class="rg-alert rg-alert-success mb-3">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="rg-card">
                <div class="rg-card-header">
                    <div class="d-flex align-items-center">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">Organization Directory</h6>
                    </div>
                    <form id="rg-filter-form" method="GET" action="{{ route($organizationsIndexRoute) }}" class="rg-filter-bar mt-2">
                        <input id="rg-search" type="text" name="search" class="rg-search-input" placeholder="Search name, organization type, type description, or address…" value="{{ request('search') }}">
                        <select id="rg-filter-status" name="status" class="rg-filter-select">
                            <option value="">All Statuses</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            <option value="deleted" {{ request('status') === 'deleted' ? 'selected' : '' }}>🗑 Deleted</option>
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
                                    <th>Organization Type</th>
                                    <th>Address</th>
                                    <th>Drivers</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rg-table-body">
                                @include('admin.organizations._rows', ['showDeleted' => $showDeleted])
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($organizations->hasPages())
                <div id="rg-pagination" class="rg-card-footer">
                    {{ $organizations->links() }}
                </div>
                @endif

            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════
         Update HQ Address Modal
    ══════════════════════════════════════════════ --}}
    <div class="modal fade" id="modal-address" tabindex="-1" role="dialog" aria-labelledby="modal-address-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content rg-modal-content">
                <div class="modal-header rg-modal-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="rg-modal-icon"><i class="fas fa-map-marker-alt"></i></span>
                        <h5 class="modal-title rg-modal-title" id="modal-address-label">Update Head Office Address</h5>
                    </div>
                    <button type="button" class="rg-modal-close" data-dismiss="modal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="form-address-update" method="POST" action="">
                    @csrf
                    @method('PATCH')

                    <div class="modal-body rg-modal-body">
                        <p class="rg-modal-org-name mb-3">
                            <i class="fas fa-building mr-1 text-muted"></i>
                            <span id="modal-org-name-label"></span>
                        </p>

                        <div class="row">
                            <div class="col-12 col-md-6">
                                <div class="rg-form-group mb-3">
                                    <label class="rg-form-label rg-form-label-sm" for="modal-hq-street">
                                        Street <span class="rg-required">*</span>
                                    </label>
                                    <input id="modal-hq-street" name="hq_street" type="text"
                                           class="rg-form-control"
                                           placeholder="e.g. J. Catolico Ave"
                                           required>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="rg-form-group mb-3">
                                    <label class="rg-form-label rg-form-label-sm" for="modal-hq-barangay">
                                        Barangay <span class="rg-required">*</span>
                                    </label>
                                    <input id="modal-hq-barangay" name="hq_barangay" type="text"
                                           class="rg-form-control"
                                           placeholder="e.g. Lagao"
                                           required>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="rg-form-group mb-3">
                                    <label class="rg-form-label rg-form-label-sm" for="modal-hq-subdivision">Subdivision</label>
                                    <input id="modal-hq-subdivision" name="hq_subdivision" type="text"
                                           class="rg-form-control"
                                           placeholder="e.g. Heritage Homes (optional)">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="rg-form-group mb-3">
                                    <label class="rg-form-label rg-form-label-sm" for="modal-hq-floor-unit-room">Floor / Unit / Room</label>
                                    <input id="modal-hq-floor-unit-room" name="hq_floor_unit_room" type="text"
                                           class="rg-form-control"
                                           placeholder="e.g. Unit 3B (optional)">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="rg-form-group mb-0">
                                    <label class="rg-form-label rg-form-label-sm" for="modal-hq-lat">Latitude</label>
                                    <input id="modal-hq-lat" name="hq_lat" type="text"
                                           class="rg-form-control"
                                           placeholder="e.g. 6.1164 (optional)">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="rg-form-group mb-0">
                                    <label class="rg-form-label rg-form-label-sm" for="modal-hq-lng">Longitude</label>
                                    <input id="modal-hq-lng" name="hq_lng" type="text"
                                           class="rg-form-control"
                                           placeholder="e.g. 125.1716 (optional)">
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="rg-form-label rg-form-label-sm">Select Location on Map</label>
                            <div id="modal-hq-map" class="rg-map-modal"></div>
                            <small class="text-muted">Click on the map to set latitude and longitude.</small>
                        </div>

                        {{-- Inline feedback shown after AJAX response --}}
                        <div id="modal-address-feedback" class="mt-3" style="display:none;"></div>
                    </div>

                    <div class="modal-footer rg-modal-footer">
                        <button type="button" class="rg-btn rg-btn-secondary" data-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit" class="rg-btn rg-btn-primary" id="btn-save-address">
                            <i class="fas fa-save mr-1"></i> Save Address
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@stop

@section('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<style>
/* ── Address Modal Styles ── */
.rg-modal-content {
    border: none;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,.18);
    overflow: hidden;
}
.rg-modal-header {
    background: var(--rg-accent-dim, rgba(99,102,241,.08));
    border-bottom: 1px solid rgba(0,0,0,.06);
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.rg-modal-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--rg-accent, #6366f1);
    color: #fff;
    font-size: .85rem;
}
.rg-modal-title {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
    color: var(--rg-text, #1e293b);
}
.rg-modal-close {
    background: none;
    border: none;
    color: var(--rg-text-muted, #64748b);
    font-size: 1rem;
    cursor: pointer;
    padding: 4px 6px;
    border-radius: 6px;
    transition: background .15s;
}
.rg-modal-close:hover { background: rgba(0,0,0,.06); }
.rg-modal-body {
    padding: 1.25rem;
}
.rg-modal-org-name {
    font-size: .85rem;
    color: var(--rg-text-muted, #64748b);
    font-weight: 500;
}
.rg-modal-footer {
    background: var(--rg-surface, #f8fafc);
    border-top: 1px solid rgba(0,0,0,.06);
    padding: .75rem 1.25rem;
    gap: .5rem;
}
.rg-address-block {
    background: var(--rg-surface, #f8fafc);
    border: 1px solid rgba(0,0,0,.07);
    border-radius: 10px;
    padding: 1rem;
}
.rg-form-label-sm {
    font-size: .78rem;
    margin-bottom: .25rem;
}
.rg-map-modal {
    height: 260px;
    width: 100%;
    border-radius: 10px;
    border: 1px solid rgba(0,0,0,.08);
    margin-top: 0.35rem;
}
</style>
@stop

@section('js')
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
// Adress MODAL
function openAddressModal(btn) {
    var orgId   = btn.getAttribute('data-org-id');
    var orgName = btn.getAttribute('data-org-name');

    document.getElementById('modal-org-name-label').textContent = orgName;

    document.getElementById('modal-hq-street').value         = btn.getAttribute('data-hq-street') || '';
    document.getElementById('modal-hq-barangay').value       = btn.getAttribute('data-hq-barangay') || '';
    document.getElementById('modal-hq-subdivision').value    = btn.getAttribute('data-hq-subdivision') || '';
    document.getElementById('modal-hq-floor-unit-room').value = btn.getAttribute('data-hq-floor-unit-room') || '';
    document.getElementById('modal-hq-lat').value            = btn.getAttribute('data-hq-lat') || '';
    document.getElementById('modal-hq-lng').value            = btn.getAttribute('data-hq-lng') || '';

    var baseUrl = '{{ rtrim(url($panelPrefix . '/organizations'), '/') }}';
    document.getElementById('form-address-update').action = baseUrl + '/' + orgId + '/address';

    var feedback = document.getElementById('modal-address-feedback');
    feedback.style.display = 'none';
    feedback.innerHTML = '';

    var saveBtn = document.getElementById('btn-save-address');
    saveBtn.disabled = false;
    saveBtn.innerHTML = '<i class="fas fa-save mr-1"></i> Save Address';

    $('#modal-address').modal('show');
}

// Submit AJAX form for updating address
document.addEventListener('DOMContentLoaded', function () {
    var form    = document.getElementById('form-address-update');
    var feedback = document.getElementById('modal-address-feedback');
    var saveBtn  = document.getElementById('btn-save-address');
    var hqMap;
    var hqMarker;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving…';
        feedback.style.display = 'none';

        var formData = new FormData(form);
        // Laravel requires _method override for PATCH
        formData.set('_method', 'PATCH');

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                    ? document.querySelector('meta[name="csrf-token"]').content
                    : formData.get('_token'),
            },
            body: formData,
        })
        .then(function (r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            if (res.ok && res.data.success) {
                feedback.style.display = '';
                feedback.innerHTML = '<div class="rg-alert rg-alert-success"><i class="fas fa-check-circle mr-1"></i>' + res.data.message + '</div>';
                saveBtn.innerHTML = '<i class="fas fa-check mr-1"></i> Saved';

                // Update the data all attributes on the trigger button so re-opening shows fresh data
                var triggerBtn = document.querySelector('.rg-btn-address[data-org-id="' + form.action.match(/organizations\/([^/]+)\/address/)[1] + '"]');
                if (triggerBtn) {
                    triggerBtn.setAttribute('data-hq-street',         formData.get('hq_street') || '');
                    triggerBtn.setAttribute('data-hq-barangay',       formData.get('hq_barangay') || '');
                    triggerBtn.setAttribute('data-hq-subdivision',    formData.get('hq_subdivision') || '');
                    triggerBtn.setAttribute('data-hq-floor-unit-room', formData.get('hq_floor_unit_room') || '');
                    triggerBtn.setAttribute('data-hq-lat',            formData.get('hq_lat') || '');
                    triggerBtn.setAttribute('data-hq-lng',            formData.get('hq_lng') || '');

                    // Also update the displayed address cell in the row
                    var row = triggerBtn.closest('tr');
                    if (row) {
                        var addressCell = row.querySelector('td:nth-child(4)');
                        if (addressCell) {
                            var street   = formData.get('hq_street') || '';
                            var barangay = formData.get('hq_barangay') || '';
                            if (street || barangay) {
                                addressCell.innerHTML = '<span>' + street + ', ' + barangay + '</span>';
                            }
                        }
                    }
                }

                setTimeout(function () { $('#modal-address').modal('hide'); }, 900);
            } else {
                var msg = (res.data.message) || 'Failed to save. Please try again.';
                // Laravel validation errors are in res.data.errors
                if (res.data.errors) {
                    msg = Object.values(res.data.errors).flat().join('<br>');
                }
                feedback.style.display = '';
                feedback.innerHTML = '<div class="alert alert-danger">' + msg + '</div>';
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save mr-1"></i> Save Address';
            }
        })
        .catch(function () {
            feedback.style.display = '';
            feedback.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save mr-1"></i> Save Address';
        });
    });

    // Live search and filters
    var search      = document.getElementById('rg-search');
    var filter      = document.getElementById('rg-filter');
    var filterStatus = document.getElementById('rg-filter-status');
    var tbody       = document.getElementById('rg-table-body');
    var pagin       = document.getElementById('rg-pagination');
    var total       = document.getElementById('rg-total');
    var timer;

    function buildQS(page) {
        var p = new URLSearchParams();
        if (search && search.value.trim()) p.set('search', search.value.trim());
        if (filterStatus && filterStatus.value) p.set('status', filterStatus.value);
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
    if (filterStatus) filterStatus.addEventListener('change', function() { load(); });
    document.getElementById('rg-clear').addEventListener('click', function() {
        if (search) search.value = '';
        if (filter) filter.value = '';
        if (filterStatus) filterStatus.value = '';
        load();
    });
    document.getElementById('rg-filter-form').addEventListener('submit', function(e) {
        e.preventDefault();
        load();
    });
    bindPagin();

    $('#modal-address').on('shown.bs.modal', function () {
        var mapContainer = document.getElementById('modal-hq-map');
        if (!mapContainer) {
            return;
        }

        var latInput = document.getElementById('modal-hq-lat');
        var lngInput = document.getElementById('modal-hq-lng');
        var lat = latInput && latInput.value ? parseFloat(latInput.value) : 6.1164;
        var lng = lngInput && lngInput.value ? parseFloat(lngInput.value) : 125.1716;

        if (!hqMap) {
            hqMap = L.map('modal-hq-map', {
                center: [lat, lng],
                zoom: 13,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(hqMap);

            hqMap.on('click', function (event) {
                var point = event.latlng;
                if (hqMarker) {
                    hqMap.removeLayer(hqMarker);
                }
                hqMarker = L.marker(point).addTo(hqMap);

                if (latInput) {
                    latInput.value = point.lat.toFixed(6);
                }
                if (lngInput) {
                    lngInput.value = point.lng.toFixed(6);
                }
            });
        }

        hqMap.setView([lat, lng], 13);
        hqMap.invalidateSize();

        if (hqMarker) {
            hqMap.removeLayer(hqMarker);
            hqMarker = null;
        }

        if (latInput && lngInput && latInput.value && lngInput.value) {
            hqMarker = L.circleMarker([lat, lng], {
                radius: 7,
                color: '#2563eb',
                fillColor: '#3b82f6',
                fillOpacity: 0.9,
            }).addTo(hqMap);
        }
    });
});
</script>
@stop
