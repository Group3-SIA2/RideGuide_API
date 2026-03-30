@extends('adminlte::page')

@section('title', 'Create User — RideGuide Admin')

@php
    $panelPrefix = request()->routeIs('super-admin.*') ? 'super-admin' : 'admin';
    $indexRoute = $panelPrefix . '.user-status.index';
    $storeRoute = $panelPrefix . '.user-status.store';
    $verifyRoute = $panelPrefix . '.user-status.verify-email';
    $resendRoute = $panelPrefix . '.user-status.resend-otp';

    $indexUrl = route($indexRoute);
    $storeUrl = route($storeRoute);
    $verifyUrl = route($verifyRoute);
    $resendUrl = route($resendRoute);
@endphp

@section('content_header')
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h4 class="mb-0">Create New User</h4>
            <p class="text-muted mb-0">Add a user and verify their email via OTP.</p>
        </div>
        <div>
            <a href="{{ $indexUrl }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
@stop

@section('content')

    {{-- Error / Success flashes --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12 col-lg-7">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">User Details</h5>
                    <small class="text-muted">All fields required</small>
                </div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle mr-1"></i> Please fix the errors below.
                        </div>
                    @endif

                    <form method="POST" action="{{ $storeUrl }}">
                        @csrf

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="first_name">First name</label>
                                <input id="first_name" name="first_name" type="text" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name') }}" required>
                                @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="last_name">Last name</label>
                                <input id="last_name" name="last_name" type="text" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name') }}" required>
                                @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email address</label>
                            <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                            <small class="form-text text-muted">OTP will be sent to this address.</small>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="password">Password</label>
                                <div class="input-group">
                                    <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" required>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" id="togglePassword"><i class="fas fa-eye"></i></button>
                                    </div>
                                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="password_confirmation">Confirm Password</label>
                                <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" required>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button class="btn btn-primary" type="submit"><i class="fas fa-plus-circle mr-1"></i> Create &amp; Send OTP</button>
                            <a href="{{ $indexUrl }}" class="btn btn-secondary ml-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title">What happens next</h6>
                    <p class="card-text text-muted">A 6-digit code will be emailed to the address you provided. Use the modal to verify and activate the account.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- OTP modal (same flow as before) --}}
    <div class="modal fade" id="otpVerificationModal" tabindex="-1" role="dialog" aria-labelledby="otpModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="otpModalLabel">Verify email</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div id="otpAlertBox" class="alert d-none"></div>
                    <p class="text-muted small">We sent a 6-digit code to <strong id="otpEmailDisplay">&nbsp;</strong></p>
                    <input id="otpInput" class="form-control" maxlength="6" inputmode="numeric" placeholder="Enter 6-digit code">
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <button type="button" id="resendOtpBtn" class="btn btn-link p-0">Resend code</button>
                        <small class="text-muted">Expires in <span id="otpCountdown">10:00</span></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="verifyOtpBtn" class="btn btn-primary w-100">Verify &amp; Activate</button>
                </div>
            </div>
        </div>
    </div>

@stop

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // password toggle
    document.getElementById('togglePassword')?.addEventListener('click', function () {
        var p = document.getElementById('password');
        if (!p) return;
        var t = p.getAttribute('type') === 'password' ? 'text' : 'password';
        p.setAttribute('type', t);
        this.querySelector('i').classList.toggle('fa-eye');
        this.querySelector('i').classList.toggle('fa-eye-slash');
    });

    // OTP flow
    const otpConfig = {
        open: @json(session('show_otp_modal', false)),
        email: @json(session('otp_email', '')),
        userId: @json(session('otp_user_id')),
        verifyUrl: @json($verifyUrl),
        resendUrl: @json($resendUrl),
        csrf: @json(csrf_token()),
        ttl: 600
    };

    const otpModalEl = document.getElementById('otpVerificationModal');
    const otpEmailDisplay = document.getElementById('otpEmailDisplay');
    const otpInput = document.getElementById('otpInput');
    const otpAlertBox = document.getElementById('otpAlertBox');
    const verifyBtn = document.getElementById('verifyOtpBtn');
    const resendBtn = document.getElementById('resendOtpBtn');
    const countdownEl = document.getElementById('otpCountdown');
    let timer = null;

    function showAlert(type, msg) {
        if (!otpAlertBox) return;
        otpAlertBox.className = 'alert alert-' + type;
        otpAlertBox.textContent = msg;
        otpAlertBox.classList.remove('d-none');
    }

    function hideAlert() { if (otpAlertBox) otpAlertBox.classList.add('d-none'); }

    function startCountdown(s) {
        clearInterval(timer);
        let rem = s;
        resendBtn.disabled = true;
        timer = setInterval(function () {
            countdownEl.textContent = Math.floor(rem/60).toString().padStart(2,'0') + ':' + (rem%60).toString().padStart(2,'0');
            rem--;
            if (rem < 0) { clearInterval(timer); resendBtn.disabled = false; }
        }, 1000);
    }

    if (otpConfig.open && otpConfig.userId) {
        otpEmailDisplay.textContent = otpConfig.email || 'pending';
        $('#otpVerificationModal').modal({backdrop: 'static', keyboard: false});
        startCountdown(otpConfig.ttl);
    }

    async function postJson(url, payload) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': otpConfig.csrf, 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json().catch(()=>({}));
        if (!res.ok) throw new Error(data.message || 'Request failed');
        return data;
    }

    verifyBtn?.addEventListener('click', async function () {
        hideAlert();
        if (!otpConfig.userId) { showAlert('danger', 'Missing pending user. Refresh page.'); return; }
        const code = (otpInput.value || '').trim();
        if (!/^\d{6}$/.test(code)) { showAlert('warning', 'Enter 6-digit code'); return; }
        this.disabled = true;
        try {
            const r = await postJson(otpConfig.verifyUrl, { otp: code, user_id: otpConfig.userId });
            showAlert('success', r.message || 'Verified');
            setTimeout(()=>{ if (r.redirect) window.location.href = r.redirect; else location.reload(); }, 900);
        } catch (e) { showAlert('danger', e.message); }
        finally { this.disabled = false; }
    });

    resendBtn?.addEventListener('click', async function () {
        hideAlert();
        if (!otpConfig.userId) { showAlert('danger','Missing pending user.'); return; }
        this.disabled = true;
        try {
            const r = await postJson(otpConfig.resendUrl, { user_id: otpConfig.userId });
            showAlert('success', r.message || 'Code sent');
            startCountdown(otpConfig.ttl);
        } catch (e) { showAlert('danger', e.message); }
        finally { this.disabled = false; }
    });

});
</script>
@endpush