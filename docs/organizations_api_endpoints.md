# RideGuide Organizations API

## Overview

The Organizations API allows users to browse driver organizations such as **TODA** (Tricycle Operators and Drivers Association), **PODA** (Pedicab Operators and Drivers Association), and **MODA** (Motorcycle Operators and Drivers Association) operating in General Santos City. Read operations are open to all authenticated users; write operations (create, update, delete, restore) are **admin-only**.

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

> **Note:** A valid Bearer Token is required for all endpoints. Tokens are obtained after completing the login + 2FA OTP flow. Only `admin` and `super_admin` roles can create, update, delete, or restore organizations.

---

## Endpoints Summary

| #  | Method   | Endpoint                            | Description                     | Access        |
|----|----------|-------------------------------------|---------------------------------|---------------|
| 1  | `GET`    | `/api/organizations`                | List all active organizations   | Authenticated |
| 2  | `GET`    | `/api/organizations/{id}`           | Get a single organization       | Authenticated |
| 3  | `POST`   | `/api/organizations`                | Create a new organization       | Admin only    |
| 4  | `PUT`    | `/api/organizations/{id}`           | Update an organization          | Admin only    |
| 5  | `DELETE` | `/api/organizations/{id}`           | Soft-delete an organization     | Admin only    |
| 6  | `PUT`    | `/api/organizations/{id}/restore`   | Restore a deleted organization  | Admin only    |

---

## 1. List Organizations

**GET** `https://rideguide.test/api/organizations`

Returns all **active** organizations. Supports optional query parameters for searching and filtering.

**Query Parameters (all optional)**

| Parameter | Type   | Description                                      |
|-----------|--------|--------------------------------------------------|
| `search`  | string | Search by name, type, or address                 |
| `type`    | string | Filter by type e.g. `TODA`, `PODA`, `MODA`      |

**Example Request (no filters)**

```
GET https://rideguide.test/api/organizations
```

**Example Request (with filters)**

```
GET https://rideguide.test/api/organizations?type=TODA&search=lagao
```

**Success Response (200)**
```json
{
    "success": true,
    "data": [
        {
            "id": "019f2a3b-1c2d-7e4f-a5b6-c7d8e9f01234",
            "name": "TODA - Lagao Terminal",
            "type": "TODA",
            "address": "Lagao, General Santos City",
            "contact_number": null,
            "status": "active",
            "created_at": "2026-03-07T11:25:05.000000Z",
            "updated_at": "2026-03-07T11:25:05.000000Z"
        }
    ]
}
```

---

## 2. Show Organization

**GET** `https://rideguide.test/api/organizations/{id}`

Returns a single organization by its UUID, including a count of how many drivers are linked to it.

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
        "address": "Lagao, General Santos City",
        "contact_number": null,
        "status": "active",
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

Creates a new organization. Requires `admin` or `super_admin` role.

In Postman, go to the **Body** tab, select **raw** → **JSON**, and fill in:

```json
{
    "name": "TODA - New Terminal",
    "type": "TODA",
    "address": "Purok 5, Brgy. San Isidro, General Santos City",
    "contact_number": "0912-345-6789"
}
```

**Field Rules**

| Field            | Type   | Required | Rules                                              |
|------------------|--------|----------|----------------------------------------------------|
| `name`           | string | Yes      | Max 255 chars, must be unique across organizations |
| `type`           | string | Yes      | Max 100 chars (e.g. `TODA`, `PODA`, `MODA`)        |
| `address`        | string | No       | Max 500 chars                                      |
| `contact_number` | string | No       | Max 20 chars                                       |

> **Note:** The `status` field is not accepted on creation — it always defaults to `active`. Use the Update endpoint to change it later.

**Success Response (201)**
```json
{
    "success": true,
    "message": "Organization created successfully.",
    "data": {
        "id": "019f3c4d-5e6f-7890-1a2b-c3d4e5f67890",
        "name": "TODA - New Terminal",
        "type": "TODA",
        "address": "Purok 5, Brgy. San Isidro, General Santos City",
        "contact_number": "0912-345-6789",
        "status": "active",
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

**Error Response — Unauthorized (403)**
```json
{
    "error": "Unauthorized."
}
```

---

## 4. Update Organization

**PUT** `https://rideguide.test/api/organizations/{id}`

Updates an existing organization. All fields are optional — only send what you want to change. Requires `admin` or `super_admin` role.

In Postman, go to the **Body** tab, select **raw** → **JSON**:

```json
{
    "name": "TODA - New Terminal (Updated)",
    "contact_number": "0998-765-4321",
    "status": "inactive"
}
```

**Field Rules**

| Field            | Type   | Required | Rules                                                             |
|------------------|--------|----------|-------------------------------------------------------------------|
| `name`           | string | No       | Max 255 chars, must be unique (ignores self)                      |
| `type`           | string | No       | Max 100 chars                                                     |
| `address`        | string | No       | Max 500 chars, nullable                                           |
| `contact_number` | string | No       | Max 20 chars, nullable                                            |
| `status`         | string | No       | Must be `active` or `inactive`                                    |

**Success Response (200)**
```json
{
    "success": true,
    "message": "Organization updated successfully.",
    "data": {
        "id": "019f3c4d-5e6f-7890-1a2b-c3d4e5f67890",
        "name": "TODA - New Terminal (Updated)",
        "type": "TODA",
        "address": "Purok 5, Brgy. San Isidro, General Santos City",
        "contact_number": "0998-765-4321",
        "status": "inactive",
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

Soft-deletes an organization. The record is **not** permanently removed and can be restored. Drivers linked to this organization will have their `organization_id` set to `null`. Requires `admin` or `super_admin` role.

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

The following organization types are used in General Santos City:

| Type   | Full Name                                          | Description                                      |
|--------|----------------------------------------------------|--------------------------------------------------|
| `TODA` | Tricycle Operators and Drivers Association         | Tricycle (motorized three-wheeler) operators     |
| `PODA` | Pedicab Operators and Drivers Association          | Pedicab (non-motorized, pedal-driven) operators  |
| `MODA` | Motorcycle Operators and Drivers Association       | Motorcycle-for-hire / habal-habal operators      |

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
| PODA - KCC Mall Terminal            | PODA   | KCC Mall                        |
| PODA - Gaisano Mall Terminal        | PODA   | Gaisano Mall                    |
| MODA - Makar Wharf Terminal         | MODA   | Makar Wharf                     |
| MODA - Fishport Terminal            | MODA   | General Santos Fish Port Complex|

---

## Error Reference

| HTTP Status | Meaning                                                            |
|-------------|--------------------------------------------------------------------|
| `200`       | Request succeeded                                                  |
| `201`       | Resource created successfully                                      |
| `403`       | Forbidden — caller does not have admin/super_admin role            |
| `404`       | Organization not found (or already permanently removed)            |
| `422`       | Validation failed — see `errors` object for field-level details    |
| `401`       | Unauthenticated — missing or invalid Bearer Token                  |
