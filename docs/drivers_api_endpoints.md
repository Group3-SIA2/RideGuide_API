# RideGuide Driver API

## Overview

The Driver API allows authenticated **driver** users to manage their driver profiles. All endpoints are **protected** and require a valid Bearer Token obtained via login + 2FA OTP verification.

**Base URL**
```
https://rideguide.test/api/drivers
```

---

## Headers

Include these headers in **all** requests:

```
Accept: application/json
Content-Type: application/json
Authorization: Bearer {your_token_here}
```

> **Note:** You must be logged in as a user with the `driver` role to create a profile. Admin users can read, update, delete, and restore any driver profile.

---

## Endpoints Summary

| #  | Method   | Endpoint                              | Description              | Access           |
|----|----------|---------------------------------------|--------------------------|------------------|
| 1  | `POST`   | `/api/drivers/create-profile`         | Create driver profile    | Driver only      |
| 2  | `GET`    | `/api/drivers/read-profile/{id}`      | Get driver profile       | Owner or Admin   |
| 3  | `PUT`    | `/api/drivers/update-profile/{id}`    | Update driver profile    | Owner or Admin   |
| 4  | `DELETE` | `/api/drivers/delete-profile/{id}`    | Soft-delete profile      | Admin only       |
| 5  | `PUT`    | `/api/drivers/restore-profile/{id}`   | Restore deleted profile  | Admin only       |

---

## 1. Create Driver Profile

**POST** `https://rideguide.test/api/drivers/create-profile`

Creates a new driver profile for the authenticated user. Each driver can only have **one** profile. The `verification_status` is automatically set to `unverified` — only an admin can change it later via the update endpoint.

In Postman, go to the **Body** tab, select **raw** → **JSON**, and fill in:

```json
{
    "license_number": "D01 00 000001",
    "franchise_number": "1984516156"
}
```

**Field Rules**

| Field              | Type   | Required | Rules                                                              |
|--------------------|--------|----------|--------------------------------------------------------------------|
| `license_number`   | string | Yes      | Max 255 chars, unique, alphanumeric and spaces only                |
| `franchise_number` | string | Yes      | Max 255 chars, unique, alphanumeric and spaces only                |

> **Note:** Both fields only accept letters, numbers, and spaces (regex: `^[A-Za-z0-9\s]+$`). The `verification_status` is **not** a user input — it defaults to `unverified`.

**Success Response (201)**
```json
{
    "message": "Driver profile created successfully",
    "driver_profile": {
        "id": "9f1a2b3c-...",
        "user_id": "019c7a57-980d-715f-b7b6-67014c23b601",
        "license_number": "D01 00 000001",
        "franchise_number": "1984516156",
        "verification_status": "unverified",
        "created_at": "2026-02-25T10:00:00.000000Z",
        "updated_at": "2026-02-25T10:00:00.000000Z"
    }
}
```

**Error Response — Not a Driver (403)**
```json
{
    "error": "Unauthorized."
}
```

**Error Response — Profile Already Exists (400)**
```json
{
    "error": "You already have a driver profile."
}
```

**Error Response — Validation Error (422)**
```json
{
    "message": "The license number has already been taken.",
    "errors": {
        "license_number": ["The license number has already been taken."]
    }
}
```

---

## 2. Read Driver Profile

**GET** `https://rideguide.test/api/drivers/read-profile/{id}`

Returns the driver profile with the given ID. Only the profile owner or an admin can access this.

No body is needed for this request. Replace `{id}` with the driver profile UUID.

**Example**
```
GET https://rideguide.test/api/drivers/read-profile/019c93de-1d75-713e-a51f-75fe61efcd73
```

**Success Response (200)**
```json
{
    "driver_profile": {
        "id": "019c93de-1d75-713e-a51f-75fe61efcd73",
        "user_id": "019c7a57-980d-715f-b7b6-67014c23b601",
        "license_number": "D01-00-000001",
        "franchise_number": "1984516156",
        "verification_status": "unverified",
        "created_at": "2026-02-25T10:00:00.000000Z",
        "updated_at": "2026-02-25T10:00:00.000000Z"
    }
}
```

**Error Response — Not Found (404)**
```json
{
    "error": "Driver profile not found"
}
```

**Error Response — Unauthorized (403)**
```json
{
    "error": "Unauthorized"
}
```

---

## 3. Update Driver Profile

**PUT** `https://rideguide.test/api/drivers/update-profile/{id}`

Updates an existing driver profile. **Drivers** can only update their own `franchise_number`. **Admins** can update all fields including `license_number`, `franchise_number`, and `verification_status`. All fields are optional (send only the ones you want to change).

### As Admin

In Postman, go to the **Body** tab, select **raw** → **JSON**, and provide the fields to update:

```json
{
    "license_number": "D01 00 000002",
    "franchise_number": "9876543210",
    "verification_status": "verified"
}
```

**Field Rules (Admin)**

| Field                 | Type   | Required | Rules                                                                                  |
|-----------------------|--------|----------|----------------------------------------------------------------------------------------|
| `license_number`      | string | No       | Max 255 chars, unique (ignores current profile), alphanumeric and spaces only           |
| `franchise_number`    | string | No       | Max 255 chars, unique (ignores current profile), alphanumeric and spaces only           |
| `verification_status` | string | No       | Must be one of: `verified`, `unverified`, `rejected`                                   |

### As Driver (Owner)

Drivers can **only** update `franchise_number`. Attempting to update `license_number` or `verification_status` will return a 403 error.

```json
{
    "franchise_number": "9876543210"
}
```

**Field Rules (Driver)**

| Field              | Type   | Required | Rules                                                     |
|--------------------|--------|----------|-----------------------------------------------------------|
| `franchise_number` | string | No       | Max 255 chars, unique (ignores current profile)            |

**Example**
```
PUT https://rideguide.test/api/drivers/update-profile/019c93de-1d75-713e-a51f-75fe61efcd73
```

**Success Response (200)**
```json
{
    "message": "Driver profile updated successfully",
    "driver_profile": {
        "id": "019c93de-1d75-713e-a51f-75fe61efcd73",
        "user_id": "019c7a57-980d-715f-b7b6-67014c23b601",
        "license_number": "D01 00 000002",
        "franchise_number": "9876543210",
        "verification_status": "verified",
        "created_at": "2026-02-25T10:00:00.000000Z",
        "updated_at": "2026-02-25T12:30:00.000000Z"
    }
}
```

**Error Response — Not Found (404)**
```json
{
    "error": "Driver profile not found"
}
```

**Error Response — Unauthorized (403)**
```json
{
    "error": "Unauthorized"
}
```

**Error Response — Driver Trying to Update Disallowed Fields (403)**
```json
{
    "error": "You can only update your franchise_number.",
    "disallowed_fields": ["license_number", "verification_status"]
}
```

---

## 4. Delete Driver Profile (Soft Delete)

**DELETE** `https://rideguide.test/api/drivers/delete-profile/{id}`

Soft-deletes a driver profile. **Admin only.**

No body is needed for this request. Replace `{id}` with the driver profile UUID.

**Example**
```
DELETE https://rideguide.test/api/drivers/delete-profile/019c93de-1d75-713e-a51f-75fe61efcd73
```

**Success Response (200)**
```json
{
    "message": "Driver profile deleted successfully"
}
```

**Error Response — Not Found (404)**
```json
{
    "error": "Driver profile not found"
}
```

**Error Response — Unauthorized (403)**
```json
{
    "error": "Unauthorized"
}
```

---

## 5. Restore Driver Profile

**PUT** `https://rideguide.test/api/drivers/restore-profile/{id}`

Restores a soft-deleted driver profile. **Admin only.**

No body is needed for this request. Replace `{id}` with the driver profile UUID.

**Example**
```
PUT https://rideguide.test/api/drivers/restore-profile/019c93de-1d75-713e-a51f-75fe61efcd73
```

**Success Response (200)**
```json
{
    "message": "Driver profile restored successfully",
    "driver_profile": {
        "id": "019c93de-1d75-713e-a51f-75fe61efcd73",
        "user_id": "019c7a57-980d-715f-b7b6-67014c23b601",
        "license_number": "D01-00-000001",
        "franchise_number": "1984516156",
        "verification_status": "unverified",
        "created_at": "2026-02-25T10:00:00.000000Z",
        "updated_at": "2026-02-25T14:00:00.000000Z"
    }
}
```

**Error Response — Not Found (404)**
```json
{
    "error": "Driver profile not found"
}
```

**Error Response — Unauthorized (403)**
```json
{
    "error": "Unauthorized"
}
```

---

## Postman Testing Workflow

Follow these steps in order to test the driver endpoints:

1. **Login** — `POST /api/auth/login` with your driver account credentials.
2. **Verify OTP** — `POST /api/auth/verify-otp` with type `login_2fa` to get your Bearer Token.
3. **Set Token** — In Postman, go to **Authorization** → **Bearer Token** and paste the token.
4. **Create Profile** — `POST /api/drivers/create-profile` with `license_number` & `franchise_number` (verification_status is auto-set to `unverified`).
5. **Read Profile** — `GET /api/drivers/read-profile/{id}` using the profile ID from step 4.
6. **Update Profile (as Driver)** — `PUT /api/drivers/update-profile/{id}` with `franchise_number` only.
7. **Update Profile (as Admin)** — `PUT /api/drivers/update-profile/{id}` with any fields including `verification_status`.
8. **Delete Profile** — `DELETE /api/drivers/delete-profile/{id}` (requires admin token).
9. **Restore Profile** — `PUT /api/drivers/restore-profile/{id}` (requires admin token).

---

## Roles & Access Reference

| Role       | Create | Read       | Update                        | Delete | Restore |
|------------|--------|------------|-------------------------------|--------|---------|
| `driver`   | ✅ Own | ✅ Own     | ✅ Own (`franchise_number` only) | ❌     | ❌      |
| `admin`    | ❌     | ✅ Any     | ✅ Any (all fields)            | ✅ Any | ✅ Any  |
| `commuter` | ❌     | ❌         | ❌                             | ❌     | ❌      |

---

## Common Error Responses

| Status | Meaning             | Example                                      |
|--------|---------------------|----------------------------------------------|
| 401    | Unauthenticated     | Missing or invalid Bearer Token              |
| 403    | Unauthorized        | Wrong role or not the profile owner           |
| 404    | Not Found           | Driver profile with given ID does not exist   |
| 400    | Bad Request         | Profile already exists for this user          |
| 422    | Validation Error    | Invalid or duplicate license/franchise number |