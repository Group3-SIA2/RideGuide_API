# RideGuide User Profile API

## Overview

The User Profile API allows authenticated users to manage their personal profiles (birthdate, gender, profile image). All endpoints are **protected** and require a valid Bearer Token obtained via login + 2FA OTP verification.

**Base URL**
```
https://rideguide.test/api/users
```

---

## Headers

Include these headers in **all** requests:

```
Accept: application/json
Content-Type: application/json
Authorization: Bearer {your_token_here}
```

> **Note:** Any authenticated user (commuter, driver, or admin) can create and manage **their own** profile. Admin users can read, update, delete, and restore **driver/commuter** profiles only — admins **cannot** operate on other admin profiles. Soft-deleted profiles can only be restored within **30 days** of deletion for data-privacy compliance.

---

## Endpoints Summary

| #  | Method   | Endpoint                              | Description              | Access                                |
|----|----------|---------------------------------------|--------------------------|---------------------------------------|
| 1  | `POST`   | `/api/users/create-profile`           | Create user profile      | Authenticated (own profile only)      |
| 2  | `GET`    | `/api/users/read-profile/{id}`        | Get user profile         | Owner or Admin (non-admin targets)    |
| 3  | `PUT`    | `/api/users/update-profile/{id}`      | Update user profile      | Owner or Admin (non-admin targets)    |
| 4  | `DELETE` | `/api/users/delete-profile/{id}`      | Soft-delete profile      | Admin only (non-admin targets)        |
| 5  | `PUT`    | `/api/users/restore-profile/{id}`     | Restore deleted profile  | Admin only (within 30-day window)     |

---

## 1. Create User Profile

**POST** `https://rideguide.test/api/users/create-profile`

Creates a new user profile for the authenticated user. Each user can only have **one** profile.

In Postman, go to the **Body** tab, select **form-data**, and fill in:

```
birthdate        1999-05-15
gender           male
profile_image    (optional — select a file)
```

**Field Rules**

| Field            | Type   | Required | Rules                                    |
|------------------|--------|----------|------------------------------------------|
| `birthdate`      | date   | Yes      | Must be a valid date (e.g. `YYYY-MM-DD`) |
| `gender`         | string | Yes      | Must be one of: `male`, `female`, `other` |
| `profile_image`  | file   | No       | Image file, max 2 MB                     |

**Success Response (201)**
```json
{
    "success": true,
    "message": "User profile created successfully.",
    "data": {
        "id": "9f1a2b3c-...",
        "user_id": "019c7a57-980d-715f-b7b6-67014c23b601",
        "birthdate": "1999-05-15",
        "gender": "male",
        "profile_image": null,
        "created_at": "2026-02-25T10:00:00.000000Z",
        "updated_at": "2026-02-25T10:00:00.000000Z"
    }
}
```

**Error Response — Unauthorized (403)**
```json
{
    "success": false,
    "message": "Unauthorized. You can only add your own profile."
}
```

**Error Response — Profile Already Exists (400)**
```json
{
    "success": false,
    "message": "You already have a profile. You can only have one profile."
}
```

**Error Response — Validation Error (422)**
```json
{
    "message": "The gender field is required.",
    "errors": {
        "gender": ["The gender field is required."]
    }
}
```

---

## 2. Read User Profile

**GET** `https://rideguide.test/api/users/read-profile/{id}`

Returns the user profile with the given ID. Only the profile owner or an admin can access this.

No body is needed for this request. Replace `{id}` with the user profile UUID.

**Example**
```
GET https://rideguide.test/api/users/read-profile/019c93de-1d75-713e-a51f-75fe61efcd73
```

**Success Response (200)**
```json
{
    "success": true,
    "data": {
        "id": "019c93de-1d75-713e-a51f-75fe61efcd73",
        "user_id": "019c7a57-980d-715f-b7b6-67014c23b601",
        "birthdate": "1999-05-15",
        "gender": "male",
        "profile_image": null,
        "created_at": "2026-02-25T10:00:00.000000Z",
        "updated_at": "2026-02-25T10:00:00.000000Z"
    }
}
```

**Error Response — Not Found (404)**
```json
{
    "success": false,
    "message": "User profile not found."
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

## 3. Update User Profile

**PUT** `https://rideguide.test/api/users/update-profile/{id}`

Updates an existing user profile. Only the profile owner or an admin can update it. All fields are optional (send only the ones you want to change).

In Postman, go to the **Body** tab, select **raw** → **JSON**, and provide the fields to update:

```json
{
    "birthdate": "2000-01-01",
    "gender": "female"
}
```

**Field Rules**

| Field            | Type   | Required | Rules                                    |
|------------------|--------|----------|------------------------------------------|
| `birthdate`      | date   | No       | Must be a valid date (e.g. `YYYY-MM-DD`) |
| `gender`         | string | No       | Must be one of: `male`, `female`, `other` |
| `profile_image`  | file   | No       | Image file, max 2 MB                     |

**Example**
```
PUT https://rideguide.test/api/users/update-profile/019c93de-1d75-713e-a51f-75fe61efcd73
```

**Success Response (200)**
```json
{
    "success": true,
    "message": "User profile updated successfully.",
    "data": {
        "id": "019c93de-1d75-713e-a51f-75fe61efcd73",
        "user_id": "019c7a57-980d-715f-b7b6-67014c23b601",
        "birthdate": "2000-01-01",
        "gender": "female",
        "profile_image": null,
        "created_at": "2026-02-25T10:00:00.000000Z",
        "updated_at": "2026-02-25T12:30:00.000000Z"
    }
}
```

**Error Response — Not Found (404)**
```json
{
    "success": false,
    "message": "User profile not found."
}
```

**Error Response — Unauthorized (403)**
```json
{
    "success": false,
    "message": "Unauthorized. You can only update your own profile."
}
```

---

## 4. Delete User Profile (Soft Delete)

**DELETE** `https://rideguide.test/api/users/delete-profile/{id}`

Soft-deletes a user profile. **Admin only.**

No body is needed for this request. Replace `{id}` with the user profile UUID.

**Example**
```
DELETE https://rideguide.test/api/users/delete-profile/019c93de-1d75-713e-a51f-75fe61efcd73
```

**Success Response (200)**
```json
{
    "success": true,
    "message": "User profile deleted successfully."
}
```

**Error Response — Not Found (404)**
```json
{
    "success": false,
    "message": "User profile not found."
}
```

**Error Response — Unauthorized (403)**
```json
{
    "success": false,
    "message": "Unauthorized. Only admins can delete user profiles."
}
```

---

## 5. Restore User Profile

**PUT** `https://rideguide.test/api/users/restore-profile/{id}`

Restores a soft-deleted user profile. **Admin only.**

No body is needed for this request. Replace `{id}` with the user profile UUID.

**Example**
```
PUT https://rideguide.test/api/users/restore-profile/019c93de-1d75-713e-a51f-75fe61efcd73
```

**Success Response (200)**
```json
{
    "success": true,
    "message": "User profile restored successfully.",
    "data": {
        "id": "019c93de-1d75-713e-a51f-75fe61efcd73",
        "user_id": "019c7a57-980d-715f-b7b6-67014c23b601",
        "birthdate": "1999-05-15",
        "gender": "male",
        "profile_image": null,
        "created_at": "2026-02-25T10:00:00.000000Z",
        "updated_at": "2026-02-25T14:00:00.000000Z"
    }
}
```

**Error Response — Not Found (404)**
```json
{
    "success": false,
    "message": "User profile not found or not deleted."
}
```

**Error Response — Unauthorized (403)**
```json
{
    "success": false,
    "message": "Unauthorized. Only admins can restore user profiles."
}
```

---

## Postman Testing Workflow

Follow these steps in order to test the user profile endpoints:

1. **Login** — `POST /api/auth/login` with your account credentials.
2. **Verify OTP** — `POST /api/auth/verify-otp` with type `login_2fa` to get your Bearer Token.
3. **Set Token** — In Postman, go to **Authorization** → **Bearer Token** and paste the token.
4. **Create Profile** — `POST /api/users/create-profile` with birthdate & gender.
5. **Read Profile** — `GET /api/users/read-profile/{id}` using the profile ID from step 4.
6. **Update Profile** — `PUT /api/users/update-profile/{id}` with fields to change.
7. **Delete Profile** — `DELETE /api/users/delete-profile/{id}` (requires admin token).
8. **Restore Profile** — `PUT /api/users/restore-profile/{id}` (requires admin token).

---

## Roles & Access Reference

| Role       | Create | Read                  | Update                | Delete                | Restore                      |
|------------|--------|-----------------------|-----------------------|-----------------------|------------------------------|
| `commuter` | ✅ Own | ✅ Own                | ✅ Own                | ❌                    | ❌                           |
| `driver`   | ✅ Own | ✅ Own                | ✅ Own                | ❌                    | ❌                           |
| `admin`    | ✅ Own | ✅ Own + Non-admin    | ✅ Own + Non-admin    | ✅ Non-admin only     | ✅ Non-admin (≤30 days)      |

> **Data-Privacy Note:** Soft-deleted profiles are only restorable within a **30-day retention window**. After 30 days, the restore endpoint will reject the request. It is recommended to schedule a periodic job (`php artisan schedule:run`) to permanently purge (`forceDelete`) profiles that exceed this window, ensuring compliance with data-minimization principles (e.g. GDPR, DPA 2012).

---

## Common Error Responses

| Status | Meaning             | Example                                          |
|--------|---------------------|--------------------------------------------------|
| 401    | Unauthenticated     | Missing or invalid Bearer Token                  |
| 403    | Unauthorized        | Wrong role or not the profile owner              |
| 404    | Not Found           | User profile with given ID does not exist        |
| 400    | Bad Request         | Profile already exists for this user             |
| 422    | Validation Error    | Missing required fields or invalid gender value  |
