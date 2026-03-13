@extends('adminlte::auth.auth-page', ['auth_type' => 'login'])

@section('auth_header', 'Two-Factor Verification')

@section('auth_body')
    <p class="text-muted">Enter the 6-digit OTP sent to your email.</p>

    <form action="{{ route('admin.2fa.verify') }}" method="post">
        @csrf
        <div class="input-group mb-3">
            <input type="text" name="otp" class="form-control @error('otp') is-invalid @enderror"
                   placeholder="Enter OTP" maxlength="6" required autofocus>
            <div class="input-group-append">
                <div class="input-group-text"><span class="fas fa-key"></span></div>
            </div>
            @error('otp')
                <span class="invalid-feedback d-block">{{ $message }}</span>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-block">Verify OTP</button>
    </form>
@endsection