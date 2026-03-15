@extends('adminlte::page')

@section('title', 'Backup & Restore — RideGuide Admin')

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Backup & Restore</h4>
            <p class="rg-page-subtitle">Manage database backups stored in Supabase Storage.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="rg-badge" id="rg-total">{{ $backups->count() }} backup{{ $backups->count() !== 1 ? 's' : '' }}</span>
            <button type="button" id="rg-create-backup" class="rg-btn rg-btn-primary rg-btn-sm">
                <i class="fas fa-plus"></i> Create Backup
            </button>
        </div>
    </div>
@stop

@section('content')

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="rg-alert rg-alert-success mb-3" id="rg-flash-success">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="rg-alert rg-alert-danger mb-3" id="rg-flash-error">
        <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
    </div>
    @endif

    {{-- Dynamic Alert (for AJAX responses) --}}
    <div class="rg-alert rg-alert-success mb-3" id="rg-alert-success" style="display:none;">
        <i class="fas fa-check-circle"></i> <span id="rg-alert-success-text"></span>
    </div>
    <div class="rg-alert rg-alert-danger mb-3" id="rg-alert-error" style="display:none;">
        <i class="fas fa-exclamation-circle"></i> <span id="rg-alert-error-text"></span>
    </div>

    {{-- Error State --}}
    @if($error)
    <div class="rg-alert rg-alert-danger mb-3">
        <i class="fas fa-exclamation-triangle"></i> {{ $error }}
    </div>
    @endif

    {{-- Backup Table --}}
    <div class="row">
        <div class="col-12">
            <div class="rg-card">
                <div class="rg-card-header">
                    <div class="d-flex align-items-center">
                        <span class="rg-card-dot"></span>
                        <h6 class="rg-card-title mb-0">Backup Files</h6>
                    </div>
                </div>
                <div class="rg-card-body p-0">
                    <div class="table-responsive">
                        <table class="rg-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Filename</th>
                                    <th>Size</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rg-table-body">
                                @include('admin.backups._rows')
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Restore Confirmation Modal --}}
    <div id="rg-restore-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
        <div style="background:var(--rg-card); border:1px solid var(--rg-card-border); border-radius:12px; padding:28px 32px; max-width:480px; width:90%; box-shadow:0 8px 32px rgba(0,0,0,0.2);">
            <h5 style="margin:0 0 8px; font-weight:700; color:var(--rg-text); font-size:1.1rem;">
                <i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i> Confirm Restore
            </h5>
            <p style="color:var(--rg-text-muted); font-size:0.88rem; margin:0 0 6px;">
                You are about to restore the database from:
            </p>
            <p style="color:var(--rg-text); font-weight:600; font-size:0.9rem; margin:0 0 16px; word-break:break-all;" id="rg-restore-filename-display"></p>
            <p style="color:#e53e3e; font-size:0.82rem; margin:0 0 20px; font-weight:500;">
                This will OVERWRITE the current database. This action cannot be undone.
            </p>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" id="rg-restore-cancel" class="rg-btn rg-btn-secondary rg-btn-sm">Cancel</button>
                <button type="button" id="rg-restore-confirm" class="rg-btn rg-btn-danger rg-btn-sm">
                    <i class="fas fa-undo"></i> Restore Database
                </button>
            </div>
        </div>
    </div>

    {{-- Loading Overlay --}}
    <div id="rg-loading-overlay" style="display:none; position:fixed; inset:0; z-index:10000; background:rgba(0,0,0,0.55); align-items:center; justify-content:center;">
        <div style="background:var(--rg-card); border-radius:12px; padding:32px 40px; text-align:center; box-shadow:0 8px 32px rgba(0,0,0,0.25); min-width:340px;">
            <div class="rg-spinner" style="margin:0 auto 16px;"></div>
            <p id="rg-loading-text" style="color:var(--rg-text); font-weight:600; font-size:0.95rem; margin:0 0 16px;">Processing…</p>

            {{-- Step indicator (hidden by default, shown during restore) --}}
            <div id="rg-step-indicator" style="display:none;">
                <div style="display:flex; align-items:center; justify-content:center; gap:8px; margin-bottom:12px;">
                    <div id="rg-step-1" class="rg-step-dot rg-step-active">1</div>
                    <div class="rg-step-line"><div id="rg-step-line-fill" class="rg-step-line-fill"></div></div>
                    <div id="rg-step-2" class="rg-step-dot">2</div>
                </div>
                <div style="display:flex; justify-content:space-between; gap:16px;">
                    <span id="rg-step-1-label" class="rg-step-label rg-step-label-active">Safety Backup</span>
                    <span id="rg-step-2-label" class="rg-step-label">Restore DB</span>
                </div>
            </div>
        </div>
    </div>

@stop

@section('css')
<style>
    /* Spinner */
    .rg-spinner {
        width: 36px;
        height: 36px;
        border: 3px solid var(--rg-card-border);
        border-top-color: var(--rg-accent);
        border-radius: 50%;
        animation: rg-spin 0.7s linear infinite;
    }
    @keyframes rg-spin {
        to { transform: rotate(360deg); }
    }

    /* Action buttons in table */
    .rg-action-group {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .rg-btn-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 6px;
        border: 1px solid var(--rg-card-border);
        background: var(--rg-card);
        color: var(--rg-text-muted);
        cursor: pointer;
        transition: all 0.15s ease;
        font-size: 0.82rem;
    }
    .rg-btn-icon:hover {
        border-color: var(--rg-accent);
        color: var(--rg-accent);
        background: var(--rg-accent-dim);
    }
    .rg-btn-icon.rg-btn-icon-danger:hover {
        border-color: #e53e3e;
        color: #e53e3e;
        background: rgba(229,62,62,0.08);
    }
    .rg-btn-icon.rg-btn-icon-warning:hover {
        border-color: #f59e0b;
        color: #f59e0b;
        background: rgba(245,158,11,0.08);
    }

    /* Tooltip style */
    .rg-btn-icon[title] {
        position: relative;
    }

    /* Disabled state */
    .rg-btn[disabled],
    .rg-btn-icon[disabled] {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    /* Step indicator */
    .rg-step-dot {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: 2px solid var(--rg-card-border);
        background: var(--rg-card);
        color: var(--rg-text-subtle);
        font-size: 0.72rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        flex-shrink: 0;
    }
    .rg-step-dot.rg-step-active {
        border-color: var(--rg-accent);
        background: var(--rg-accent);
        color: #fff;
    }
    .rg-step-dot.rg-step-done {
        border-color: #10b981;
        background: #10b981;
        color: #fff;
    }
    .rg-step-line {
        width: 60px;
        height: 3px;
        background: var(--rg-card-border);
        border-radius: 2px;
        overflow: hidden;
    }
    .rg-step-line-fill {
        width: 0%;
        height: 100%;
        background: var(--rg-accent);
        border-radius: 2px;
        transition: width 0.4s ease;
    }
    .rg-step-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--rg-text-subtle);
        transition: color 0.3s ease;
    }
    .rg-step-label-active {
        color: var(--rg-accent);
    }
    .rg-step-label-done {
        color: #10b981;
    }
</style>
@stop

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var tbody     = document.getElementById('rg-table-body');
    var totalEl   = document.getElementById('rg-total');

    // ── Alert helpers ──────────────────────────────────────
    function showSuccess(msg) {
        var el = document.getElementById('rg-alert-success');
        document.getElementById('rg-alert-success-text').textContent = msg;
        el.style.display = 'flex';
        setTimeout(function() { el.style.display = 'none'; }, 6000);
    }
    function showError(msg) {
        var el = document.getElementById('rg-alert-error');
        document.getElementById('rg-alert-error-text').textContent = msg;
        el.style.display = 'flex';
        setTimeout(function() { el.style.display = 'none'; }, 8000);
    }
    function hideAlerts() {
        document.getElementById('rg-alert-success').style.display = 'none';
        document.getElementById('rg-alert-error').style.display = 'none';
    }

    // ── Loading overlay ────────────────────────────────────
    function showLoading(text) {
        document.getElementById('rg-loading-text').textContent = text || 'Processing…';
        document.getElementById('rg-loading-overlay').style.display = 'flex';
        // Reset step indicator
        document.getElementById('rg-step-indicator').style.display = 'none';
    }
    function hideLoading() {
        document.getElementById('rg-loading-overlay').style.display = 'none';
        document.getElementById('rg-step-indicator').style.display = 'none';
    }

    // ── Step indicator for restore process ─────────────────
    function updateLoadingStep(step) {
        var indicator = document.getElementById('rg-step-indicator');
        var step1Dot  = document.getElementById('rg-step-1');
        var step2Dot  = document.getElementById('rg-step-2');
        var lineFill  = document.getElementById('rg-step-line-fill');
        var step1Lbl  = document.getElementById('rg-step-1-label');
        var step2Lbl  = document.getElementById('rg-step-2-label');

        indicator.style.display = 'block';

        if (step === 1) {
            step1Dot.className = 'rg-step-dot rg-step-active';
            step2Dot.className = 'rg-step-dot';
            lineFill.style.width = '0%';
            step1Lbl.className = 'rg-step-label rg-step-label-active';
            step2Lbl.className = 'rg-step-label';
        } else if (step === 2) {
            step1Dot.className = 'rg-step-dot rg-step-done';
            step2Dot.className = 'rg-step-dot rg-step-active';
            lineFill.style.width = '100%';
            step1Lbl.className = 'rg-step-label rg-step-label-done';
            step2Lbl.className = 'rg-step-label rg-step-label-active';
        } else if (step === 3) {
            // All done — both dots green
            step1Dot.className = 'rg-step-dot rg-step-done';
            step2Dot.className = 'rg-step-dot rg-step-done';
            lineFill.style.width = '100%';
            step1Lbl.className = 'rg-step-label rg-step-label-done';
            step2Lbl.className = 'rg-step-label rg-step-label-done';
        }
    }

    // ── Hide loading with optional delay (for step completion visibility) ──
    function hideLoadingWithDelay(callback) {
        document.getElementById('rg-loading-text').textContent = 'Done!';
        updateLoadingStep(3);
        setTimeout(function () {
            hideLoading();
            if (callback) callback();
        }, 1500);
    }

    // ── Refresh backup list via AJAX ───────────────────────
    function refreshList() {
        tbody.style.opacity = '0.35';
        fetch("{{ route('admin.backups.index') }}", {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            tbody.innerHTML     = d.rows;
            tbody.style.opacity = '1';
            if (totalEl) {
                var count = d.total || 0;
                totalEl.textContent = count + ' backup' + (count !== 1 ? 's' : '');
            }
            bindActions();
        })
        .catch(function() {
            tbody.style.opacity = '1';
            showError('Failed to refresh backup list.');
        });
    }

    // ── Create Backup ──────────────────────────────────────
    document.getElementById('rg-create-backup').addEventListener('click', function () {
        hideAlerts();
        showLoading('Creating database backup…');

        fetch("{{ route('admin.backups.create') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            hideLoading();
            if (d.success) {
                showSuccess(d.message || 'Backup created successfully.');
                refreshList();
            } else {
                showError(d.message || 'Backup creation failed.');
            }
        })
        .catch(function() {
            hideLoading();
            showError('An unexpected error occurred while creating the backup.');
        });
    });

    // ── Download Backup ────────────────────────────────────
    function handleDownload(filename) {
        window.location.href = "{{ url('admin/backups') }}/" + encodeURIComponent(filename) + "/download";
    }

    // ── Restore Backup ─────────────────────────────────────
    var restoreModal   = document.getElementById('rg-restore-modal');
    var restoreFilename = '';

    function openRestoreModal(filename) {
        restoreFilename = filename;
        document.getElementById('rg-restore-filename-display').textContent = filename;
        restoreModal.style.display = 'flex';
    }

    document.getElementById('rg-restore-cancel').addEventListener('click', function () {
        restoreModal.style.display = 'none';
        restoreFilename = '';
    });

    // Close modal on backdrop click
    restoreModal.addEventListener('click', function (e) {
        if (e.target === restoreModal) {
            restoreModal.style.display = 'none';
            restoreFilename = '';
        }
    });

    document.getElementById('rg-restore-confirm').addEventListener('click', function () {
        if (!restoreFilename) return;
        restoreModal.style.display = 'none';
        hideAlerts();

        // Step 1: Show backup-first indication
        showLoading('Step 1 of 2 — Creating a safety backup before restore…');
        updateLoadingStep(1);

        fetch("{{ url('admin/backups') }}/" + encodeURIComponent(restoreFilename) + "/restore", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                hideLoadingWithDelay(function () {
                    showSuccess(d.message || 'Database restored successfully.');
                    refreshList();
                });
            } else {
                hideLoading();
                showError(d.message || 'Database restore failed.');
            }
        })
        .catch(function() {
            hideLoading();
            showError('An unexpected error occurred during the restore.');
        });

        // Simulate step 2 indication after a delay (the server does both steps sequentially)
        setTimeout(function () {
            var overlay = document.getElementById('rg-loading-overlay');
            if (overlay.style.display === 'flex') {
                document.getElementById('rg-loading-text').textContent = 'Step 2 of 2 — Restoring database from backup…';
                updateLoadingStep(2);
            }
        }, 8000);

        restoreFilename = '';
    });

    // ── Bind action buttons on table rows ──────────────────
    function bindActions() {
        document.querySelectorAll('.rg-download-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                handleDownload(this.getAttribute('data-filename'));
            });
        });
        document.querySelectorAll('.rg-restore-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                openRestoreModal(this.getAttribute('data-filename'));
            });
        });
    }

    // Initial bind
    bindActions();
});
</script>
@stop
