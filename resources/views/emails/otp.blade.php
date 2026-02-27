<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: #F0F2F5;
            padding: 40px 16px;
        }

        .wrapper {
            max-width: 520px;
            margin: 0 auto;
        }

        .brand {
            text-align: center;
            margin-bottom: 24px;
        }

        .brand span {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
            letter-spacing: -0.3px;
        }

        .brand span em {
            color: #3B7DD8;
            font-style: normal;
        }

        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 40px 36px;
            border: 1px solid #E4E7EC;
        }

        .badge {
            display: inline-block;
            background: #EEF4FF;
            color: #3B7DD8;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 100px;
            letter-spacing: 0.3px;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        h2 {
            font-size: 20px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 8px;
        }

        .subtitle {
            font-size: 14px;
            color: #6B7280;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .otp-block {
            background: #F8FAFF;
            border: 1.5px dashed #3B7DD8;
            border-radius: 12px;
            text-align: center;
            padding: 20px;
            margin-bottom: 24px;
        }

        .otp-block .label {
            font-size: 11px;
            font-weight: 600;
            color: #9CA3AF;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .otp-block .code {
            font-size: 36px;
            font-weight: 600;
            color: #111827;
            letter-spacing: 10px;
        }

        .expiry {
            font-size: 13px;
            color: #6B7280;
            text-align: center;
            margin-bottom: 24px;
        }

        .expiry strong {
            color: #111827;
        }

        .divider {
            border: none;
            border-top: 1px solid #F3F4F6;
            margin-bottom: 20px;
        }

        .warning {
            font-size: 13px;
            color: #9CA3AF;
            text-align: center;
            line-height: 1.6;
        }

        .footer {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: #B0B7C3;
        }
    </style>
</head>
<body>
    <div class="wrapper">

        <div class="brand">
            <span>Ride<em>Guide</em></span>
        </div>

        <div class="card">

            @if($type === 'email_verification')
                <div class="badge">Email Verification</div>
                <h2>Verify your email</h2>
                <p class="subtitle">Hi <strong>{{ $recipientEmail }}</strong>, use the code below to verify your email address.</p>
            @elseif($type === 'login_2fa')
                <div class="badge">Login Verification</div>
                <h2>Complete your login</h2>
                <p class="subtitle">Hi <strong>{{ $recipientEmail }}</strong>, use the code below to finish signing in to your account.</p>
            @elseif($type === 'password_reset')
                <div class="badge">Password Reset</div>
                <h2>Reset your password</h2>
                <p class="subtitle">Hi <strong>{{ $recipientEmail }}</strong>, use the code below to reset your account password.</p>
            @endif

            <div class="otp-block">
                <div class="label">Your OTP Code</div>
                <div class="code">{{ $otpCode }}</div>
            </div>

            <p class="expiry">This code expires in <strong>10 minutes</strong>.</p>

            <hr class="divider">

            <p class="warning">If you didn't request this, you can safely ignore this email. Do not share this code with anyone.</p>

        </div>

        <div class="footer">
            &copy; {{ date('Y') }} RideGuide. All rights reserved.
        </div>

    </div>
</body>
</html>