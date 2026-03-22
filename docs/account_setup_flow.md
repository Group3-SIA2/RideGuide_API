# RideGuide — New User Account Setup Flow

## Overview

Creating a new user account in RideGuide is a **three-step process**:

1. **Register** — Create credentials (email or phone number + password).
2. **Verify OTP** — Confirm ownership of the email or phone number.
3. **Set Up Profile** — Provide your name and choose your role(s).

After step 2, you receive a Bearer Token and are technically logged in, but your account is incomplete until step 3 is finished. The setup step is required before accessing any role-specific features.

---

## Registration Methods

RideGuide supports two ways to register:

| Method | Description |
|--------|-------------|
| **Email** | Register with an email address; OTP is delivered via Gmail |
| **Phone** | Register with a Philippine mobile number; OTP is delivered via SMS (iProgSMS) |

---

## Method A — Email Registration

### Step 1 — Register

**POST** `https://rideguide.test/api/auth/register`

In Postman, go to the **Body** tab, select **form-data**, and fill in:

```
email     aslainiemaruhom19@gmail.com
password  password123
```

**Success Response (201)**
```json
{
    "success": true,
    "message": "Registration successful. Please check your email for the OTP to verify your account.",
    "data": {
        "user": {
            "id": "9f1a2b3c-...",
            "email": "aslainiemaruhom19@gmail.com"
        }
    }
}
```

**Error — Email Already Registered (422)**
```json
{
    "success": false,
    "message": "The email has already been taken."
}
```

---

### Step 2 — Verify Email OTP

**POST** `https://rideguide.test/api/auth/verify-otp`

Check your inbox for a 6-digit OTP. In Postman, go to the **Body** tab, select **form-data**, and fill in:

```
email  aslainiemaruhom19@gmail.com
otp    123456
type   email_verification
```

**Success Response (200)**
```json
{
    "success": true,
    "message": "Email verified successfully. You are now logged in.",
    "data": {
        "user": {
            "id": "9f1a2b3c-...",
            "email": "aslainiemaruhom19@gmail.com",
            "email_verified_at": "2026-03-08T10:30:00.000000Z"
        },
        "token": "1|abc123xyz456...",
        "token_type": "Bearer"
    }
}
```

> Copy the `token` value. You will need it for Step 3.

**Error — Invalid or Expired OTP (422)**
```json
{
    "success": false,
    "message": "Invalid or expired OTP."
}
```

---

### Resend Email OTP (if needed)

**POST** `https://rideguide.test/api/auth/resend-otp`

Rate limited: once every **60 seconds**, max **3 per day**.

```
email  aslainiemaruhom19@gmail.com
type   email_verification
```

---

## Method B — Phone Number Registration

Accepts Philippine mobile numbers in any of these formats:
- `09XXXXXXXXX` (local)
- `+639XXXXXXXXX` (E.164)
- `639XXXXXXXXX` (without +)

### Step 1 — Register

**POST** `https://rideguide.test/api/auth/phone/register`

In Postman, go to the **Body** tab, select **form-data**, and fill in:

```
phone_number  09171234567
password      password123
```

**Success Response (201)**
```json
{
    "success": true,
    "message": "Registration successful. An OTP has been sent to your phone number.",
    "data": {
        "user": {
            "id": "9f1a2b3c-...",
            "phone_number": "+639171234567"
        }
    }
}
```

> The phone number is stored internally in E.164 format (`+639XXXXXXXXX`).

**Error — Phone Already Registered (409)**
```json
{
    "success": false,
    "message": "A user with this phone number is already registered."
}
```

**Error — SMS Could Not Be Sent (503)**
```json
{
    "success": false,
    "message": "We could not send the verification OTP. Please try again."
}
```

---

### Step 2 — Verify Phone OTP

**POST** `https://rideguide.test/api/auth/phone/verify-otp`

Check your SMS for the 6-digit OTP sent by iProgSMS. In Postman, go to the **Body** tab, select **form-data**, and fill in:

```
phone_number  09171234567
otp           654321
type          phone_verification
```

**Success Response (200)**
```json
{
    "success": true,
    "message": "Phone number verified successfully. You are now logged in.",
    "data": {
        "user": {
            "id": "9f1a2b3c-...",
            "phone_number": "+639171234567",
            "phone_verified_at": "2026-03-08T10:30:00.000000Z"
        },
        "token": "1|abc123xyz456...",
        "token_type": "Bearer"
    }
}
```

> Copy the `token` value. You will need it for Step 3.

**Error — Invalid or Expired OTP (422)**
```json
{
    "success": false,
    "message": "Invalid or expired OTP."
}
```

**Error — No Active OTP Found (422)**
```json
{
    "success": false,
    "message": "No active OTP found. Please request a new OTP."
}
```

---

### Resend Phone OTP (if needed)

**POST** `https://rideguide.test/api/auth/phone/resend-otp`

Rate limited: once every **60 seconds**, max **3 per day**.

```
phone_number  09171234567
type          phone_verification
```

**Success Response (200)**
```json
{
    "success": true,
    "message": "If the phone number exists, a new OTP has been sent."
}
```

---

## Step 3 — Set Up Your Profile (Required)

This step applies to **both** email and phone registrations. It is a **protected endpoint** — include your Bearer token.

**POST** `https://rideguide.test/api/setup/setup-users`

In Postman, go to the **Authorization** tab, set type to **Bearer Token**, and paste your token. Then go to the **Body** tab, select **form-data**, and fill in:

```
first_name    Aslainie
last_name     Maruhom
middle_name   Lampac
roles[]       commuter
```

> `middle_name` is optional.
>
> `roles[]` accepts `driver`, `commuter`, and/or `organization` (a user can hold multiple roles simultaneously).

**To assign multiple roles, add each value on its own line:**
```
roles[]  driver
roles[]  commuter
roles[]  organization
```

**Success Response (200)**
```json
{
    "success": true,
    "message": "You're All Set Up.",
    "data": {
        "id": "9f1a2b3c-...",
        "first_name": "Aslainie",
        "last_name": "Maruhom",
        "middle_name": "Lampac",
        "email": "aslainiemaruhom19@gmail.com",
        "roles": ["commuter"]
    }
}
```

**Example response with all roles:**
```json
{
    "success": true,
    "message": "You're All Set Up.",
    "data": {
        "id": "9f1a2b3c-...",
        "first_name": "Aslainie",
        "last_name": "Maruhom",
        "middle_name": null,
        "email": "aslainiemaruhom19@gmail.com",
        "roles": ["driver", "commuter", "organization"]
    }
}
```

**Error — Already Set Up (400)**
```json
{
    "success": false,
    "message": "Your profile is already set up."
}
```

**Error — Invalid Role (422)**
```json
{
    "success": false,
    "message": "The selected roles.0 is invalid."
}
```

---

## Complete Flow Summary

### Email Registration
```
POST /api/auth/register
  → POST /api/auth/verify-otp        (type: email_verification)  → Bearer Token
    → POST /api/setup/setup-users    (Protected — Bearer Token required)
```

### Phone Registration
```
POST /api/auth/phone/register
  → POST /api/auth/phone/verify-otp  (type: phone_verification)  → Bearer Token
    → POST /api/setup/setup-users    (Protected — Bearer Token required)
```

---

## Roles Reference

| Role | Description |
|------|-------------|
| `driver` | Can create a driver profile and access driver features |
| `commuter` | Can create a commuter profile and access commuter features |
| `organization` | Can create and manage a single organization profile |

> A single user may hold any combination of the supported roles.

---

## OTP Behaviour Reference

| Event | Delivery | Expiry | Rate Limit |
|-------|----------|--------|------------|
| Email registration OTP | Gmail (SMTP) | 10 minutes | 60 s cooldown + 3/day |
| Phone registration OTP | SMS via iProgSMS | 5 minutes | 60 s cooldown + 3/day |

---

## Headers Reference

**Public endpoints (Steps 1 & 2):**
```
Accept: application/json
Content-Type: application/json
```

**Protected endpoint (Step 3):**
```
Accept: application/json
Content-Type: application/json
Authorization: Bearer {your_token_here}
```
