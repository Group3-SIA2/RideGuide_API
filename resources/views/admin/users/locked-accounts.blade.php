@extends('adminlte::page')

@section('title', 'Locked Admin Accounts — RideGuide Admin')

@php
    $panelPrefix = request()->routeIs('super-admin.*') ? 'super-admin' : 'admin';
@endphp

@section('content_header')
    <div class="rg-page-header">
        <div>
            <h4 class="rg-page-title">Locked Admin Accounts</h4>
            <p class="rg-page-subtitle">Manage and monitor locked admin and super admin accounts.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="{{ route($panelPrefix . '.user-status.index') }}" class="rg-btn rg-btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
            <span class="rg-badge" id="rg-locked-count">0 locked</span>
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
                        <h6 class="rg-card-title mb-0">Locked Accounts List</h6>
                    </div>
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
                                    <th>Lock Reason</th>
                                    <th>Locked Since</th>
                                    <th>Unlock Time</th>
                                    <th>Time Remaining</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rg-locked-table">
                                <!-- Populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    <div id="rg-no-locked" class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-info-circle"></i> No locked admin accounts at this time.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Unlock Account Modal -->
    <div class="modal fade" id="unlockAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Unlock Admin Account</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="unlockAccountForm">
                    <div class="modal-body">
                        <p>Are you sure you want to unlock this account?</p>
                        <div class="alert alert-warning">
                            <strong><i class="fas fa-exclamation-triangle"></i> Warning:</strong>
                            Unlocking an account will allow it to attempt login again immediately.
                        </div>
                        <input type="hidden" id="unlockUserId" name="user_id">
                        <div class="form-group">
                            <label>Admin Account:</label>
                            <p id="unlockAccountEmail" class="text-muted"></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">Unlock Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Locked Account Password</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="resetPasswordForm">
                    <div class="modal-body">
                        <input type="hidden" id="resetUserId" name="user_id">
                        <div class="form-group mb-3">
                            <label>Admin Account:</label>
                            <p id="resetAccountEmail" class="text-muted"></p>
                        </div>
                        <!-- Password will be generated; generated field below is used for submission -->
                        <div class="form-group mb-3">
                            <label for="generatedPassword" class="form-label">Generated Password</label>
                            <div class="input-group">
                                <input type="text" id="generatedPassword" class="form-control" placeholder="Click Generate to create password" readonly>
                                <div class="input-group-append">
                                    <button type="button" id="generatePasswordBtn" class="btn btn-secondary">Generate</button>
                                    <button type="button" id="copyPasswordBtn" class="btn btn-outline-primary">Copy</button>
                                </div>
                            </div>
                            <small class="text-muted">Generates an 8-character password with uppercase, lowercase, number and special character.</small>
                        </div>
                        <div id="rg-feedback" style="display: none;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@stop

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('rg-locked-table');
    const noLocked = document.getElementById('rg-no-locked');
    const lockedCount = document.getElementById('rg-locked-count');
    const panelPrefix = '{{ $panelPrefix }}';
    const feedback = document.getElementById('rg-feedback');

    function showNotice(type, message) {
        if (!feedback) {
            return;
        }

        const typeClass = type === 'success' ? 'alert-success' : (type === 'warning' ? 'alert-warning' : 'alert-danger');
        feedback.className = `alert ${typeClass} mb-3`;
        feedback.innerHTML = `<i class="fas fa-info-circle mr-1"></i> ${message}`;
        feedback.style.display = 'block';

        window.clearTimeout(showNotice._timer);
        showNotice._timer = window.setTimeout(() => {
            feedback.style.display = 'none';
        }, 4000);
    }

    // Load locked accounts
    function loadLockedAccounts() {
        fetch(`/{{ $panelPrefix }}/security/locked-accounts/api`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const accounts = d.data.locked_accounts;
                table.innerHTML = '';
                
                if (accounts.length === 0) {
                    table.closest('table').style.display = 'none';
                    noLocked.style.display = 'block';
                    lockedCount.textContent = '0 locked';
                } else {
                    table.closest('table').style.display = 'table';
                    noLocked.style.display = 'none';
                    lockedCount.textContent = accounts.length + ' locked';

                    accounts.forEach((account, idx) => {
                        const row = document.createElement('tr');
                        const lockReason = account.lock_reason === 'failed_login_attempts' 
                            ? 'Failed Login Attempts' 
                            : 'Admin Initiated';
                        const lockedTime = new Date(account.locked_at).toLocaleString();
                        const unlocksAt = new Date(account.locked_until);
                        const now = new Date();
                        const timeRemaining = Math.max(0, Math.floor((unlocksAt - now) / 1000 / 60));
                        const hours = Math.floor(timeRemaining / 60);
                        const mins = timeRemaining % 60;
                        const timeRemainingText = hours > 0 ? `${hours}h ${mins}m` : `${mins}m`;

                        row.innerHTML = `
                            <td>${idx + 1}</td>
                            <td>${account.first_name || ''} ${account.last_name || ''}</td>
                            <td>${account.email}</td>
                            <td>${account.roles[0]?.name ? account.roles[0].name.replace('_', ' ').toUpperCase() : 'N/A'}</td>
                            <td><span class="badge bg-danger">${lockReason}</span></td>
                            <td>${lockedTime}</td>
                            <td>${unlocksAt.toLocaleString()}</td>
                            <td>
                                ${timeRemaining > 0 
                                    ? `<span class="badge bg-warning">${timeRemainingText}</span>` 
                                    : `<span class="badge bg-success">Ready to unlock</span>`}
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="openUnlockModal('${account.id}', '${account.email}')">
                                    <i class="fas fa-lock-open"></i> Unlock
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="openResetPasswordModal('${account.id}', '${account.email}')">
                                    <i class="fas fa-key"></i> Reset Password
                                </button>
                            </td>
                        `;
                        table.appendChild(row);
                    });
                }
            }
        })
        .catch(err => {
            console.error('Error loading locked accounts:', err);
            noLocked.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error loading locked accounts. Please refresh the page.';
        });
    }

    // Load on page load
    loadLockedAccounts();

    // Refresh every 30 seconds
    setInterval(loadLockedAccounts, 30000);

    // Password generator utilities
    function generatePassword() {
        const upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        const lower = 'abcdefghijklmnopqrstuvwxyz';
        const numbers = '0123456789';
        const special = '!@#$%^&*()-_=+[]{};:,.<>?';

        let pwd = '';
        pwd += upper.charAt(Math.floor(Math.random() * upper.length));
        pwd += lower.charAt(Math.floor(Math.random() * lower.length));
        pwd += numbers.charAt(Math.floor(Math.random() * numbers.length));
        pwd += special.charAt(Math.floor(Math.random() * special.length));

        const all = upper + lower + numbers + special;
        for (let i = 4; i < 8; i++) {
            pwd += all.charAt(Math.floor(Math.random() * all.length));
        }

        // Shuffle
        return pwd.split('').sort(() => 0.5 - Math.random()).join('');
    }

    // Wire up generate and copy buttons
    const genBtn = document.getElementById('generatePasswordBtn');
    const copyBtn = document.getElementById('copyPasswordBtn');
    const genInput = document.getElementById('generatedPassword');

    if (genBtn) {
        genBtn.addEventListener('click', function () {
            const pwd = generatePassword();
            if (genInput) genInput.value = pwd;
        });
    }

    if (copyBtn) {
        copyBtn.addEventListener('click', async function () {
            const text = genInput ? genInput.value : '';
            if (!text) {
                showNotice('warning', 'No password to copy. Generate one first.');
                return;
            }
            try {
                await navigator.clipboard.writeText(text);
                showNotice('success', 'Password copied to clipboard.');
            } catch (e) {
                // Fallback
                if (genInput && genInput.select) genInput.select();
                try {
                    document.execCommand('copy');
                    showNotice('success', 'Password copied to clipboard.');
                } catch (err) {
                    showNotice('error', 'Unable to copy to clipboard.');
                }
            }
        });
    }

    window.showNotice = showNotice;
});

// Unlock account modal
function openUnlockModal(userId, email) {
    document.getElementById('unlockUserId').value = userId;
    document.getElementById('unlockAccountEmail').textContent = email;
    $('#unlockAccountModal').modal('show');
}

// Reset password modal
function openResetPasswordModal(userId, email) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetAccountEmail').textContent = email;
    const genInput = document.getElementById('generatedPassword');
    if (genInput) genInput.value = '';
    $('#resetPasswordModal').modal('show');
}

// Handle unlock form
document.getElementById('unlockAccountForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const userId = document.getElementById('unlockUserId').value;
    const panelPrefix = document.querySelector('[data-panel-prefix]')?.dataset.panelPrefix || 
                       (window.location.pathname.includes('/super-admin/') ? 'super-admin' : 'admin');
    
    fetch(`/${panelPrefix}/security/unlock/${userId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            if (window.showNotice) window.showNotice('success', 'Account unlocked successfully.');
            $('#unlockAccountModal').modal('hide');
            location.reload();
        } else {
            if (window.showNotice) window.showNotice('error', d.message || 'Failed to unlock account.');
        }
    })
    .catch(err => {
        if (window.showNotice) window.showNotice('error', err.message || 'Request failed.');
    });
});

// Handle reset password form
document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const userId = document.getElementById('resetUserId').value;
    const genInput = document.getElementById('generatedPassword');
    const password = genInput ? genInput.value : '';
    const passwordConfirmation = password;
    const panelPrefix = window.location.pathname.includes('/super-admin/') ? 'super-admin' : 'admin';

    if (!password) {
        if (window.showNotice) window.showNotice('warning', 'Generate a password first.');
        return;
    }

    fetch(`/${panelPrefix}/security/reset-password/${userId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            password: password,
            password_confirmation: passwordConfirmation
        })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            if (window.showNotice) window.showNotice('success', 'Password reset successfully. Account has been unlocked.');
            $('#resetPasswordModal').modal('hide');
            location.reload();
        } else {
            if (window.showNotice) window.showNotice('error', d.message || 'Failed to reset password.');
        }
    })
    .catch(err => {
        if (window.showNotice) window.showNotice('error', err.message || 'Request failed.');
    });
});
</script>
@stop
