# Backup & Restore API Endpoints

> **Authorization**: All endpoints require **Sanctum authentication** and **`super_admin` role**.

Base URL: `/api/backups`

---

## 1. List All Backups

Fetch all available `.sql` backup files stored in Supabase Storage.

**Endpoint:** `GET /api/backups`

**Headers:**
| Header          | Value                  |
|-----------------|------------------------|
| Authorization   | Bearer `{token}`       |
| Accept          | application/json       |

**Success Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "name": "backup_rideguide_2026-03-09_21-18-36.sql",
            "size": "48.62 KB",
            "created_at": "2026-03-09T21:18:40.000Z",
            "updated_at": "2026-03-09T21:18:40.000Z"
        },
        {
            "name": "backup_rideguide_2026-03-08_22-00-00.sql",
            "size": "45.10 KB",
            "created_at": "2026-03-08T22:00:05.000Z",
            "updated_at": "2026-03-08T22:00:05.000Z"
        }
    ],
    "total": 2
}
```

**Error Responses:**
| Status | Description                              |
|--------|------------------------------------------|
| 401    | Unauthenticated                          |
| 403    | Unauthorized (not a super admin)         |
| 500    | Supabase credentials not configured      |
| 502    | Failed to fetch from Supabase            |

---

## 2. Create Backup (Manual)

Trigger a new database backup and upload it to Supabase Storage.

**Endpoint:** `POST /api/backups/create`

**Headers:**
| Header          | Value                  |
|-----------------|------------------------|
| Authorization   | Bearer `{token}`       |
| Accept          | application/json       |

**Success Response (200):**
```json
{
    "success": true,
    "message": "Database backup created successfully.",
    "data": {
        "output": "Starting database backup...\nRunning mysqldump...\nDatabase dump created: backup_rideguide_2026-03-10_14-30-00.sql (48.62 KB)\nUploading backup to Supabase Storage...\nBackup uploaded successfully to Supabase: backup_rideguide_2026-03-10_14-30-00.sql",
        "created_by": "admin@rideguide.com",
        "created_at": "2026-03-10 14:30:05"
    }
}
```

---

## 3. Download Backup File

Download a specific `.sql` backup file from Supabase Storage.

**Endpoint:** `GET /api/backups/{filename}/download`

**URL Parameters:**
| Parameter | Type   | Description                                         |
|-----------|--------|-----------------------------------------------------|
| filename  | string | The backup filename (e.g., `backup_rideguide_2026-03-09_21-18-36.sql`) |

**Headers:**
| Header          | Value                  |
|-----------------|------------------------|
| Authorization   | Bearer `{token}`       |

**Success Response (200):**
Returns the `.sql` file as a streamed download with `Content-Type: application/sql`.

**Error Responses:**
| Status | Description                              |
|--------|------------------------------------------|
| 400    | Invalid filename (not a .sql file)       |
| 403    | Unauthorized (not a super admin)         |
| 404    | Backup file not found in Supabase        |

---

## 4. Restore Database from Backup

Restore the MySQL database using a specific backup file from Supabase Storage.

> ⚠️ **WARNING**: This will **overwrite** the current database with the selected backup.

**Endpoint:** `POST /api/backups/{filename}/restore`

**URL Parameters:**
| Parameter | Type   | Description                                         |
|-----------|--------|-----------------------------------------------------|
| filename  | string | The backup filename (e.g., `backup_rideguide_2026-03-09_21-18-36.sql`) |

**Headers:**
| Header          | Value                  |
|-----------------|------------------------|
| Authorization   | Bearer `{token}`       |
| Accept          | application/json       |

**Success Response (200):**
```json
{
    "success": true,
    "message": "Database restored successfully.",
    "data": {
        "file": "backup_rideguide_2026-03-09_21-18-36.sql",
        "output": "Downloading backup from Supabase Storage...\nDownloaded: backup_rideguide_2026-03-09_21-18-36.sql (48.62 KB)\nRestoring database...\nRunning mysql restore...",
        "restored_by": "admin@rideguide.com",
        "restored_at": "2026-03-10 14:35:00"
    }
}
```

**Error Responses:**
| Status | Description                              |
|--------|------------------------------------------|
| 400    | Invalid filename (not a .sql file)       |
| 403    | Unauthorized (not a super admin)         |
| 500    | Restore failed (check `error` field)     |

---

## Artisan CLI Commands

In addition to the API, super admins with server access can use Artisan commands directly:

### List available backups
```bash
php artisan app:restore-database --list
```

### Restore a specific backup (interactive)
```bash
php artisan app:restore-database
```

### Restore a specific file (non-interactive)
```bash
php artisan app:restore-database --file=backup_rideguide_2026-03-09_21-18-36.sql --force
```

### Create a backup manually
```bash
php artisan app:backup-database
```
