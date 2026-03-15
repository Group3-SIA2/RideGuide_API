# RideGuide Users & Commuter Profile API

## Overview

This document covers:

- **Users API**: list and view user accounts.
- **Commuter Profile API**: create/read/update/delete/restore commuter profiles with optional discount classification.

All endpoints are **protected** and require a valid Bearer Token obtained via login + 2FA OTP verification.

**Base URLs**

Users:
```
https://rideguide.test/api/users
```

Commuter profile:
```
https://rideguide.test/api/commuter
```

---

## Headers

Include these headers in **all** requests:

```
Accept: application/json
Content-Type: application/json
Authorization: Bearer {your_token_here}
```

> **Important:** The old `/api/users/*-profile` endpoints (birthdate, gender, profile image) are not part of the current routes.
>
> Admin note (users endpoints): `admin` and `super_admin` can list/view **non-admin** users, but cannot view another admin/super_admin account (except themselves).
>
> Commuter retention: Soft-deleted commuter profiles can only be restored within **30 days** of deletion.

---

## Endpoints Summary

| #  | Method   | Endpoint                              | Description                     | Access |
|----|----------|---------------------------------------|---------------------------------|--------|
| 1  | `GET`    | `/api/users`                          | List users                      | Admin/Super Admin (non-admin users) / Own only |
| 2  | `GET`    | `/api/users/{id}`                     | Get specific user               | Owner or Admin/Super Admin (with admin limits) |
| 3  | `POST`   | `/api/commuter/add-commuter`          | Create commuter profile         | Commuter only |
| 4  | `GET`    | `/api/commuter/read-commuter/{id}`    | Read commuter profile           | Owner (commuter) or Admin |
| 5  | `PUT`    | `/api/commuter/update-commuter/{id}`  | Update commuter classification  | Owner (commuter) or Admin |
| 6  | `DELETE` | `/api/commuter/delete-commuter/{id}`  | Soft-delete commuter profile    | Admin only |
| 7  | `PUT`    | `/api/commuter/restore-commuter/{id}` | Restore commuter profile        | Admin only (≤30 days) |

---

## 1. Get All Users

**GET** `https://rideguide.test/api/users`

Returns a list of users. `admin` and `super_admin` see all **driver and commuter** accounts (not other admin/super_admin accounts). Drivers and commuters only see their own account. This is useful for retrieving user IDs before calling profile endpoints.

Optional query parameter:
- `status` = `active`, `inactive`, or `suspended` (applies only to admin/super_admin requests)

No body is needed for this request.

**Example**
```
GET https://rideguide.test/api/users
```

**Example with status filter**
```
GET https://rideguide.test/api/users?status=active
```

**Success Response — Admin (200)**
```json
{
    "success": true,
    "data": [
        {
            "id": "019c7a57-980d-715f-b7b6-67014c23b601",
            "first_name": "Juan",
            "last_name": "Dela Cruz",
            "middle_name": "Santos",
            "email": "juan@example.com",
            "role": ["commuter"],
            "status": "active",
            "status_reason": null,
            "status_changed_at": "2026-03-15T10:30:00.000000Z",
            "email_verified_at": "2026-02-25T10:00:00.000000Z",
            "created_at": "2026-02-25T09:00:00.000000Z",
            "updated_at": "2026-02-25T09:00:00.000000Z"
        },
        {
            "id": "019c7a58-112a-7def-9a3c-abcdef123456",
            "first_name": "Maria",
            "last_name": "Garcia",
            "middle_name": null,
            "email": "maria@example.com",
            "role": ["driver"],
            "status": "active",
            "status_reason": null,
            "status_changed_at": "2026-03-15T10:30:00.000000Z",
            "email_verified_at": "2026-02-25T11:00:00.000000Z",
            "created_at": "2026-02-25T10:30:00.000000Z",
            "updated_at": "2026-02-25T10:30:00.000000Z"
        }
    ]
}
```

**Success Response — Driver/Commuter (200)**
```json
{
    "success": true,
    "data": [
        {
            "id": "019c7a57-980d-715f-b7b6-67014c23b601",
            "first_name": "Juan",
            "last_name": "Dela Cruz",
            "middle_name": "Santos",
            "email": "juan@example.com",
            "role": ["commuter"],
            "status": "active",
            "status_reason": null,
            "status_changed_at": "2026-03-15T10:30:00.000000Z",
            "email_verified_at": "2026-02-25T10:00:00.000000Z",
            "created_at": "2026-02-25T09:00:00.000000Z",
            "updated_at": "2026-02-25T09:00:00.000000Z"
        }
    ]
}
```

---

## 2. Get Specific User

**GET** `https://rideguide.test/api/users/{id}`

Returns a single user by ID.

- Drivers and commuters can only view themselves.
- `admin` and `super_admin` can view drivers/commuters, but **cannot** view another admin/super_admin account.

No body is needed for this request. Replace `{id}` with the user UUID.

**Example**
```
GET https://rideguide.test/api/users/019c7a57-980d-715f-b7b6-67014c23b601
```

**Success Response (200)**
```json
{
    "success": true,
    "data": {
        "id": "019c7a57-980d-715f-b7b6-67014c23b601",
        "first_name": "Juan",
        "last_name": "Dela Cruz",
        "middle_name": "Santos",
        "email": "juan@example.com",
        "role": ["commuter"],
        "status": "active",
        "status_reason": null,
        "status_changed_at": "2026-03-15T10:30:00.000000Z",
        "email_verified_at": "2026-02-25T10:00:00.000000Z",
        "created_at": "2026-02-25T09:00:00.000000Z",
        "updated_at": "2026-02-25T09:00:00.000000Z"
    }
}
```

**Error Response — Not Found (404)**
```json
{
    "success": false,
    "message": "User not found."
}
```

**Error Response — Unauthorized (403)**
```json
{
    "success": false,
    "message": "Unauthorized. You can only view your own account."
}
```

**Error Response — Admin Targeting Another Admin (403)**
```json
{
    "success": false,
    "message": "Unauthorized. You cannot view another admin account."
}
```

---

## 3. Create Commuter Profile

**POST** `https://rideguide.test/api/commuter/add-commuter`

Creates a new commuter profile for the authenticated user. Each commuter can only have **one** profile.

### Classification rules

- `classification_name` must be one of: `Regular`, `Student`, `Senior`, `PWD`
- If `classification_name` is **not** `Regular`, then `ID_number` and `ID_image` are required.

In Postman, go to the **Body** tab, select **form-data**, and fill in:

**Regular commuter**
```
classification_name   Regular
```

**Student / Senior / PWD commuter**
```
classification_name   Student
ID_number             2021 00456
ID_image              (required — select a file)
```

**Field Rules**

| Field | Type | Required | Rules |
|---|---|---|---|
| `classification_name` | string | Yes | Must be one of: `Regular`, `Student`, `Senior`, `PWD` |
| `ID_number` | string | Required unless `Regular` | Max 255, unique in `discounts.ID_number`, numbers and spaces only (regex: `^[0-9\s]+$`) |
| `ID_image` | file | Required unless `Regular` | Image, max 2MB |

**Success Response (201)**
```json
{
    "success": true,
    "message": "Commuter profile created successfully.",
    "data": {
        "id": "9f1a2b3c-...",
        "user_id": "019c7a57-980d-715f-b7b6-67014c23b601",
        "user": {
            "id": "019c7a57-980d-715f-b7b6-67014c23b601",
            "first_name": "Juan",
            "last_name": "Dela Cruz",
            "email": "juan@example.com"
        },
        "classification_name": "Student",
        "discount": {
            "id": "2b3c4d5e-...",
            "ID_number": "2021 00456",
            "ID_image_path": "discount_ids/abc123.jpg",
            "classification": "Student"
        },
        "created_at": "2026-02-25T10:00:00.000000Z",
        "updated_at": "2026-02-25T10:00:00.000000Z"
    }
}
```

**Error Response — Not a Commuter (403)**
```json
{
    "success": false,
    "message": "Unauthorized. Only users with the commuter role can create a commuter profile."
}
```

**Error Response — Profile Already Exists (400)**
```json
{
    "success": false,
    "message": "You already have a commuter profile. You can only have one."
}
```

**Error Response — Classification Type Not Found (404)**
```json
{
    "success": false,
    "message": "Classification type not found."
}
```

---

## 4. Read Commuter Profile

**GET** `https://rideguide.test/api/commuter/read-commuter/{id}`

Returns the commuter profile with the given ID.

- Admin can view any commuter profile.
- A commuter can only view **their own** commuter profile.

No body is needed for this request. Replace `{id}` with the commuter profile UUID.

**Example**
```
GET https://rideguide.test/api/commuter/read-commuter/019c93de-1d75-713e-a51f-75fe61efcd73
```

**Success Response (200)**
```json
{
    "success": true,
    "data": {
        "id": "019c93de-1d75-713e-a51f-75fe61efcd73",
        "user_id": "019c7a57-980d-715f-b7b6-67014c23b601",
        "user": {
            "id": "019c7a57-980d-715f-b7b6-67014c23b601",
            "first_name": "Juan",
            "last_name": "Dela Cruz",
            "email": "juan@example.com"
        },
        "classification_name": "Regular",
        "discount": null,
        "created_at": "2026-02-25T10:00:00.000000Z",
        "updated_at": "2026-02-25T10:00:00.000000Z"
    }
}
```

**Error Response — Not Found (404)**
```json
{
    "success": false,
    "message": "Commuter not found."
}
```

**Error Response — Unauthorized (403)**
```json
{
    "success": false,
    "message": "Unauthorized. You can only view your own profile."
}
```

---

## 5. Update Commuter Classification

**PUT** `https://rideguide.test/api/commuter/update-commuter/{id}`

Updates the commuter profile’s classification and/or discount ID details.

Access:

- Admin can update any commuter profile.
- A commuter can update **their own** profile.

In Postman, go to the **Body** tab, select **form-data**.

### Common update examples

**A) Change classification to Regular (removes discount)**
```
classification_name   Regular
```

**B) Change classification to Student (requires ID_number; ID_image optional but recommended)**
```
classification_name   Student
ID_number             2021 00456
ID_image              (optional — select a file)
```

**C) Update ID only (no classification change, only works if discount exists)**
```
ID_number             2021 00457
ID_image              (optional — select a file)
```

**Success Response (200)**
```json
{
    "success": true,
    "message": "Commuter profile updated successfully.",
    "data": {
        "id": "019c93de-1d75-713e-a51f-75fe61efcd73",
        "user_id": "019c7a57-980d-715f-b7b6-67014c23b601",
        "classification_name": "Student",
        "discount": {
            "id": "2b3c4d5e-...",
            "ID_number": "2021 00457",
            "ID_image_path": "discount_ids/def456.jpg",
            "classification": "Student"
        },
        "created_at": "2026-02-25T10:00:00.000000Z",
        "updated_at": "2026-02-25T12:30:00.000000Z"
    }
}
```

**Error Response — Unauthorized (403)**
```json
{
    "success": false,
    "message": "Unauthorized. Only Admin or the owning Commuter can update this profile."
}
```

**Error Response — Not Found (404)**
```json
{
    "success": false,
    "message": "Commuter not found."
}
```

**Error Response — Switching to Student/Senior/PWD Without ID (422)**
```json
{
    "success": false,
    "message": "ID number is required when switching to student, senior, or PWD classification."
}
```

---

## 6. Delete Commuter Profile (Soft Delete)

**DELETE** `https://rideguide.test/api/commuter/delete-commuter/{id}`

Soft-deletes a commuter profile. **Admin only.**

When a commuter profile is deleted, its discount record (if any) is also soft-deleted.

No body is needed for this request.

**Success Response (200)**
```json
{
    "success": true,
    "message": "Commuter profile deleted successfully."
}
```

**Error Response — Unauthorized (403)**
```json
{
    "success": false,
    "message": "Unauthorized. Only admins can delete commuter profiles."
}
```

**Error Response — Not Found (404)**
```json
{
    "success": false,
    "message": "Commuter not found."
}
```

---

## 7. Restore Commuter Profile

**PUT** `https://rideguide.test/api/commuter/restore-commuter/{id}`

Restores a soft-deleted commuter profile. **Admin only.**

Retention rule: if the commuter was deleted more than **30 days** ago, the restore request will be rejected.

When a commuter profile is restored, its discount record (if any) is also restored.

**Success Response (200)**
```json
{
    "success": true,
    "message": "Commuter profile restored successfully.",
    "data": {
        "id": "019c93de-1d75-713e-a51f-75fe61efcd73",
        "user_id": "019c7a57-980d-715f-b7b6-67014c23b601",
        "classification_name": "Regular",
        "discount": null,
        "created_at": "2026-02-25T10:00:00.000000Z",
        "updated_at": "2026-02-25T14:00:00.000000Z"
    }
}
```

**Error Response — Unauthorized (403)**
```json
{
    "success": false,
    "message": "Unauthorized. Only admins can restore commuter profiles."
}
```

**Error Response — Not Found (404)**
```json
{
    "success": false,
    "message": "Commuter profile not found or not deleted."
}
```

**Error Response — Outside Retention Window (403)**
```json
{
    "success": false,
    "message": "This profile was deleted more than 30 days ago and can no longer be restored for data-privacy compliance."
}
```

---

## Postman Testing Workflow

1. **Login** — `POST /api/auth/login`.
2. **Verify OTP** — `POST /api/auth/verify-otp` (type `login_2fa`) to get a Bearer Token.
3. **Set Token** — In Postman, set **Authorization** → **Bearer Token**.
4. **Users list** — `GET /api/users` (admin/super_admin sees all drivers/commuters; non-admin sees only self).
    Optional: `GET /api/users?status=active`.
5. **Users show** — `GET /api/users/{id}`.
6. **Create commuter profile** — `POST /api/commuter/add-commuter` (commuter role only).
7. **Read commuter profile** — `GET /api/commuter/read-commuter/{id}`.
8. **Update commuter profile** — `PUT /api/commuter/update-commuter/{id}`.
9. **Delete commuter profile** — `DELETE /api/commuter/delete-commuter/{id}` (admin only).
10. **Restore commuter profile** — `PUT /api/commuter/restore-commuter/{id}` (admin only).

---

## Common Error Responses

| Status | Meaning | Example |
|---:|---|---|
| 401 | Unauthenticated | Missing/invalid Bearer Token |
| 403 | Unauthorized | Wrong role / not the profile owner / outside retention window |
| 404 | Not Found | User or commuter profile not found |
| 400 | Bad Request | Profile already exists |
| 422 | Validation Error | Missing required fields, invalid classification, duplicate ID number |
