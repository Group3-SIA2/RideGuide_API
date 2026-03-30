@extends('adminlte::page')

@php
    $panelPrefix = request()->routeIs('super-admin.*') ? 'super-admin' : 'admin';
    $authorizationIndexRoute     = $panelPrefix . '.user-authorization.index';
    $authorizationStoreRoleRoute = $panelPrefix . '.user-authorization.store-role';
@endphp

@section('title', 'Create Role — RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Create New Role</h4>
            <p class="rg-page-subtitle">Define a role name, optional description, and choose its permissions.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="{{ route($authorizationIndexRoute) }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
@stop

@push('css')
<style>
/* ── Permission group collapse ───────────────────────────────────────── */
.rg-perm-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    user-select: none;
    padding: 6px 0;
    border-radius: 4px;
    transition: background .15s;
}
.rg-perm-group-header:hover {
    background: rgba(0,0,0,.03);
    padding-left: 6px;
}
.rg-perm-group-header .rg-perm-group-meta {
    display: flex;
    align-items: center;
    gap: 6px;
}
.rg-perm-group-header .rg-perm-group-count {
    font-size: .7rem;
    font-weight: 600;
    padding: 1px 6px;
    border-radius: 10px;
    background: rgba(0,0,0,.07);
    color: #6c757d;
}
.rg-perm-group-header .rg-perm-group-count.has-checked {
    background: #d4edda;
    color: #155724;
}
.rg-perm-toggle-btn {
    background: none;
    border: none;
    padding: 0 4px;
    color: #6c757d;
    font-size: .8rem;
    line-height: 1;
    transition: transform .2s, color .2s;
}
.rg-perm-toggle-btn:focus { outline: none; }
.rg-perm-toggle-btn.collapsed { transform: rotate(-90deg); }

.rg-perm-group-body {
    overflow: hidden;
    transition: max-height .25s ease, opacity .2s ease;
    max-height: 2000px;
    opacity: 1;
}
.rg-perm-group-body.collapsed {
    max-height: 0 !important;
    opacity: 0;
}

/* ── Permission search ───────────────────────────────────────────────── */
.rg-perm-search-wrap {
    position: relative;
    margin-bottom: 1rem;
}
.rg-perm-search-wrap .fa-search {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #adb5bd;
    font-size: .8rem;
    pointer-events: none;
}
#perm-search {
    padding-left: 30px;
    border-radius: 6px;
    font-size: .85rem;
    height: 34px;
}
#perm-search-clear {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #adb5bd;
    font-size: .75rem;
    padding: 0 2px;
    display: none;
    cursor: pointer;
}
#perm-search-clear:hover { color: #495057; }

.rg-perm-no-results {
    display: none;
    text-align: center;
    padding: 1.5rem 0;
    color: #6c757d;
    font-size: .85rem;
}

/* ── Checked counter badge in card header ────────────────────────────── */
#perm-checked-badge {
    font-size: .7rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 10px;
    background: #d4edda;
    color: #155724;
    display: none;
}
#perm-checked-badge.visible { display: inline-block; }

/* ── Tighten card body padding ───────────────────────────────────────── */
.rg-permissions-body {
    padding: 1rem 1.25rem;
}
.rg-card-body {
    padding: 1.1rem 1.25rem;
}

/* ── Highlight matched text ──────────────────────────────────────────── */
.perm-highlight {
    background: #fff3cd;
    border-radius: 2px;
    padding: 0 1px;
}
</style>
@endpush

@section('content')

    {{-- Error flash --}}
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    {{-- Validation errors --}}
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

    <form action="{{ route($authorizationStoreRoleRoute) }}" method="POST">
        @csrf

        <div class="row">

            {{-- ── Role Details Card ─────────────────────────────────────── --}}
            <div class="col-12 col-lg-4 mb-4">
                <div class="rg-card h-100">
                    <div class="rg-card-header">
                        <div class="d-flex align-items-center">
                            <span class="rg-card-dot"></span>
                            <h6 class="rg-card-title mb-0">Role Details</h6>
                        </div>
                    </div>
                    <div class="rg-card-body">

                        {{-- Name --}}
                        <div class="form-group">
                            <label for="role-name" class="font-weight-bold">
                                Role Name <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                id="role-name"
                                name="name"
                                class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name') }}"
                                placeholder="e.g. moderator"
                                autocomplete="off"
                                pattern="[a-z][a-z0-9_]*"
                                maxlength="64"
                                required>
                            <small class="form-text text-muted">
                                Lowercase letters, digits, and underscores only. Must start with a letter.
                            </small>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Preview badge --}}
                        <div class="form-group">
                            <label class="font-weight-bold">Preview</label>
                            <div>
                                <span class="rg-role-badge" id="role-name-preview">
                                    <i class="fas fa-tag mr-1 text-muted" style="font-size:.7rem;"></i>
                                    <span id="role-name-preview-text">New Role</span>
                                </span>
                            </div>
                        </div>

                        {{-- Description --}}
                        <div class="form-group mb-0">
                            <label for="role-description" class="font-weight-bold">Description</label>
                            <textarea
                                id="role-description"
                                name="description"
                                class="form-control @error('description') is-invalid @enderror"
                                rows="3"
                                maxlength="255"
                                placeholder="Briefly describe what this role is for…">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                    </div>
                </div>
            </div>

            {{-- ── Permissions Card ──────────────────────────────────────── --}}
            <div class="col-12 col-lg-8 mb-4">
                <div class="rg-card h-100">

                    {{-- Card header: title + checked badge + bulk actions --}}
                    <div class="rg-card-header">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rg-card-dot"></span>
                            <h6 class="rg-card-title mb-0">Permissions</h6>
                            <span id="perm-checked-badge">0 selected</span>
                        </div>
                        <div class="mt-2 d-flex flex-wrap gap-1">
                            <button type="button" id="btn-select-all" class="btn btn-xs btn-outline-success mr-1">
                                <i class="fas fa-check-double"></i> Select All
                            </button>
                            <button type="button" id="btn-deselect-all" class="btn btn-xs btn-outline-danger mr-1">
                                <i class="fas fa-times"></i> Deselect All
                            </button>
                            <button type="button" id="btn-collapse-all" class="btn btn-xs btn-outline-secondary mr-1">
                                <i class="fas fa-compress-alt"></i> Collapse All
                            </button>
                            <button type="button" id="btn-expand-all" class="btn btn-xs btn-outline-secondary">
                                <i class="fas fa-expand-alt"></i> Expand All
                            </button>
                        </div>
                    </div>

                    <div class="rg-card-body rg-permissions-body">

                        {{-- Permission search --}}
                        <div class="rg-perm-search-wrap">
                            <i class="fas fa-search"></i>
                            <input
                                type="text"
                                id="perm-search"
                                class="form-control"
                                placeholder="Search permissions…"
                                autocomplete="off">
                            <button type="button" id="perm-search-clear" title="Clear search">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        {{-- Permission groups --}}
                        @forelse($permissionGroups as $group => $groupPermissions)
                            @php $groupSlug = Str::slug($group); @endphp

                            <div class="mb-3 rg-permission-group-block" data-group="{{ $groupSlug }}">

                                {{-- Group header (click to collapse) --}}
                                <div class="rg-perm-group-header mb-2"
                                     data-target="perm-body-{{ $groupSlug }}"
                                     role="button"
                                     aria-expanded="true">
                                    <div class="rg-perm-group-meta">
                                        <i class="fas fa-folder text-muted" style="font-size:.75rem;"></i>
                                        <h6 class="text-uppercase text-muted font-weight-bold mb-0"
                                            style="letter-spacing:.5px; font-size:0.78rem;">
                                            {{ ucwords(str_replace('_', ' ', $group)) }}
                                        </h6>
                                        <span class="rg-perm-group-count"
                                              data-group-count="{{ $groupSlug }}">
                                            {{ $groupPermissions->count() }}
                                        </span>
                                    </div>
                                    <button type="button"
                                            class="rg-perm-toggle-btn"
                                            tabindex="-1"
                                            aria-label="Toggle group">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </div>

                                {{-- Group body --}}
                                <div class="rg-perm-group-body row" id="perm-body-{{ $groupSlug }}">
                                    @foreach($groupPermissions as $permission)
                                        <div class="col-12 col-sm-6 col-md-4 mb-2 rg-perm-item"
                                             data-perm-label="{{ strtolower($permission->display_name) }}"
                                             data-perm-desc="{{ strtolower($permission->description ?? '') }}">
                                            <div class="custom-control custom-checkbox">
                                                <input
                                                    type="checkbox"
                                                    class="custom-control-input permission-checkbox"
                                                    id="perm_{{ $permission->id }}"
                                                    name="permissions[]"
                                                    value="{{ $permission->id }}"
                                                    data-group="{{ $groupSlug }}"
                                                    {{ in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}>
                                                <label class="custom-control-label perm-label-text"
                                                       for="perm_{{ $permission->id }}">
                                                    {{ $permission->display_name }}
                                                </label>
                                                @if($permission->description)
                                                    <br>
                                                    <small class="text-muted perm-desc-text">{{ $permission->description }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                            </div>

                            @if(!$loop->last)
                                <hr class="rg-perm-divider">
                            @endif

                        @empty
                            <p class="text-muted text-center py-4">
                                <i class="fas fa-info-circle mr-1"></i>
                                No permissions have been defined yet. Run the permission seeder first.
                            </p>
                        @endforelse

                        {{-- Empty search state --}}
                        <p class="rg-perm-no-results" id="perm-no-results">
                            <i class="fas fa-search mr-1"></i>
                            No permissions match "<span id="perm-no-results-term"></span>".
                        </p>

                    </div>{{-- /.rg-permissions-body --}}
                </div>
            </div>

        </div>{{-- /row --}}

        {{-- ── Footer actions ───────────────────────────────────────────── --}}
        <div class="d-flex justify-content-end mb-4">
            <a href="{{ route($authorizationIndexRoute) }}" class="btn btn-secondary mr-2">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus-circle mr-1"></i> Create Role
            </button>
        </div>

    </form>

@stop

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ── helpers ─────────────────────────────────────────────────────── */
    function allCheckboxes()  { return document.querySelectorAll('.permission-checkbox'); }
    function visibleCheckboxes() {
        return [...allCheckboxes()].filter(cb =>
            cb.closest('.rg-perm-item').style.display !== 'none'
        );
    }

    /* ── checked-count badge ─────────────────────────────────────────── */
    var checkedBadge = document.getElementById('perm-checked-badge');
    function updateCheckedBadge() {
        var n = [...allCheckboxes()].filter(cb => cb.checked).length;
        checkedBadge.textContent = n + ' selected';
        checkedBadge.classList.toggle('visible', n > 0);
    }
    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('permission-checkbox')) updateCheckedBadge();
    });

    /* ── per-group checked counts ────────────────────────────────────── */
    function updateGroupCount(groupSlug) {
        var total   = document.querySelectorAll('.permission-checkbox[data-group="' + groupSlug + '"]').length;
        var checked = [...document.querySelectorAll('.permission-checkbox[data-group="' + groupSlug + '"]:checked')].length;
        var badge   = document.querySelector('[data-group-count="' + groupSlug + '"]');
        if (!badge) return;
        badge.textContent = checked > 0 ? checked + ' / ' + total : total;
        badge.classList.toggle('has-checked', checked > 0);
    }
    function updateAllGroupCounts() {
        document.querySelectorAll('[data-group-count]').forEach(function (el) {
            updateGroupCount(el.dataset.groupCount);
        });
    }
    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('permission-checkbox')) {
            updateGroupCount(e.target.dataset.group);
        }
    });

    /* ── select / deselect all ───────────────────────────────────────── */
    document.getElementById('btn-select-all').addEventListener('click', function () {
        visibleCheckboxes().forEach(cb => cb.checked = true);
        updateCheckedBadge();
        updateAllGroupCounts();
    });
    document.getElementById('btn-deselect-all').addEventListener('click', function () {
        visibleCheckboxes().forEach(cb => cb.checked = false);
        updateCheckedBadge();
        updateAllGroupCounts();
    });

    /* ── collapse / expand helpers ───────────────────────────────────── */
    function collapseGroup(header) {
        var bodyId = header.dataset.target;
        var body   = document.getElementById(bodyId);
        var btn    = header.querySelector('.rg-perm-toggle-btn');
        if (!body) return;
        body.classList.add('collapsed');
        btn.classList.add('collapsed');
        header.setAttribute('aria-expanded', 'false');
    }
    function expandGroup(header) {
        var bodyId = header.dataset.target;
        var body   = document.getElementById(bodyId);
        var btn    = header.querySelector('.rg-perm-toggle-btn');
        if (!body) return;
        body.classList.remove('collapsed');
        btn.classList.remove('collapsed');
        header.setAttribute('aria-expanded', 'true');
    }

    /* ── per-group toggle on header click ────────────────────────────── */
    document.querySelectorAll('.rg-perm-group-header').forEach(function (header) {
        header.addEventListener('click', function (e) {
            if (e.target.closest('.custom-checkbox')) return; // don't trigger on checkbox clicks
            var isExpanded = header.getAttribute('aria-expanded') === 'true';
            isExpanded ? collapseGroup(header) : expandGroup(header);
        });
    });

    /* ── collapse all / expand all ───────────────────────────────────── */
    document.getElementById('btn-collapse-all').addEventListener('click', function () {
        document.querySelectorAll('.rg-perm-group-header').forEach(collapseGroup);
    });
    document.getElementById('btn-expand-all').addEventListener('click', function () {
        document.querySelectorAll('.rg-perm-group-header').forEach(expandGroup);
    });

    /* ── client-side permission search ──────────────────────────────── */
    var searchInput   = document.getElementById('perm-search');
    var searchClear   = document.getElementById('perm-search-clear');
    var noResults     = document.getElementById('perm-no-results');
    var noResultsTerm = document.getElementById('perm-no-results-term');

    function highlight(text, term) {
        if (!term) return text;
        var escaped = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return text.replace(new RegExp('(' + escaped + ')', 'gi'),
            '<mark class="perm-highlight">$1</mark>');
    }

    function clearHighlights() {
        document.querySelectorAll('.perm-label-text, .perm-desc-text').forEach(function (el) {
            el.innerHTML = el.textContent; // strip any marks
        });
    }

    function runSearch(term) {
        var q = term.trim().toLowerCase();
        searchClear.style.display = q ? 'block' : 'none';

        clearHighlights();

        var totalVisible = 0;

        document.querySelectorAll('.rg-permission-group-block').forEach(function (groupBlock) {
            var items = groupBlock.querySelectorAll('.rg-perm-item');
            var groupVisible = 0;

            items.forEach(function (item) {
                var label = item.dataset.permLabel || '';
                var desc  = item.dataset.permDesc  || '';
                var match = !q || label.includes(q) || desc.includes(q);

                item.style.display = match ? '' : 'none';

                if (match) {
                    groupVisible++;
                    totalVisible++;

                    if (q) {
                        var labelEl = item.querySelector('.perm-label-text');
                        var descEl  = item.querySelector('.perm-desc-text');
                        if (labelEl) labelEl.innerHTML = highlight(labelEl.textContent, q);
                        if (descEl)  descEl.innerHTML  = highlight(descEl.textContent,  q);
                    }
                }
            });

            // show / hide the whole group block + its divider
            var groupBlock_display = groupVisible > 0 ? '' : 'none';
            groupBlock.style.display = groupBlock_display;
            var nextHr = groupBlock.nextElementSibling;
            if (nextHr && nextHr.classList.contains('rg-perm-divider')) {
                nextHr.style.display = groupBlock_display;
            }

            // auto-expand groups that have matches during a search
            if (q && groupVisible > 0) {
                var header = groupBlock.querySelector('.rg-perm-group-header');
                if (header) expandGroup(header);
            }
        });

        // empty state
        noResults.style.display    = (q && totalVisible === 0) ? 'block' : 'none';
        noResultsTerm.textContent  = term.trim();
    }

    searchInput.addEventListener('input', function () { runSearch(this.value); });
    searchClear.addEventListener('click', function () {
        searchInput.value = '';
        runSearch('');
        searchInput.focus();
    });

    /* ── live name preview ───────────────────────────────────────────── */
    var nameInput   = document.getElementById('role-name');
    var previewText = document.getElementById('role-name-preview-text');

    function updatePreview() {
        var raw = nameInput.value.trim();
        previewText.textContent = raw
            ? raw.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
            : 'New Role';
    }
    nameInput.addEventListener('input', updatePreview);
    updatePreview();

    /* ── sanitise name input ─────────────────────────────────────────── */
    nameInput.addEventListener('keyup', function () {
        var s = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
        if (this.value !== s) this.value = s;
        updatePreview();
    });

    /* ── init counts ─────────────────────────────────────────────────── */
    updateCheckedBadge();
    updateAllGroupCounts();
});
</script>
@stop