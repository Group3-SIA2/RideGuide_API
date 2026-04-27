# Eloquent Models Phase 1.5-1.8 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create four Laravel Eloquent models (CommuterRideRequest, RideRequest, DriverLocation, Barangay) with proper relationships, scopes, casts, and UUID support for the OpenStreetMap integration feature.

**Architecture:** Each model is independently instantiable and implements relationships to other models. Models use UUID primary keys, some use soft deletes, and all include appropriate query scopes for business logic. Models follow Laravel conventions and will be tested via tinker instantiation.

**Tech Stack:** Laravel Eloquent, UUID trait/casting, soft deletes trait, datetime casting, git

---

## File Structure

### Created Files:
- `app/Models/CommuterRideRequest.php` - Main ride request from commuters
- `app/Models/RideRequest.php` - Driver response to commuter requests
- `app/Models/DriverLocation.php` - Real-time driver location tracking
- `app/Models/Barangay.php` - Geographic data model for barangays

### Dependencies:
- `app/Models/User.php` - Existing model referenced by relationships (Commuter/Driver roles)
- `app/Models/Route.php` - Existing model referenced by CommuterRideRequest
- `app/Models/Terminal.php` - Existing model referenced by CommuterRideRequest

---

## Task 1: Create CommuterRideRequest Model

**Files:**
- Create: `app/Models/CommuterRideRequest.php`

- [ ] **Step 1: Create the CommuterRideRequest model file**

Create `app/Models/CommuterRideRequest.php` with the following content:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommuterRideRequest extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'commuter_ride_requests';

    protected $fillable = [
        'commuter_id',
        'route_id',
        'terminal_id',
        'destination',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * The Commuter (User) who made this request
     */
    public function commuter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commuter_id');
    }

    /**
     * The Route this request is associated with
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    /**
     * The Terminal this request starts from
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }

    /**
     * Driver responses to this commuter request
     */
    public function rideRequests(): HasMany
    {
        return $this->hasMany(RideRequest::class, 'commuter_ride_request_id');
    }

    /**
     * Scope: Active requests that haven't expired
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope: Expired requests
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope: Not expired requests
     */
    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
```

- [ ] **Step 2: Verify the model file is properly formatted**

Check the file at `app/Models/CommuterRideRequest.php`:
- Contains all required attributes in $fillable
- Includes HasUuids and SoftDeletes traits
- Has all four relationships defined
- Has all three scopes (active, expired, notExpired)
- Proper use of datetime cast for expires_at

- [ ] **Step 3: Commit the CommuterRideRequest model**

```bash
git add app/Models/CommuterRideRequest.php
git commit -m "feat: add CommuterRideRequest model"
```

---

## Task 2: Create RideRequest Model

**Files:**
- Create: `app/Models/RideRequest.php`

- [ ] **Step 1: Create the RideRequest model file**

Create `app/Models/RideRequest.php` with the following content:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideRequest extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'ride_requests';

    protected $fillable = [
        'driver_id',
        'commuter_ride_request_id',
        'status',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    /**
     * The Driver (User) responding to this request
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * The Commuter Ride Request this is responding to
     */
    public function commuterRideRequest(): BelongsTo
    {
        return $this->belongsTo(CommuterRideRequest::class, 'commuter_ride_request_id');
    }

    /**
     * Scope: Pending ride requests
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Accepted ride requests
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    /**
     * Scope: Completed ride requests
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
```

- [ ] **Step 2: Verify the model file is properly formatted**

Check the file at `app/Models/RideRequest.php`:
- Contains all required attributes in $fillable
- Includes HasUuids and SoftDeletes traits
- Has both relationships defined (driver, commuterRideRequest)
- Has all three scopes (pending, accepted, completed)
- Proper use of datetime cast for responded_at

- [ ] **Step 3: Commit the RideRequest model**

```bash
git add app/Models/RideRequest.php
git commit -m "feat: add RideRequest model"
```

---

## Task 3: Create DriverLocation Model

**Files:**
- Create: `app/Models/DriverLocation.php`

- [ ] **Step 1: Create the DriverLocation model file**

Create `app/Models/DriverLocation.php` with the following content:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverLocation extends Model
{
    use HasUuids;

    protected $table = 'driver_locations';

    protected $fillable = [
        'driver_id',
        'latitude',
        'longitude',
        'heading',
        'accuracy',
    ];

    public $timestamps = false;

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    /**
     * The Driver whose location this is
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
```

- [ ] **Step 2: Verify the model file is properly formatted**

Check the file at `app/Models/DriverLocation.php`:
- Contains all required location attributes in $fillable
- Includes HasUuids trait but NOT SoftDeletes
- Has driver relationship defined
- Has $timestamps = false to disable created_at
- Has updated_at cast to datetime
- No query scopes (per specification)

- [ ] **Step 3: Commit the DriverLocation model**

```bash
git add app/Models/DriverLocation.php
git commit -m "feat: add DriverLocation model"
```

---

## Task 4: Create Barangay Model

**Files:**
- Create: `app/Models/Barangay.php`

- [ ] **Step 1: Create the Barangay model file**

Create `app/Models/Barangay.php` with the following content:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barangay extends Model
{
    use HasUuids;

    protected $table = 'barangays';

    protected $fillable = [
        'name',
        'code',
        'center_latitude',
        'center_longitude',
        'north_latitude',
        'south_latitude',
        'east_longitude',
        'west_longitude',
    ];

    protected $casts = [
        'center_latitude' => 'float',
        'center_longitude' => 'float',
        'north_latitude' => 'float',
        'south_latitude' => 'float',
        'east_longitude' => 'float',
        'west_longitude' => 'float',
    ];

    /**
     * Driver locations within this barangay
     */
    public function driverLocations(): HasMany
    {
        return $this->hasMany(DriverLocation::class, 'barangay_id');
    }
}
```

- [ ] **Step 2: Verify the model file is properly formatted**

Check the file at `app/Models/Barangay.php`:
- Contains all geographic attributes in $fillable
- Includes HasUuids trait but NOT SoftDeletes
- Has driverLocations relationship defined
- Has float casts for all coordinate fields
- Normal timestamps (created_at, updated_at)

- [ ] **Step 3: Commit the Barangay model**

```bash
git add app/Models/Barangay.php
git commit -m "feat: add Barangay model"
```

---

## Task 5: Verification via Tinker

**Files:**
- No files created or modified

- [ ] **Step 1: Start Laravel Tinker**

Run: `php artisan tinker`

- [ ] **Step 2: Test CommuterRideRequest instantiation**

In tinker, run:
```php
$model = new CommuterRideRequest();
get_class($model)
```

Expected output: `"App\Models\CommuterRideRequest"`

- [ ] **Step 3: Test RideRequest instantiation**

In tinker, run:
```php
$model = new RideRequest();
get_class($model)
```

Expected output: `"App\Models\RideRequest"`

- [ ] **Step 4: Test DriverLocation instantiation**

In tinker, run:
```php
$model = new DriverLocation();
get_class($model)
```

Expected output: `"App\Models\DriverLocation"`

- [ ] **Step 5: Test Barangay instantiation**

In tinker, run:
```php
$model = new Barangay();
get_class($model)
```

Expected output: `"App\Models\Barangay"`

- [ ] **Step 6: Exit Tinker**

Run: `exit`

---

## Self-Review Checklist

✓ **Spec coverage:**
- CommuterRideRequest: All attributes, relationships, scopes, casts, UUID, soft deletes
- RideRequest: All attributes, relationships, scopes, casts, UUID, soft deletes
- DriverLocation: All attributes, relationships, casts, UUID (no soft deletes, no timestamps except updated_at)
- Barangay: All attributes, relationships, casts, UUID (no soft deletes)

✓ **Placeholder scan:**
- No TBD, TODO, or placeholder content
- All code is complete and functional
- All relationships properly defined with correct foreign keys
- All scopes properly defined with correct query logic

✓ **Type consistency:**
- Relationship names follow Laravel conventions (singular for BelongsTo, plural for HasMany)
- Attribute names match database schema expectations
- Casts are appropriate for data types
- All method signatures are consistent with Laravel patterns

✓ **No gaps:**
- Four models implemented
- All relationships verified
- All scopes verified
- All casts verified
- Git commits for each model
- Verification steps included

---

## Execution Options

**Plan complete. Two execution options:**

**1. Subagent-Driven (recommended)** - Fresh subagent per task, fast iteration

**2. Inline Execution** - Execute all tasks in this session using executing-plans

**Which approach?**
