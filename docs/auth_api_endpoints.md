# RideGuide Authentication API

## Overview

The RideGuide API uses Laravel Sanctum for authentication with Two-Factor Authentication (2FA) via Gmail OTP. Most endpoints are public, but some require a Bearer Token in the request header.

**Base URL**
```
https://rideguide.test/api/
```

---

## Authentication Flow

### New User (First Time)
```
Register → Receive Email OTP → Verify OTP (email_verification) → Auto-Logged In → Create Profile
```

### Returning User (Subsequent Logins)
```
Login → Receive 2FA OTP → Verify OTP (login_2fa) → Logged In
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

**POST** `https://rideguide.test/api/auth/register`

Creates a new user account and sends an email verification OTP to the provided email.

In Postman, go to the **Body** tab, select **form-data**, and fill in the following fields:

```
first_name             Aslainie
last_name              Maruhom
middle_name            Lampac
email                  aslainiemaruhom19@gmail.com
password               password123
password_confirmation  password123
role                   commuter
```

Note: The `middle_name` field is optional. The `role` field accepts only `admin`, `driver`, or `commuter`.

**Success Response (201)**
```json
{
    "success": true,
    "message": "Registration successful. Please check your email for the OTP to verify your account.",
    "data": {
        "user": {
            "id": "9f1a2b3c-...",
            "first_name": "Aslainie",
            "last_name": "Maruhom",
            "middle_name": "Lampac",
            "email": "aslainiemaruhom19@gmail.com",
            "role": "commuter"
        }
    }
}
```

---

### 2. Login

**POST** `https://rideguide.test/api/auth/login`

Validates your credentials and sends a 2FA OTP to your email. This does not return a token yet. You must complete the OTP verification step first.

If you are already logged in (have an active token), the request will be rejected. You must logout first. If a 2FA OTP has already been sent and has not expired, a new one will not be generated.

In Postman, go to the **Body** tab, select **form-data**, and fill in:

```
email       aslainiemaruhom19@gmail.com
password    password123
```

**Success Response (200)**
```json
{
    "success": true,
    "message": "Credentials verified. Please check your email for the 2FA OTP to complete login."
}
```

**Error Response — Already Logged In (409)**
```json
{
    "success": false,
    "message": "You are already logged in. Please logout first before logging in again."
}
```

**Error Response — Email Not Verified (403)**
```json
{
    "success": false,
    "message": "Your email is not verified. A new verification OTP has been sent to your email."
}
```

**Error Response — OTP Already Sent (429)**
```json
{
    "success": false,
    "message": "A 2FA OTP has already been sent to your email. Please check your email or wait for it to expire before requesting a new one."
}
```

---

### 3. Verify OTP

**POST** `https://rideguide.test/api/auth/verify-otp`

Verifies an OTP for either email verification or 2FA login. A Bearer token is returned in **both** cases — email verification auto-logs the user in, and 2FA login completes the login process.

In Postman, go to the **Body** tab, select **form-data**, and fill in:

**For Email Verification (after registration):**
```
email    aslainiemaruhom19@gmail.com
otp      123456
type     email_verification
```

**For 2FA Login (after login):**
```
email    aslainiemaruhom19@gmail.com
otp      654321
type     login_2fa
```

**Success Response for email_verification (200)**
```json
{
    "success": true,
    "message": "Email verified successfully. You are now logged in.",
    "data": {
        "user": {
            "id": "9f1a2b3c-...",
            "first_name": "Aslainie",
            "last_name": "Maruhom",
            "middle_name": "Lampac",
            "email": "aslainiemaruhom19@gmail.com",
            "role": "commuter",
            "email_verified_at": "2026-02-22T10:30:00.000000Z"
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
            "first_name": "Aslainie",
            "last_name": "Maruhom",
            "middle_name": "Lampac",
            "email": "aslainiemaruhom19@gmail.com",
            "role": "commuter",
            "email_verified_at": "2026-02-22T10:30:00.000000Z"
        },
        "token": "1|abc123xyz456...",
        "token_type": "Bearer"
    }
}
```

Copy the `token` value from this response. You will need it for all protected routes.

---

### 4. Forgot Password

**POST** `https://rideguide.test/api/auth/forgot-password`

Sends a password reset OTP to the provided email address. Limited to **3 requests per day**.

In Postman, go to the **Body** tab, select **form-data**, and fill in:

```
email    aslainiemaruhom19@gmail.com
```

**Success Response (200)**
```json
{
    "success": true,
    "message": "If an account with that email exists, a password reset OTP has been sent."
}
```

**Error Response — Daily Limit Reached (429)**
```json
{
    "success": false,
    "message": "You have reached the maximum of 3 password reset requests for today. Please try again tomorrow."
}
```

---

### 5. Reset Password

**POST** `https://rideguide.test/api/auth/reset-password`

Resets the account password using the OTP received from the Forgot Password step. Limited to **3 attempts per day**.

In Postman, go to the **Body** tab, select **form-data**, and fill in:

```
email                  aslainiemaruhom19@gmail.com
otp                    789012
password               newpassword123
password_confirmation  newpassword123
```

**Success Response (200)**
```json
{
    "success": true,
    "message": "Password reset successfully. Please login with your new password."
}
```

**Error Response — Daily Limit Reached (429)**
```json
{
    "success": false,
    "message": "You have reached the maximum of 3 password reset attempts for today. Please try again tomorrow."
}
```

---

### 6. Resend OTP

**POST** `https://rideguide.test/api/auth/resend-otp`

Resends an OTP to the user's email. Rate limited to once every **60 seconds** and a maximum of **3 requests per type per day**.

In Postman, go to the **Body** tab, select **form-data**, and fill in:

```
email    aslainiemaruhom19@gmail.com
type     email_verification
```

The type field accepts `email_verification` or `password_reset`.

**Success Response (200)**
```json
{
    "success": true,
    "message": "If the email exists, a new OTP has been sent."
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
    "message": "You have reached the maximum of 3 OTP resend requests for today. Please try again tomorrow."
}
```

---

## Protected Endpoints

These endpoints require a Bearer Token. In Postman, go to the **Authorization** tab, set the type to **Bearer Token**, and paste your token there.

---

### 7. Get Authenticated User

**GET** `https://rideguide.test/api/user`

Returns the profile of the currently logged-in user. No body is needed for this request.

**Success Response (200)**
```json
{
    "success": true,
    "data": {
        "user": {
            "id": "9f1a2b3c-...",
            "first_name": "Aslainie",
            "last_name": "Maruhom",
            "middle_name": "Lampac",
            "email": "aslainiemaruhom19@gmail.com",
            "role": "commuter",
            "email_verified_at": "2026-02-22T10:30:00.000000Z"
        }
    }
}
```

---

### 8. Logout

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

| Type | Used When |
|------|-----------|
| `email_verification` | After Register — verifies email and auto-logs in |
| `login_2fa` | After Login — completes 2FA and issues token |
| `password_reset` | After Forgot Password — allows password reset |

---

## Roles Reference

| Role | Description |
|------|-------------|
| `admin` | Administrator accounts |
| `driver` | Driver accounts |
| `commuter` | Commuter accounts |

---

## Rate Limits Summary

| Endpoint | Limit |
|----------|-------|
| Login | Blocked if already logged in; blocked if 2FA OTP still pending |
| Forgot Password | 3 requests per day |
| Reset Password | 3 attempts per day |
| Resend OTP | 60-second cooldown + 3 requests per type per day |