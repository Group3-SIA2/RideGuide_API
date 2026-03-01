# RideGuide Search API

## Overview

The Search API allows users to search and filter drivers and commuters. Access is **role-based** — each role sees different data and has different filtering capabilities.

**Base URL**
```
https://rideguide.test/api/
```

---

## Headers

For all requests, include these headers in Postman:
```
Accept: application/json
Content-Type: application/json
Authorization: Bearer {your_token_here}
```

All search endpoints are **protected** and require a Bearer Token. See the [Authentication API](auth_api_endpoints.md) for how to obtain a token.

---

## Access Control Summary

| Role         | Search Drivers                       | Search Commuters               |
|--------------|--------------------------------------|--------------------------------|
| **Admin**    |  Full details + all filters          |  Full details + all filters    |
| **Commuter** |  Verified drivers only, limited info |  Blocked                       |
| **Driver**   |  Blocked                             |  Limited info                  |

---

## Endpoints

---

### 1. Search Drivers

**GET** `https://rideguide.test/api/search/drivers`

Searches and filters driver profiles. Results and visible fields depend on the authenticated user's role.

- **Admin** — sees all drivers with full details and can filter by verification status.
- **Commuter** — sees only verified drivers with limited public info.
- **Driver** — cannot access this endpoint (403).

#### Query Parameters

All parameters are optional. Add them to the URL as query strings.

| Parameter             | Type   | Description                                                                                                   | Available To    |
|-----------------------|--------|---------------------------------------------------------------------------------------------------------------|-----------------|
| `search`              | string | Search by first name, last name, email, license number, or franchise number                                   | Admin, Commuter |
| `verification_status` | string | Filter by `unverified`, `verified`, or `rejected`                                                             | Admin only      |
| `sort_by`             | string | Sort by `license_number`, `franchise_number`, `verification_status`, or `created_at` (default: |`created_at`) | Admin, Commuter |
| `sort_order`          | string | `asc` or `desc` (default: `desc`)                                                                             | Admin, Commuter |

#### Example Requests

**Admin — Get all drivers (no filters):**
```
GET https://rideguide.test/api/search/drivers
```

**Admin — Search by name:**
```
GET https://rideguide.test/api/search/drivers?search=Juan
```

**Admin — Filter by verification status:**
```
GET https://rideguide.test/api/search/drivers?verification_status=unverified
```

**Admin — Combined search + filter + sort:**
```
GET https://rideguide.test/api/search/drivers?search=Juan&verification_status=verified&sort_by=franchise_number&sort_order=asc
```

**Commuter — Search drivers (only verified results returned):**
```
GET https://rideguide.test/api/search/drivers?search=dela cruz
```

#### Success Response — Admin (200)
```json
{
    "success": true,
    "data": [
        {
            "id": "9f1a2b3c-...",
            "franchise_number": "FR-20260001",
            "driver_name": "Edriane Bangonon",
            "user_id": "8a4b5c6d-...",
            "license_number": "N01-12-345678",
            "verification_status": "verified",
            "email": "edriane.bangonon26@gmail.com",
            "created_at": "2026-02-15T10:30:00.000000Z",
            "updated_at": "2026-02-20T08:00:00.000000Z"
        }
    ],
    "total": 1
}
```

#### Success Response — Commuter (200)
```json
{
    "success": true,
    "data": [
        {
            "id": "9f1a2b3c-...",
            "franchise_number": "FR-20260001",
            "driver_name": "Edriane Bangonon"
        }
    ],
    "total": 1
}
```

#### Success Response — No Results (200)
```json
{
    "success": true,
    "data": [],
    "total": 0
}
```

#### Error Response — Driver Trying to Search Drivers (403)
```json
{
    "success": false,
    "message": "Unauthorized. Drivers cannot search other drivers."
}
```

#### Error Response — Unauthenticated (401)
```json
{
    "error": "Unauthenticated"
}
```

---

### 2. Search Commuters

**GET** `https://rideguide.test/api/search/commuters`

Searches and filters commuter profiles. Results and visible fields depend on the authenticated user's role.

- **Admin** — sees all commuters with full details including discount info.
- **Driver** — sees commuters with limited public info.
- **Commuter** — cannot access this endpoint (403).

#### Query Parameters

All parameters are optional. Add them to the URL as query strings.

| Parameter        | Type   | Description                                        | Available To  |
|------------------|--------|----------------------------------------------------|---------------|
| `search`         | string | Search by first name, last name, or email          | Admin, Driver |
| `classification` | string | Filter by `Regular`, `Student`, `Senior`, or `PWD` | Admin, Driver |
| `sort_by`        | string | Sort by `created_at` (default: `created_at`)       | Admin, Driver |
| `sort_order`     | string | `asc` or `desc` (default: `desc`)                  | Admin, Driver |

#### How Classification Filtering Works

| Classification | How It Filters                                                |
|----------------|---------------------------------------------------------------|
| `Regular`      | Commuters with **no discount** record (`discount_id` is null) |
| `Student`      | Commuters whose discount classification type is "Student"     |
| `Senior`       | Commuters whose discount classification type is "Senior"      |
| `PWD`          | Commuters whose discount classification type is "PWD"         |

#### Example Requests

**Admin — Get all commuters (no filters):**
```
GET https://rideguide.test/api/search/commuters
```

**Admin — Search by name:**
```
GET https://rideguide.test/api/search/commuters?search=Maria
```

**Admin — Filter by classification:**
```
GET https://rideguide.test/api/search/commuters?classification=Student
```

**Admin — Combined search + classification filter + sort:**
```
GET https://rideguide.test/api/search/commuters?search=Maria&classification=PWD&sort_by=created_at&sort_order=asc
```

**Driver — Search commuters:**
```
GET https://rideguide.test/api/search/commuters?search=Santos
```

#### Success Response — Admin (200)
```json
{
    "success": true,
    "data": [
        {
            "id": "7e8f9a0b-...",
            "classification_name": "Student",
            "commuter_name": "Edriane Bangonon",
            "user_id": "3c4d5e6f-...",
            "email": "edriane.bangonon@gmail.com",
            "discount": {
                "id": "2b3c4d5e-...",
                "ID_number": "2021 00456",
                "ID_image_path": "discount_ids/abc123.jpg",
                "classification": "Student"
            },
            "created_at": "2026-02-18T14:00:00.000000Z",
            "updated_at": "2026-02-18T14:00:00.000000Z"
        },
        {
            "id": "1a2b3c4d-...",
            "classification_name": "Regular",
            "commuter_name": "Kian Mhyco",
            "user_id": "5f6a7b8c-...",
            "email": "kian@gmail.com",
            "discount": null,
            "created_at": "2026-02-20T09:00:00.000000Z",
            "updated_at": "2026-02-20T09:00:00.000000Z"
        }
    ],
    "total": 2
}
```

#### Success Response — Driver (200)
```json
{
    "success": true,
    "data": [
        {
            "id": "7e8f9a0b-...",
            "classification_name": "Student",
            "commuter_name": "Edriane Bangonon"
        },
        {
            "id": "1a2b3c4d-...",
            "classification_name": "Regular",
            "commuter_name": "Kian Mhyco"
        }
    ],
    "total": 2
}
```

#### Success Response — No Results (200)
```json
{
    "success": true,
    "data": [],
    "total": 0
}
```

#### Error Response — Commuter Trying to Search Commuters (403)
```json
{
    "success": false,
    "message": "Unauthorized. Only admins and drivers can search commuters."
}
```

#### Error Response — Unauthenticated (401)
```json
{
    "error": "Unauthenticated"
}
```

---

## Response Fields Reference

### Driver Fields

| Field                 | Type              | Description                             | Visible To      |
|-----------------------|-------------------|-----------------------------------------|-----------------|
| `id`                  | string (UUID)     | Driver profile ID                       | Admin, Commuter |
| `franchise_number`    | string            | Driver's franchise number               | Admin, Commuter |
| `driver_name`         | string            | Driver's full name (first + last)       | Admin, Commuter |
| `user_id`             | string (UUID)     | Associated user account ID              | Admin only      |
| `license_number`      | string            | Driver's license number                 | Admin only      |
| `verification_status` | string            | `unverified`, `verified`, or `rejected` | Admin only      |
| `email`               | string            | Driver's email address                  | Admin only      |
| `created_at`          | string (datetime) | Profile creation timestamp              | Admin only      |
| `updated_at`          | string (datetime) | Last update timestamp                   | Admin only      |

### Commuter Fields

| Field                     | Type              | Description                              | Visible To    |
|---------------------------|-------------------|------------------------------------------|---------------|
| `id`                      | string (UUID)     | Commuter profile ID                      | Admin, Driver |
| `classification_name`     | string            | `Regular`, `Student`, `Senior`, or `PWD` | Admin, Driver |
| `commuter_name`           | string            | Commuter's full name (first + last)      | Admin, Driver |
| `user_id`                 | string (UUID)     | Associated user account ID               | Admin only    |
| `email`                   | string            | Commuter's email address                 | Admin only    |
| `discount`                | object/null       | Discount details (null for Regular)      | Admin only    |
| `discount.id`             | string (UUID)     | Discount record ID                       | Admin only    |
| `discount.ID_number`      | string            | Discount ID number                       | Admin only    |
| `discount.ID_image_path`  | string            | Path to uploaded ID image                | Admin only    |
| `discount.classification` | string            | Classification name from discount type   | Admin only    |
| `created_at`              | string (datetime) | Profile creation timestamp               | Admin only    |
| `updated_at`              | string (datetime) | Last update timestamp                    | Admin only    |

---

## Classifications Reference

| Classification | Description                                               | Has Discount Record |
|----------------|-----------------------------------------------------------|---------------------|
| `Regular`      | Standard commuter, no discount                            | No      NULL        |
| `Student`      | Student discount, requires ID verification                | Yes                 |
| `Senior`       | Senior citizen discount, requires ID verification         | Yes                 |
| `PWD`          | Person with disability discount, requires ID verification | Yes                 |

---

## Roles Reference

| Role       | Search Drivers                          | Search Commuters                          |
|------------|-----------------------------------------|-------------------------------------------|
| `admin`    |  All drivers, all filters, full details |  All commuters, all filters, full details |
| `commuter` |  Verified only, limited info            |  403 Forbidden                            |
| `driver`   | 403 Forbidden                           |  All commuters, limited info              |