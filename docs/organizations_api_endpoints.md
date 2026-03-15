# RideGuide Organizations API

## Overview

The Organizations API manages TODA/MODA organizations.

- `admin` / `super_admin`: full access
- `organization`: can create one org (auto-owned), update/delete own org only; cannot change `status` or `owner_user_id`
- other roles: read-only

Schema-aligned fields:
- `name` (required)
- `type` (required)
- `description` (nullable)
- `hq_address` (nullable)
- `owner_user_id` (nullable, UUID)
- `status` (`active`/`inactive`)

Base URL:
```
https://rideguide.test/api/organizations
```

## Headers

```
Accept: application/json
Content-Type: application/json
Authorization: Bearer {token}
```

## Endpoints Summary

| Method   | Endpoint                          | Description                 |
|----------|-----------------------------------|-----------------------------|
| `GET`    | `/api/organizations`              | List active organizations   |
| `GET`    | `/api/organizations/{id}`         | Show one organization       |
| `POST`   | `/api/organizations`              | Create organization         |
| `PUT`    | `/api/organizations/{id}`         | Update organization         |
| `DELETE` | `/api/organizations/{id}`         | Soft-delete organization    |
| `PUT`    | `/api/organizations/{id}/restore` | Restore soft-deleted org    |

---

## 1) List Organizations

**GET** `/api/organizations`

Returns active organizations only.

Query params:
- `search` (name, type, or `hq_address`)
- `type`
- `per_page` (default `20`, max `100`)

Example:
```
GET /api/organizations?type=TODA&search=lagao&per_page=10
```

Success (200):
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
        "description": "Primary short-distance transport association.",
        "hq_address": "Lagao, General Santos City",
        "status": "active",
        "owner_user_id": null,
        "created_at": "2026-03-07T11:25:05.000000Z",
        "updated_at": "2026-03-07T11:25:05.000000Z"
      }
    ]
  }
}
```

## 2) Show Organization

**GET** `/api/organizations/{id}`

- Admin/super_admin can view active or inactive.
- Non-admin users can view active only.

Success (200) includes `drivers_count`.

Not found (404):
```json
{
  "success": false,
  "message": "Organization not found."
}
```

## 3) Create Organization

**POST** `/api/organizations`

Body (JSON):
```json
{
  "name": "TODA - New Terminal",
  "type": "TODA",
  "description": "Primary short-distance transport association in GenSan.",
  "hq_address": "Purok 5, Brgy. San Isidro, General Santos City",
  "owner_user_id": "01a2b3c4-d5e6-7890-f1a2-b3c4d5e6f789"
}
```

Rules:
- `name`: required, unique, max 255
- `type`: required, max 100
- `description`: nullable, max 1000
- `hq_address`: nullable, max 500
- `owner_user_id`: nullable UUID, must exist in `users`, and must have `admin`, `super_admin`, or `organization` role
- selected owner user must be active (not soft-deleted/inactive)

Role behavior:
- `organization` caller: limited to one organization; server auto-sets `owner_user_id` to authenticated user.
- `admin` / `super_admin`: may set `owner_user_id` to an admin/super_admin/organization user or leave null.

Already has org (organization role, 409):
```json
{
  "success": false,
  "message": "You already have a registered organization."
}
```

## 4) Update Organization

**PUT** `/api/organizations/{id}`

Partial update supported (`sometimes` validation).

Updatable fields:
- `name`, `type`, `description`, `hq_address`, `owner_user_id`, `status`

Role behavior:
- `admin` / `super_admin`: can update all fields.
- `organization`: own organization only; `status` and `owner_user_id` are ignored.

`owner_user_id` validation:
- nullable UUID
- must exist in `users`
- if provided, user must have `admin`, `super_admin`, or `organization` role
- selected owner user must be active (not soft-deleted/inactive)

## 5) Delete Organization

**DELETE** `/api/organizations/{id}`

Soft delete only.

Success (200):
```json
{
  "success": true,
  "message": "Organization deleted successfully."
}
```

## 6) Restore Organization

**PUT** `/api/organizations/{id}/restore`

Admin/super_admin only.

Success (200):
```json
{
  "success": true,
  "message": "Organization restored successfully.",
  "data": {
    "id": "019f3c4d-5e6f-7890-1a2b-c3d4e5f67890",
    "name": "TODA - New Terminal",
    "type": "TODA",
    "hq_address": "Purok 5, Brgy. San Isidro, General Santos City",
    "status": "inactive"
  }
}
```

---

## Notes

- Use `organization_id` on driver endpoints to link a driver to an organization.
- For organizations, use `hq_address` (not `address`) and do not use `contact_number`.
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
