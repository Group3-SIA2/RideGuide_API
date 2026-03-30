@extends('adminlte::page')

@section('title', 'Commuters — RideGuide Admin')

@php
    $panelPrefix = request()->routeIs('super-admin.*') ? 'super-admin' : 'admin';
    $commutersIndexRoute = $panelPrefix . '.commuters.index';
@endphp

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Commuters</h4>
            <p class="rg-page-subtitle">View all registered commuter profiles.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
        
            <span class="rg-badge" id="rg-total">{{ $commuters->total() }} total</span>
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
                        <h6 class="rg-card-title mb-0">Commuter List</h6>
                    </div>
                    <form id="rg-filter-form" method="GET" action="{{ route($commutersIndexRoute) }}" class="rg-filter-bar mt-2">
                        <input id="rg-search" type="text" name="search" class="rg-search-input" placeholder="Search name or email…" value="{{ request('search') }}">
                        <select id="rg-filter" name="classification" class="rg-filter-select">
                            <option value="">All Classifications</option>
                            @foreach($classifications as $cls)
                                <option value="{{ $cls }}" {{ request('classification') === $cls ? 'selected' : '' }}>{{ $cls }}</option>
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
                                    <th>Classification</th>
                                    <th>ID Number</th>
                                    <th>ID Images</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody id="rg-table-body">
                                @forelse($commuters as $index => $commuter)
                                @php
                                    $discount = $commuter->discount;
                                    $discountImage = $discount?->idImage;
                                    $discountModalId = 'commuterDiscountImagesModal_' . $commuter->id;
                                    $discountFrontUrl = $discountImage && $discountImage->image_front
                                        ? \App\Support\MediaStorage::url($discountImage->image_front)
                                        : null;
                                    $discountBackUrl = $discountImage && $discountImage->image_back
                                        ? \App\Support\MediaStorage::url($discountImage->image_back)
                                        : null;
                                    $hasDiscountImages = $discountFrontUrl || $discountBackUrl;
                                @endphp
                                <tr>
                                    <td class="rg-td-index">{{ $commuters->firstItem() + $index }}</td>
                                    <td>
                                        <div class="rg-user-cell">
                                            <div class="rg-avatar">
                                                {{ strtoupper(substr($commuter->user->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($commuter->user->last_name ?? '?', 0, 1)) }}
                                            </div>
                                            <div>
                                                <p class="rg-user-name mb-0">{{ $commuter->user->first_name ?? '—' }} {{ $commuter->user->last_name ?? '' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="rg-td-muted">{{ $commuter->user->email ?? '—' }}</td>
                                    <td>
                                        <span class="rg-role-badge">
                                            {{ $commuter->discount?->classificationType?->classification_name ?? 'Regular' }}
                                        </span>
                                    </td>
                                    <td class="rg-td-muted">{{ $commuter->discount?->ID_number ?? '—' }}</td>
                                    <td>
                                        @if($hasDiscountImages)
                                            <button type="button" class="btn btn-link btn-sm px-0" data-toggle="modal" data-target="#{{ $discountModalId }}">
                                                <i class="fas fa-id-card mr-1"></i> View ID Images
                                            </button>

                                            <div class="modal fade" id="{{ $discountModalId }}" tabindex="-1" role="dialog" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Commuter ID Images — {{ $commuter->user->first_name ?? '' }} {{ $commuter->user->last_name ?? '' }}</h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <p class="text-muted mb-1">ID Number: <strong>{{ $discount?->ID_number ?? 'N/A' }}</strong></p>
                                                                <p class="text-muted mb-0">Classification: <strong>{{ $discount?->classificationType?->classification_name ?? 'Regular' }}</strong></p>
                                                            </div>
                                                            <div class="row">
                                                                @if($discountFrontUrl)
                                                                    <div class="col-md-6 mb-3">
                                                                        <img src="{{ $discountFrontUrl }}" class="img-fluid rounded shadow-sm" alt="Discount ID Front">
                                                                        <small class="text-muted d-block mt-2">Front View</small>
                                                                    </div>
                                                                @endif
                                                                @if($discountBackUrl)
                                                                    <div class="col-md-6 mb-3">
                                                                        <img src="{{ $discountBackUrl }}" class="img-fluid rounded shadow-sm" alt="Discount ID Back">
                                                                        <small class="text-muted d-block mt-2">Back View</small>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted">No images</span>
                                        @endif
                                    </td>
                                    <td class="rg-td-muted">{{ $commuter->created_at->format('M d, Y') }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="rg-empty">No commuters found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($commuters->hasPages())
                <div id="rg-pagination" class="rg-card-footer">
                    {{ $commuters->links() }}
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
        if (filter && filter.value)        p.set('classification', filter.value);
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
