# RideGuide Phone Authentication API

## Overview

The RideGuide Phone Authentication API allows users to register and login using Philippine mobile numbers via the iProgSMS service. The flow is similar to email authentication but uses SMS OTP instead.

**Base URL**
```
https://rideguide.test/api/
```

---

## Phone Number Format

All phone numbers are accepted in the following Philippine formats and automatically normalized to E.164 format (`+639XXXXXXXXX`) for storage:

- **Local format**: `09XXXXXXXXX` (e.g., `09123456789`)
- **Without country code**: `639XXXXXXXXX` (e.g., `639123456789`)
- **International format**: `+639XXXXXXXXX` (e.g., `+639123456789`)

---

## Password Requirements

All password fields must meet the following criteria:
- **Minimum 8 characters**
- **Uppercase letters** (at least one A-Z)
- **Lowercase letters** (at least one a-z)
- **Special characters** (at least one symbol: @, #, $, !, %, ^, &, *, etc.)

**Valid Examples:**
- `Password@123`
- `Marjovic@123`
- `SecureP@ss1`
- `Test!Login99`

---

## Authentication Flow

### New User (First Time)
```
Register → Receive SMS OTP → Verify OTP (phone_verification) → Auto-Logged In
```

### Returning User (Subsequent Logins)
```
Login → Receive SMS 2FA OTP → Verify OTP (login_2fa) → Logged In
```

---

## Headers

For all requests, include these headers in Postman:
```
Accept: application/json
Content-Type: application/json
```

For protected routes only, also add:
```
Authorization: Bearer {your_token_here}
```

---

## Public Endpoints

These endpoints do not require a token.

---

### 1. Register

**POST** `https://rideguide.test/api/auth/phone/register`

Creates a new user account using a Philippine mobile number and sends a phone verification OTP via SMS.

In Postman, go to the **Body** tab, select **form-data**, and fill in the following fields:

```
phone_number    09123456789
password        Password@123
```

**Accepted phone formats:**
- `09XXXXXXXXX`
- `639XXXXXXXXX`
- `+639XXXXXXXXX`

**Password Requirements:**
- Minimum 8 characters
- Must contain uppercase, lowercase, and special characters
- Example: `Password@123`

**Success Response (201)**
```json
{
    "success": true,
    "message": "Registration successful. An OTP has been sent to your phone number.",
    "data": {
        "user": {
            "id": "9f1a2b3c-...",
            "phone_number": "+639123456789"
        }
    }
}
```

**Error Response — Phone Already Registered (409)**
```json
{
    "success": false,
    "message": "A user with this phone number is already registered."
}
```

**Error Response — SMS Delivery Failed (503)**
```json
{
    "success": false,
    "message": "We could not send the verification OTP. Please try again."
}
```

**Error Response — Validation Error (422)**
```json
{
    "message": "The phone number field format is invalid.",
    "errors": {
        "phone_number": [
            "The phone number field format is invalid."
        ],
        "password": [
            "The password must be at least 8 characters.",
            "The password must contain at least one uppercase character."
        ]
    }
}
```

---

### 2. Login

**POST** `https://rideguide.test/api/auth/phone/login`

Validates credentials and sends a 2FA OTP to the registered phone number. This does not return a token yet. You must complete the OTP verification step first.

If you are already logged in (have an active token), the request will be rejected. You must logout first. If a 2FA OTP has already been sent and has not expired, a new one will not be generated.

In Postman, go to the **Body** tab, select **form-data**, and fill in:

```
phone_number    09123456789
password        Password@123
```

**Success Response (200)**
```json
{
    "success": true,
    "message": "Credentials verified. An OTP has been sent to your phone to complete login."
}
```

**Error Response — Invalid Credentials (401)**
```json
{
    "success": false,
    "message": "The provided credentials are incorrect."
}
```

**Error Response — Phone Not Verified (403)**
```json
{
    "success": false,
    "message": "Your phone number is not verified. A new verification OTP has been sent."
}
```

**Error Response — Already Logged In (409)**
```json
{
    "success": false,
    "message": "You are already logged in. Please logout first before logging in again."
}
```

**Error Response — 2FA OTP Pending (429)**
```json
{
    "success": false,
    "message": "A 2FA OTP has already been sent. Please check your phone or wait for it to expire before requesting a new one."
}
```

**Error Response — SMS Delivery Failed (503)**
```json
{
    "success": false,
    "message": "We could not send the 2FA OTP. Please try again."
}
```

**Error Response — Inactive/Suspended Account (403)**
```json
{
    "success": false,
    "message": "Your account is not active."
}
```

---

### 3. Verify OTP

**POST** `https://rideguide.test/api/auth/phone/verify-otp`

Verifies an OTP received via SMS for either phone verification or 2FA login. A Bearer token is returned in **both** cases — phone verification auto-logs the user in, and 2FA login completes the login process.

The OTP code is verified directly with iProgSMS (the SMS service provider).

In Postman, go to the **Body** tab, select **form-data**, and fill in:

**For Phone Verification (after registration):**
```
phone_number    09123456789
otp             123456
type            phone_verification
```

**For 2FA Login (after login):**
```
phone_number    09123456789
otp             654321
type            login_2fa
```

**Success Response for phone_verification (200)**
```json
{
    "success": true,
    "message": "Phone number verified successfully. You are now logged in.",
    "data": {
        "user": {
            "id": "9f1a2b3c-...",
            "phone_number": "+639123456789",
            "phone_verified_at": "2026-03-15T10:30:00.000000Z"
        },
        "token": "1|abc123xyz456...",
        "token_type": "Bearer"
    }
}
```

**Success Response for login_2fa (200)**
```json
{
    "success": true,
    "message": "Login successful.",
    "data": {
        "user": {
            "id": "9f1a2b3c-...",
            "phone_number": "+639123456789",
            "phone_verified_at": "2026-03-15T10:30:00.000000Z"
        },
        "token": "1|abc123xyz456...",
        "token_type": "Bearer"
    }
}
```

Copy the `token` value from this response. You will need it for all protected routes.

**Error Response — No Active OTP (422)**
```json
{
    "success": false,
    "message": "No active OTP found. Please request a new OTP."
}
```

**Error Response — Invalid/Expired OTP (422)**
```json
{
    "success": false,
    "message": "Invalid or expired OTP."
}
```

**Error Response — Inactive/Suspended Account (403)**
```json
{
    "success": false,
    "message": "Your account is not active."
}
```

---

### 4. Resend OTP

**POST** `https://rideguide.test/api/auth/phone/resend-otp`

Resends a phone verification OTP to the user's phone. Rate limited to once every **60 seconds** and a maximum of **3 requests per day**.

In Postman, go to the **Body** tab, select **form-data**, and fill in:

```
phone_number    09123456789
type            phone_verification
```

The `type` field currently accepts only `phone_verification`.

**Success Response (200)**
```json
{
    "success": true,
    "message": "If the phone number exists, a new OTP has been sent."
}
```

**Error Response — Too Soon (429)**
```json
{
    "success": false,
    "message": "Please wait 60 seconds before requesting a new OTP."
}
```

**Error Response — Daily Limit Reached (429)**
```json
{
    "success": false,
    "message": "You have reached the maximum of 3 OTP requests for today. Please try again tomorrow."
}
```

---

### 5. Forgot Password

**POST** `https://rideguide.test/api/auth/phone/forgot-password`

Sends a password reset OTP to the registered phone number via SMS. Limited to **3 requests per day**.

In Postman, go to the **Body** tab, select **form-data**, and fill in:

```
phone_number    09123456789
```

**Success Response (200)**
```json
{
    "success": true,
    "message": "If an account with that phone number exists, a password reset OTP has been sent."
}
```

**Error Response — Daily Limit Reached (429)**
```json
{
    "success": false,
    "message": "You have reached the maximum of 3 password reset requests for today. Please try again tomorrow."
}
```

**Error Response — SMS Delivery Failed (503)**
```json
{
    "success": false,
    "message": "We could not send the password reset OTP. Please try again."
}
```

---

### 6. Reset Password

**POST** `https://rideguide.test/api/auth/phone/reset-password`

Resets the account password using the OTP received from the Forgot Password step. Limited to **3 attempts per day**. OTP is verified directly with iProgSMS.

In Postman, go to the **Body** tab, select **form-data**, and fill in:

```
phone_number           09123456789
otp                    789012
password               NewPass@456
password_confirmation  NewPass@456
```

**Password Requirements:**
- Minimum 8 characters
- Must contain uppercase, lowercase, and special characters
- Example: `NewPass@456`

**Success Response (200)**
```json
{
    "success": true,
    "message": "Password reset successfully. Please login with your new password."
}
```

**Error Response — Invalid/Expired OTP (422)**
```json
{
    "success": false,
    "message": "Invalid or expired OTP."
}
```

**Error Response — Daily Limit Reached (429)**
```json
{
    "success": false,
    "message": "You have reached the maximum of 3 password reset attempts for today. Please try again tomorrow."
}
```

**Error Response — Validation Error (422)**
```json
{
    "message": "The password must be at least 8 characters.",
    "errors": {
        "password": [
            "The password must be at least 8 characters.",
            "The password must contain at least one uppercase character.",
            "The password must contain at least one symbol."
        ]
    }
}
```

---

## Protected Endpoints

These endpoints require a Bearer Token. In Postman, go to the **Authorization** tab, set the type to **Bearer Token**, and paste your token there.

---

### 7. Logout

**POST** `https://rideguide.test/api/auth/logout`

Revokes the current Bearer Token, effectively logging the user out. No body is needed for this request.

**Success Response (200)**
```json
{
    "success": true,
    "message": "Logged out successfully."
}
```

---

## OTP Types Reference

| Type | Used When | Delivery Method |
|------|-----------|-----------------|
| `phone_verification` | After Register — verifies phone and auto-logs in | SMS via iProgSMS |
| `login_2fa` | After Login — completes 2FA and issues token | SMS via iProgSMS |
| `password_reset` | After Forgot Password — allows password reset | SMS via iProgSMS |

---

## Rate Limits Summary

| Endpoint | Limit |
|----------|-------|
| Login | Blocked if already logged in; blocked if 2FA OTP still pending |
| Resend OTP | 60-second cooldown + 3 requests per day |
| Forgot Password | 3 requests per day |
| Reset Password | 3 attempts per day |

---

## OTP Expiration

All SMS OTPs expire after **5 minutes** (mirroring iProgSMS default OTP lifetime).

---

## Comparison: Email vs Phone Authentication

| Feature | Email Auth | Phone Auth |
|---------|-----------|-----------|
| Login Method | Email + Password | Phone + Password |
| OTP Delivery | Gmail SMTP | iProgSMS SMS |
| OTP Expiration | 10 minutes | 5 minutes |
| Registration Token | Auto-issued after email verification | Auto-issued after phone verification |
| Setup Endpoint | POST /api/setup/setup-users | Same setup endpoint (shared) |
| Logout | Same endpoint for both | Same endpoint for both |

Users can have both email and phone authentication methods enabled for maximum flexibility.
