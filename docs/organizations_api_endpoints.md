# RideGuide Organizations API

## Overview

The Organizations API manages TODA/MODA/cooperative organizations and ownership workflows.

Access summary:
- admin / super_admin: full access (list all, include deleted, create/update/delete/restore)
- organization: can create one organization (auto-owned), update/delete own org only; cannot change status or owner_user_id
- other authenticated roles: read-only access to active organizations

Current schema-aligned request fields:
- name (required)
- organization_type (required, string)
- description (nullable, string): stored in organization_types.description and shared by organizations of the same type
- hq_address (nullable)
- owner_user_id (nullable, UUID)
- status (active or inactive)

Base URL:
```http
https://rideguide.test/api/organizations
```

## Headers

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer {token}
```

## Endpoint Summary

| Method | Endpoint | Description |
|---|---|---|
| GET | /api/organizations | List organizations (active by default; admin supports more filters) |
| GET | /api/organizations/{id} | Show one organization |
| POST | /api/organizations | Create organization |
| POST | /api/organizations/create-profile | Create organization profile for authenticated user |
| PUT | /api/organizations/{id} | Update organization |
| DELETE | /api/organizations/{id} | Soft-delete organization |
| PUT | /api/organizations/{id}/restore | Restore soft-deleted organization |
| GET | /api/organizations/assigned-drivers | List drivers assigned to caller's managed organization |

---

## 1) List Organizations

GET /api/organizations

Default behavior:
- non-admin users only receive active organizations
- admin/super_admin can use status and include_deleted filters

Query params:
- search (optional): searches organization name, organization type name, organization type description, and HQ address fields
- organization_type (optional): exact match by type name
- organization_type_id (optional): UUID match
- owner_user_id (optional): UUID match
- status (optional, admin/super_admin): active or inactive
- include_deleted (optional, admin/super_admin): true or false
- sort_by (optional): name, status, created_at, updated_at (default: name)
- sort_dir (optional): asc or desc (default: asc)
- per_page (optional): default 20, max 100

Example:
```http
GET /api/organizations?search=lagao&organization_type=TODA&sort_by=created_at&sort_dir=desc&per_page=10
```

Admin example with deleted records:
```http
GET /api/organizations?include_deleted=true&status=inactive&sort_by=updated_at&sort_dir=desc
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
        "organization_type": "TODA",
        "description": "Primary short-distance transport association.",
        "hq_address": "7f866f90-8f57-4d8b-b6d7-0f29c3466f6f",
        "status": "active",
        "owner_user_id": null,
        "created_at": "2026-03-07T11:25:05.000000Z",
        "updated_at": "2026-03-07T11:25:05.000000Z"
      }
    ]
  },
  "meta": {
    "filters": {
      "search": "lagao",
      "organization_type": "TODA",
      "organization_type_id": null,
      "owner_user_id": null,
      "status": null,
      "include_deleted": false
    },
    "sort": {
      "by": "created_at",
      "dir": "desc"
    }
  }
}
```

## 2) Show Organization

GET /api/organizations/{id}

Behavior:
- non-admin users can only access active organizations
- admin/super_admin may pass include_deleted=true to fetch soft-deleted records

Query params:
- include_deleted (optional, admin/super_admin): true or false

Success (200) includes:
- organization details
- organizationType relation
- hqAddress relation
- drivers_count

Not found (404):
```json
{
  "success": false,
  "message": "Organization not found."
}
```

## 3) Create Organization

POST /api/organizations

Body (JSON):
```json
{
  "name": "TODA - New Terminal",
  "organization_type": "TODA",
  "description": "Primary short-distance transport association in GenSan.",
  "hq_address": "Purok 5, Brgy. San Isidro, General Santos City",
  "owner_user_id": "01a2b3c4-d5e6-7890-f1a2-b3c4d5e6f789"
}
```

Rules:
- name: required, unique, max 255
- organization_type: required, max 100
- description: nullable, max 1000 (stored on organization type)
- hq_address: nullable, max 500
- owner_user_id: nullable UUID, must be an eligible active owner

Role behavior:
- organization caller: limited to one organization; server auto-sets owner_user_id to authenticated user
- admin / super_admin: may set owner_user_id or leave null

Conflict (409) when organization-role user already owns one:
```json
{
  "success": false,
  "message": "You already have a registered organization."
}
```

## 3b) Create Organization Profile (Flutter-Safe)

POST /api/organizations/create-profile

Creates an organization owned by the authenticated user while keeping multi-role support.

Body (JSON):
```json
{
  "name": "Lagao TODA",
  "organization_type": "TODA",
  "description": "Association-owned tricycle operators.",
  "hq_address": "Lagao, General Santos City",
  "roles": ["driver", "commuter"]
}
```

Rules:
- name, organization_type, description, hq_address follow the same validation limits as create
- roles (optional): array limited to driver and/or commuter
- caller must already have one of organization, admin, super_admin
- one organization per owner (returns 409 if already exists)

Success (201):
```json
{
  "success": true,
  "message": "Organization profile created successfully.",
  "data": {
    "organization": {
      "id": "019f...",
      "name": "Lagao TODA",
      "organization_type": "TODA",
      "description": "Association-owned tricycle operators."
    },
    "roles": ["driver", "organization"]
  }
}
```

## 3c) Get Assigned Drivers (Managed Organization)

GET /api/organizations/assigned-drivers

Returns drivers assigned to the authenticated user's managed organization.

Access:
- organization owners
- active organization managers (organization_user_role)

Query params:
- per_page (default 20, max 100)

Driver item includes organization summary:
- id
- name
- organization_type
- description

Not found (404):
```json
{
  "success": false,
  "message": "Organization not found for this user."
}
```

## 4) Update Organization

PUT /api/organizations/{id}

Partial update supported.

Updatable fields:
- name
- organization_type
- description (updates organization type description)
- hq_address
- owner_user_id
- status

Role behavior:
- admin / super_admin: can update all fields
- organization and org managers: status and owner_user_id are ignored

## 5) Delete Organization

DELETE /api/organizations/{id}

Soft delete only.

Success (200):
```json
{
  "success": true,
  "message": "Organization deleted successfully."
}
```

## 6) Restore Organization

PUT /api/organizations/{id}/restore

Admin/super_admin only.

Success (200):
```json
{
  "success": true,
  "message": "Organization restored successfully.",
  "data": {
    "id": "019f3c4d-5e6f-7890-1a2b-c3d4e5f67890",
    "name": "TODA - New Terminal",
    "organization_type": "TODA",
    "status": "inactive"
  }
}
```

---

## Flutter App Flow Integration

Recommended mobile flow for organization onboarding:
1. Register/Login: POST /api/auth/register or POST /api/auth/login
2. Verify OTP: POST /api/auth/verify-otp
3. Save base profile and roles: POST /api/setup/setup-users (include organization in roles if user will create an org)
4. Create organization profile: POST /api/organizations/create-profile
5. Load organization list/dashboard data:
- GET /api/organizations for listing/search/filter
- GET /api/organizations/assigned-drivers for org management driver screen

Flutter request tips:
- For list screens use query params: search, organization_type, sort_by, sort_dir, per_page
- For admin tools add include_deleted and status filters
- Treat description as type-level metadata (shared by organizations using that type)

Example list call from Flutter:
```text
GET /api/organizations?search=toda&organization_type=TODA&sort_by=name&sort_dir=asc&per_page=20
```

---

## Error Reference

| HTTP Status | Meaning |
|---|---|
| 200 | Request succeeded |
| 201 | Resource created successfully |
| 401 | Unauthenticated (missing/invalid token) |
| 403 | Forbidden (insufficient permission) |
| 404 | Organization not found |
| 409 | Conflict (organization-role user already has organization) |
| 422 | Validation failed |
