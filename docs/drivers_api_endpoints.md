# RideGuide Driver API Endpoints

## Overview

The Driver API manages the authenticated user's driver profile, license details, and license images.

These endpoints are under:

```
https://rideguide.test/api/drivers
```

All Driver API routes are protected by:
- `auth:sanctum`
- `active.user`

This means requests require a valid Sanctum Bearer token and the authenticated user must pass your active-user middleware checks.

---

## Authentication & Headers

### Required Header (all endpoints)

```
Accept: application/json
Authorization: Bearer {your_token_here}
```

### Content-Type

- For endpoints that upload files (`create-profile`, `update-profile` when sending images), use:

```
Content-Type: multipart/form-data
```

- For endpoints without file upload, JSON is fine:

```
Content-Type: application/json
```

---

## Access Rules (High-level)

- **Create profile**: `driver` role only.
- **Read profile**: owner of the profile or `admin`.
- **Update profile**: owner of the profile or `admin`.
- **Delete profile**: `admin` only.
- **Restore profile**: `admin` only.

> Notes:
> - A user can only have one driver profile (`driver.user_id` is unique).
> - Driver verification status is stored in the related license record (`license_id` table), not directly in `driver` table.

---

## Endpoints Summary

| # | Method | Endpoint | Description | Access |
|---|--------|----------|-------------|--------|
| 1 | `POST` | `/api/drivers/create-profile` | Create a driver profile with license ID and images | Driver only |
| 2 | `GET` | `/api/drivers/read-profile/{id}` | Get one driver profile | Owner or Admin |
| 3 | `PUT` | `/api/drivers/update-profile/{id}` | Update driver/license fields and/or images | Owner or Admin |
| 4 | `DELETE` | `/api/drivers/delete-profile/{id}` | Soft-delete driver profile (and related license/image) | Admin only |
| 5 | `PUT` | `/api/drivers/restore-profile/{id}` | Restore soft-deleted driver profile and related records | Admin only |

---

## Driver Profile Response Shape

Most success responses return this object under `driver_profile`:

```json
{
  "id": "uuid",
  "user_id": "uuid",
  "user": {
    "id": "uuid",
    "first_name": "string|null",
    "last_name": "string|null",
    "middle_name": "string|null",
    "email": "string|null"
  },
  "organization": {
    "id": "uuid",
    "name": "string",
    "type": "string"
  },
  "verification_status": "unverified|verified|rejected|null",
  "rejection_reason": "string|null",
  "driver_license": {
    "id": "uuid",
    "number": "string",
    "verification_status": "unverified|verified|rejected",
    "rejection_reason": "string|null",
    "images": {
      "front_path": "string|null",
      "front_url": "string|null",
      "back_path": "string|null",
      "back_url": "string|null"
    }
  },
  "emergency_contact": {
    "id": "uuid",
    "contact_name": "string",
    "contact_phone_number": "string",
    "contact_relationship": "string"
  },
  "created_at": "timestamp",
  "updated_at": "timestamp",
  "deleted_at": "timestamp|null"
}
```

Fields like `organization` and `emergency_contact` may be `null`.

---

## 1) Create Driver Profile

### Endpoint

**POST** `/api/drivers/create-profile`

### Purpose

Creates the authenticated driver's profile, creates a license image record, creates a license record with default status `unverified`, then links everything in the `driver` table.

### Request Type

`multipart/form-data`

### Request Fields

| Field | Type | Required | Rules |
|------|------|----------|------|
| `organization_id` | string (UUID) | No | Must exist in `organizations.id` |
| `license_id_number` | string | Yes | max 255, unique in `license_id.license_id`, regex `^[A-Za-z0-9\s-]+$` |
| `license_image_front` | file image | Yes | image, max 2048 KB |
| `license_image_back` | file image | No | image, max 2048 KB |

### Example (Postman form-data)

```
organization_id      019dxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
license_id_number    D01 00-000001
license_image_front  <file>
license_image_back   <file>   (optional)
```

### Success Response (201)

```json
{
  "message": "Driver profile created successfully",
  "driver_profile": {
    "id": "019...",
    "user_id": "019...",
    "user": {
      "id": "019...",
      "first_name": "Juan",
      "last_name": "Dela Cruz",
      "middle_name": null,
      "email": "juan@example.com"
    },
    "organization": null,
    "verification_status": "unverified",
    "rejection_reason": null,
    "driver_license": {
      "id": "019...",
      "number": "D01 00-000001",
      "verification_status": "unverified",
      "rejection_reason": null,
      "images": {
        "front_path": "driver_license_ids/xxx.jpg",
        "front_url": "https://rideguide.test/storage/driver_license_ids/xxx.jpg",
        "back_path": null,
        "back_url": null
      }
    },
    "emergency_contact": null,
    "created_at": "2026-03-26T10:00:00.000000Z",
    "updated_at": "2026-03-26T10:00:00.000000Z",
    "deleted_at": null
  }
}
```

### Common Errors

**401 Unauthenticated**
```json
{ "error": "Unauthenticated" }
```

**403 Not Driver Role**
```json
{ "error": "Unauthorized." }
```

**400 Profile Already Exists**
```json
{ "error": "You already have a driver profile." }
```

**422 Validation Error**
```json
{
  "message": "The license id number field is required.",
  "errors": {
    "license_id_number": ["The license id number field is required."]
  }
}
```

---

## 2) Read Driver Profile

### Endpoint

**GET** `/api/drivers/read-profile/{id}`

### Purpose

Returns a single driver profile by profile UUID.

### Access

- Profile owner
- Admin

### Success Response (200)

```json
{
  "driver_profile": {
    "id": "019c93de-1d75-713e-a51f-75fe61efcd73",
    "user_id": "019c7a57-980d-715f-b7b6-67014c23b601",
    "verification_status": "unverified",
    "driver_license": {
      "number": "D01 00-000001"
    }
  }
}
```

### Common Errors

**404 Not Found**
```json
{ "error": "Driver profile not found" }
```

**403 Unauthorized**
```json
{ "error": "Unauthorized" }
```

**401 Unauthenticated**
```json
{ "error": "Unauthenticated" }
```

---

## 3) Update Driver Profile

### Endpoint

**PUT** `/api/drivers/update-profile/{id}`

### Purpose

Updates the driver profile and/or related license data/images.

### Request Type

- If uploading images: `multipart/form-data`
- If no files: `application/json` is acceptable

---

### 3A) Admin Update Rules

Admins can update:
- `organization_id`
- `license_id_number`
- `license_image_front`
- `license_image_back`
- `license_verification_status` (`unverified`, `verified`, `rejected`)
- `license_rejection_reason`

#### Admin Field Rules

| Field | Type | Required | Rules |
|------|------|----------|------|
| `organization_id` | string (UUID) | No | Must exist in `organizations.id` |
| `license_id_number` | string | No | max 255, unique in `license_id.license_id` (current record ignored), regex `^[A-Za-z0-9\s-]+$` |
| `license_image_front` | file image | No | image, max 2048 KB |
| `license_image_back` | file image | No | image, max 2048 KB |
| `license_verification_status` | string | No | `unverified`, `verified`, `rejected` |
| `license_rejection_reason` | string | No | max 255, nullable |

> Behavior: if `license_verification_status` is set to `verified` or `unverified`, rejection reason is auto-cleared.

---

### 3B) Driver (Owner) Update Rules

Drivers (owners) can update:
- `license_id_number`
- `license_image_front`
- `license_image_back`

Drivers cannot update:
- `organization_id`
- `license_number` (legacy/disallowed key)
- `verification_status` (legacy/disallowed key)

If disallowed fields are sent, API returns 403.

#### Driver Field Rules

| Field | Type | Required | Rules |
|------|------|----------|------|
| `license_id_number` | string | No | max 255, unique in `license_id.license_id` (current record ignored), regex `^[A-Za-z0-9\s-]+$` |
| `license_image_front` | file image | No | image, max 2048 KB |
| `license_image_back` | file image | No | image, max 2048 KB |

---

### Example Request (Admin)

```
organization_id             019dxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
license_id_number           D01 00-000002
license_verification_status verified
license_rejection_reason    
license_image_front         <file>
```

### Example Request (Driver)

```
license_id_number  D01 00-000003
license_image_back <file>
```

### Success Response (200)

```json
{
  "message": "Driver profile updated successfully",
  "driver_profile": {
    "id": "019...",
    "verification_status": "verified",
    "rejection_reason": null,
    "driver_license": {
      "number": "D01 00-000002",
      "verification_status": "verified",
      "rejection_reason": null
    }
  }
}
```

### Common Errors

**404 Not Found**
```json
{ "error": "Driver profile not found" }
```

**403 Unauthorized (not owner/admin)**
```json
{ "error": "Unauthorized" }
```

**403 Disallowed fields (driver)**
```json
{
  "error": "You can only update your license ID images.",
  "disallowed_fields": ["organization_id"]
}
```

**422 Validation Error**
```json
{
  "message": "The license id number has already been taken.",
  "errors": {
    "license_id_number": ["The license id number has already been taken."]
  }
}
```

---

## 4) Delete Driver Profile

### Endpoint

**DELETE** `/api/drivers/delete-profile/{id}`

### Purpose

Soft-deletes the driver profile.

Implementation also soft-deletes related records when present:
- `license_id`
- `license_image`

### Access

- Admin only

### Success Response (200)

```json
{
  "message": "Driver profile deleted successfully",
  "driver_profile": {
    "id": "019...",
    "deleted_at": null
  }
}
```

### Common Errors

**404 Not Found**
```json
{ "error": "Driver profile not found" }
```

**403 Unauthorized**
```json
{ "error": "Unauthorized" }
```

---

## 5) Restore Driver Profile

### Endpoint

**PUT** `/api/drivers/restore-profile/{id}`

### Purpose

Restores a soft-deleted driver profile and restores related soft-deleted records when available:
- `license_id`
- `license_image`

### Access

- Admin only

### Success Response (200)

```json
{
  "message": "Driver profile restored successfully",
  "driver_profile": {
    "id": "019...",
    "deleted_at": null
  }
}
```

### Common Errors

**404 Not Found**
```json
{ "error": "Driver profile not found" }
```

**401 Unauthenticated**
```json
{ "error": "Unauthenticated" }
```

**403 Unauthorized**
```json
{ "error": "Unauthorized" }
```

---

## Suggested Postman Testing Flow

1. Authenticate (email/phone auth flow), get Bearer token.
2. Set Authorization to Bearer Token.
3. As a driver account, call `POST /api/drivers/create-profile` using form-data with image upload.
4. Call `GET /api/drivers/read-profile/{id}` and confirm nested `driver_license.images.front_url` is returned.
5. As driver owner, call `PUT /api/drivers/update-profile/{id}` with `license_id_number` and/or images.
6. As admin account, call `PUT /api/drivers/update-profile/{id}` with `license_verification_status` and optional rejection reason.
7. As admin account, call delete then restore endpoints.

---

## Role & Access Matrix

| Role | Create | Read | Update | Delete | Restore |
|------|--------|------|--------|--------|---------|
| `driver` | ✅ Own | ✅ Own | ✅ Own (license_id_number + images) | ❌ | ❌ |
| `admin` | ❌ | ✅ Any | ✅ Any (including verification status) | ✅ Any | ✅ Any |
| `commuter` | ❌ | ❌ | ❌ | ❌ | ❌ |

---

## Common HTTP Status Codes

| Status | Meaning | Typical Cause |
|--------|---------|---------------|
| 200 | Success | Read/update/delete/restore successful |
| 201 | Created | Driver profile created |
| 400 | Bad Request | Driver already has profile |
| 401 | Unauthenticated | Missing/invalid token |
| 403 | Unauthorized | Wrong role, not owner, or disallowed fields |
| 404 | Not Found | Driver profile ID does not exist |
| 422 | Validation Error | Invalid field format, duplicate license ID, invalid image |

---

## Implementation Notes (Important)

- Current disallowed-field guard for non-admin update checks keys: `license_number`, `verification_status`, `organization_id`.
- Canonical update keys used by API are: `license_id_number` and `license_verification_status`.
- For consistency in client apps, prefer sending only the canonical keys documented above.
