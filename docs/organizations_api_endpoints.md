# RideGuide Organizations API

## Overview

The Organizations API allows users to browse and manage driver organizations — **TODA** (Tricycle Operators and Drivers Association) and **MODA** (Motorcycle Operators and Drivers Association) — operating in General Santos City. Read operations are open to all authenticated users. Write operations follow role-based access control:

- `admin` / `super_admin` — full access to all organizations
- `organization` — can create **one** organization (auto-assigned as owner), update/delete **their own** organization only; cannot change `status`
- All other roles — read-only access

**Base URL**
```
https://rideguide.test/api/organizations
```

---

## Headers

Include these headers in **all** requests:

```
Accept: application/json
Content-Type: application/json
Authorization: Bearer {your_token_here}
```

> **Note:** A valid Bearer Token is required for all endpoints. Tokens are obtained after completing the login + 2FA OTP flow.

---

## Endpoints Summary

| #  | Method   | Endpoint                            | Description                       | Access                                    |
|----|----------|-------------------------------------|-----------------------------------|-------------------------------------------|
| 1  | `GET`    | `/api/organizations`                | List active organizations         | Any authenticated user                    |
| 2  | `GET`    | `/api/organizations/{id}`           | Get a single organization         | Any authenticated user                    |
| 3  | `POST`   | `/api/organizations`                | Create a new organization         | `admin`, `super_admin`, `organization`    |
| 4  | `PUT`    | `/api/organizations/{id}`           | Update an organization            | `admin`, `super_admin` (any); `organization` (own only) |
| 5  | `DELETE` | `/api/organizations/{id}`           | Soft-delete an organization       | `admin`, `super_admin` (any); `organization` (own only) |
| 6  | `PUT`    | `/api/organizations/{id}/restore`   | Restore a soft-deleted org        | `admin`, `super_admin` only               |

---

## 1. List Organizations

**GET** `https://rideguide.test/api/organizations`

Returns **active** organizations in paginated form, ordered by name. Supports optional search and type filtering.

**Query Parameters (all optional)**

| Parameter  | Type    | Default | Description                                           |
|------------|---------|---------|-------------------------------------------------------|
| `search`   | string  | —       | Search by organization name, type, or address         |
| `type`     | string  | —       | Filter by type — `TODA` or `MODA`                     |
| `per_page` | integer | `20`    | Number of results per page. Maximum: `100`            |

**Example Request (no filters)**

```
GET https://rideguide.test/api/organizations
```

**Example Request (with filters and pagination)**

```
GET https://rideguide.test/api/organizations?type=TODA&search=lagao&per_page=10
```

**Success Response (200)**
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": "019f2a3b-1c2d-7e4f-a5b6-c7d8e9f01234",
                "name": "TODA - Lagao Terminal",
                "type": "TODA",
                "description": "Primary short-distance transport association operating motorized tricycles.",
                "address": "Lagao, General Santos City",
                "contact_number": null,
                "status": "active",
                "owner_user_id": null,
                "created_at": "2026-03-07T11:25:05.000000Z",
                "updated_at": "2026-03-07T11:25:05.000000Z"
            }
        ],
        "from": 1,
        "last_page": 2,
        "per_page": 20,
        "to": 20,
        "total": 25,
        "links": {
            "first": "https://rideguide.test/api/organizations?page=1",
            "last": "https://rideguide.test/api/organizations?page=2",
            "prev": null,
            "next": "https://rideguide.test/api/organizations?page=2"
        }
    }
}
```

---

## 2. Show Organization

**GET** `https://rideguide.test/api/organizations/{id}`

Returns a single organization by its UUID, including a count of how many drivers are linked to it.

> **Access note:** `admin` / `super_admin` users can retrieve any organization regardless of status. All other roles only see organizations with `status: active`. An inactive organization returns `404` for non-admin callers.

**Example Request**

```
GET https://rideguide.test/api/organizations/019f2a3b-1c2d-7e4f-a5b6-c7d8e9f01234
```

**Success Response (200)**
```json
{
    "success": true,
    "data": {
        "id": "019f2a3b-1c2d-7e4f-a5b6-c7d8e9f01234",
        "name": "TODA - Lagao Terminal",
        "type": "TODA",
        "description": "Primary short-distance transport association operating motorized tricycles.",
        "address": "Lagao, General Santos City",
        "contact_number": null,
        "status": "active",
        "owner_user_id": null,
        "drivers_count": 5,
        "created_at": "2026-03-07T11:25:05.000000Z",
        "updated_at": "2026-03-07T11:25:05.000000Z"
    }
}
```

**Error Response — Not Found (404)**
```json
{
    "success": false,
    "message": "Organization not found."
}
```

---

## 3. Create Organization

**POST** `https://rideguide.test/api/organizations`

Creates a new organization. Requires `admin`, `super_admin`, or `organization` role.

**Role-specific behaviour:**
- **`organization` role** — `owner_user_id` is automatically set to the caller's user ID. Limited to **one organization per user** — sending a second create request returns `409`.
- **`admin` / `super_admin`** — organization is created without an owner (`owner_user_id: null`). No one-per-user limit applies.

In Postman, go to the **Body** tab, select **raw** → **JSON**, and fill in:

```json
{
    "name": "TODA - New Terminal",
    "type": "TODA",
    "description": "Primary short-distance transport association in GenSan operating motorized tricycles. Coordinates route compliance and supports the PUV Modernization Program (PUVMP).",
    "address": "Purok 5, Brgy. San Isidro, General Santos City",
    "contact_number": "0912-345-6789"
}
```

**Field Rules**

| Field            | Type   | Required | Rules                                               |
|------------------|--------|----------|-----------------------------------------------------|
| `name`           | string | Yes      | Max 255 chars, must be unique across organizations  |
| `type`           | string | Yes      | Max 100 chars — `TODA` or `MODA`                    |
| `description`    | string | No       | Max 1000 chars                                      |
| `address`        | string | No       | Max 500 chars                                       |
| `contact_number` | string | No       | Max 20 chars                                        |

> **Note:** `status` and `owner_user_id` are not accepted on creation. Status always defaults to `active`. `owner_user_id` is set automatically for `organization`-role users.

**Success Response (201)**
```json
{
    "success": true,
    "message": "Organization created successfully.",
    "data": {
        "id": "019f3c4d-5e6f-7890-1a2b-c3d4e5f67890",
        "name": "TODA - New Terminal",
        "type": "TODA",
        "description": "Primary short-distance transport association in GenSan.",
        "address": "Purok 5, Brgy. San Isidro, General Santos City",
        "contact_number": "0912-345-6789",
        "status": "active",
        "owner_user_id": "01a2b3c4-d5e6-7890-f1a2-b3c4d5e6f789",
        "created_at": "2026-03-07T12:00:00.000000Z",
        "updated_at": "2026-03-07T12:00:00.000000Z"
    }
}
```

**Error Response — Duplicate Name (422)**
```json
{
    "message": "The name has already been taken.",
    "errors": {
        "name": ["The name has already been taken."]
    }
}
```

**Error Response — Already Has Organization (409)** *(organization role only)*
```json
{
    "success": false,
    "message": "You already have a registered organization."
}
```

**Error Response — Unauthorized (403)**
```json
{
    "message": "This action is unauthorized."
}
```

---

## 4. Update Organization

**PUT** `https://rideguide.test/api/organizations/{id}`

Partially updates an organization. All fields are optional — send only what you want to change.

**Role-specific behaviour:**
- **`admin` / `super_admin`** — can update any organization, including changing `status`.
- **`organization` role** — can only update **their own** organization (`owner_user_id` must match the caller). The `status` field is **ignored** even if sent — only admins can activate/deactivate organizations.

In Postman, go to the **Body** tab, select **raw** → **JSON**:

```json
{
    "name": "TODA - New Terminal (Updated)",
    "contact_number": "0998-765-4321",
    "status": "inactive"
}
```

**Field Rules**

| Field            | Type   | Required | Rules                                                |
|------------------|--------|----------|------------------------------------------------------|
| `name`           | string | No       | Max 255 chars, must be unique (ignores current record) |
| `type`           | string | No       | Max 100 chars — `TODA` or `MODA`                     |
| `description`    | string | No       | Max 1000 chars, nullable                             |
| `address`        | string | No       | Max 500 chars, nullable                              |
| `contact_number` | string | No       | Max 20 chars, nullable                               |
| `status`         | string | No       | `active` or `inactive` — **admin/super_admin only**  |

**Success Response (200)**
```json
{
    "success": true,
    "message": "Organization updated successfully.",
    "data": {
        "id": "019f3c4d-5e6f-7890-1a2b-c3d4e5f67890",
        "name": "TODA - New Terminal (Updated)",
        "type": "TODA",
        "description": "Primary short-distance transport association in GenSan.",
        "address": "Purok 5, Brgy. San Isidro, General Santos City",
        "contact_number": "0998-765-4321",
        "status": "inactive",
        "owner_user_id": "01a2b3c4-d5e6-7890-f1a2-b3c4d5e6f789",
        "created_at": "2026-03-07T12:00:00.000000Z",
        "updated_at": "2026-03-07T12:30:00.000000Z"
    }
}
```

**Error Response — Not Found (404)**
```json
{
    "success": false,
    "message": "Organization not found."
}
```

---

## 5. Delete Organization

**DELETE** `https://rideguide.test/api/organizations/{id}`

Soft-deletes an organization. The record is **not** permanently removed and can be restored. Soft-deleted organizations no longer appear in the public listing or show endpoints.

**Role-specific behaviour:**
- **`admin` / `super_admin`** — can delete any organization.
- **`organization` role** — can only delete **their own** organization.

**Example Request**

```
DELETE https://rideguide.test/api/organizations/019f3c4d-5e6f-7890-1a2b-c3d4e5f67890
```

No request body is needed.

**Success Response (200)**
```json
{
    "success": true,
    "message": "Organization deleted successfully."
}
```

**Error Response — Not Found (404)**
```json
{
    "success": false,
    "message": "Organization not found."
}
```

---

## 6. Restore Organization

**PUT** `https://rideguide.test/api/organizations/{id}/restore`

Restores a previously soft-deleted organization. Requires `admin` or `super_admin` role.

**Example Request**

```
PUT https://rideguide.test/api/organizations/019f3c4d-5e6f-7890-1a2b-c3d4e5f67890/restore
```

No request body is needed.

**Success Response (200)**
```json
{
    "success": true,
    "message": "Organization restored successfully.",
    "data": {
        "id": "019f3c4d-5e6f-7890-1a2b-c3d4e5f67890",
        "name": "TODA - New Terminal (Updated)",
        "type": "TODA",
        "address": "Purok 5, Brgy. San Isidro, General Santos City",
        "contact_number": "0998-765-4321",
        "status": "inactive",
        "created_at": "2026-03-07T12:00:00.000000Z",
        "updated_at": "2026-03-07T13:00:00.000000Z"
    }
}
```

**Error Response — Not Found (404)**
```json
{
    "success": false,
    "message": "Organization not found."
}
```

---

## Linking a Driver to an Organization

When creating or updating a driver profile, pass the `organization_id` (UUID of the organization) in the request body:

```json
{
    "license_number": "D01 00 000001",
    "franchise_number": "1984516156",
    "organization_id": "019f2a3b-1c2d-7e4f-a5b6-c7d8e9f01234"
}
```

The organization must exist and be **active** for the validation to pass. Omit the field or set it to `null` to unlink a driver from any organization.

When reading a driver profile, the response now includes an `organization` object:

```json
{
    "driver_profile": {
        "id": "...",
        "license_number": "D01 00 000001",
        "franchise_number": "1984516156",
        "verification_status": "unverified",
        "organization": {
            "id": "019f2a3b-1c2d-7e4f-a5b6-c7d8e9f01234",
            "name": "TODA - Lagao Terminal",
            "type": "TODA"
        }
    }
}
```

---

## Organization Types Reference

The following organization types operate in General Santos City:

| Type   | Full Name                                              | Description                                        |
|--------|--------------------------------------------------------|----------------------------------------------------|
| `TODA` | Tricycle Operators and Drivers Association             | Tricycle (motorized three-wheeler) operators       |
| `MODA` | Motorcycle/Motorized Operators and Drivers Association | Motorcycle-for-hire (habal-habal) operators        |

---

## Seeded Organizations

The following organizations are pre-loaded via `OrganizationSeeder`:

| Name                                | Type   | Area                            |
|-------------------------------------|--------|---------------------------------|
| TODA - Bulaong Terminal             | TODA   | Bulaong                         |
| TODA - Calumpang Terminal           | TODA   | Calumpang                       |
| TODA - City Heights Terminal        | TODA   | City Heights                    |
| TODA - Dadiangas Terminal           | TODA   | Dadiangas                       |
| TODA - Lagao Terminal               | TODA   | Lagao                           |
| TODA - Labangal Terminal            | TODA   | Labangal                        |
| TODA - Apopong Terminal             | TODA   | Apopong                         |
| TODA - Bula Terminal                | TODA   | Bula                            |
| TODA - San Isidro Terminal          | TODA   | San Isidro                      |
| TODA - Fatima Terminal              | TODA   | Fatima                          |
| MODA - Makar Wharf Terminal         | MODA   | Makar Wharf                     |
| MODA - Fishport Terminal            | MODA   | General Santos Fish Port Complex|

---

## Error Reference

| HTTP Status | Meaning                                                                           |
|-------------|-----------------------------------------------------------------------------------|
| `200`       | Request succeeded                                                                 |
| `201`       | Resource created successfully                                                     |
| `403`       | Forbidden — caller lacks permission (wrong role, or not the owner)                |
| `404`       | Organization not found (deleted, inactive for non-admins, or invalid ID)          |
| `409`       | Conflict — `organization`-role user already has a registered organization         |
| `422`       | Validation failed — see `errors` object for field-level details                   |
| `401`       | Unauthenticated — missing or invalid Bearer Token                                 |
