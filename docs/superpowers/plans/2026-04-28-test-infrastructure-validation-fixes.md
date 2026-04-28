# Test Infrastructure & Validation Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix 9 failing tests by adding RefreshDatabase trait and implementing missing validation rules for location bounds and destination/route/terminal requirements.

**Architecture:** This plan fixes infrastructure issues in the test suite (test isolation) and adds validation rules to prevent invalid data (GSC bounds, required destination). The fixes are modular: test infrastructure (tasks 1-3) and validation rules (tasks 4-5).

**Tech Stack:** Laravel 11 with Pest framework, PHPUnit testing

---

## Task 1: Add RefreshDatabase trait to LocationControllerTest

**Files:**
- Modify: `tests/Feature/Api/LocationControllerTest.php:9-10`
- Test: `tests/Feature/Api/LocationControllerTest.php`

- [ ] **Step 1: Read the current test file**

The file is at `tests/Feature/Api/LocationControllerTest.php`. It currently lacks the `RefreshDatabase` trait which causes test isolation issues.

- [ ] **Step 2: Add RefreshDatabase use statement and trait**

Add the import:
```php
use Illuminate\Foundation\Testing\RefreshDatabase;
```

Add the trait to the class definition (after line 9):
```php
class LocationControllerTest extends TestCase
{
    use RefreshDatabase;
    
    // ... rest of class
}
```

The full section should look like:
```php
use App\Models\Barangay;
use App\Models\Terminal;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LocationControllerTest extends TestCase
{
    use RefreshDatabase;
```

- [ ] **Step 3: Fix assertIsArray() calls**

Replace line 36 and line 150:
- Line 36: `$response->assertIsArray();` → `$response->assertJsonIsArray();`
- Line 150: `$response->assertIsArray();` → `$response->assertJsonIsArray();`

- [ ] **Step 4: Run LocationControllerTest tests**

Run: `php artisan test tests/Feature/Api/LocationControllerTest.php --verbose`

Expected: 6/6 tests PASS

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Api/LocationControllerTest.php
git commit -m "fix: add RefreshDatabase trait to LocationControllerTest and fix assertions"
```

---

## Task 2: Add RefreshDatabase trait to AvailableCommutersControllerTest

**Files:**
- Modify: `tests/Feature/Api/AvailableCommutersControllerTest.php:10-12`
- Test: `tests/Feature/Api/AvailableCommutersControllerTest.php`

- [ ] **Step 1: Read the current test file**

The file is at `tests/Feature/Api/AvailableCommutersControllerTest.php`. It lacks the `RefreshDatabase` trait.

- [ ] **Step 2: Add RefreshDatabase use statement and trait**

Add the import:
```php
use Illuminate\Foundation\Testing\RefreshDatabase;
```

Add the trait to the class definition (after line 12):
```php
class AvailableCommutersControllerTest extends TestCase
{
    use RefreshDatabase;
    
    // ... rest of class
}
```

The import section (lines 1-10) should include:
```php
use Illuminate\Foundation\Testing\RefreshDatabase;
```

And the class definition should be:
```php
class AvailableCommutersControllerTest extends TestCase
{
    use RefreshDatabase;
    
    /**
     * GET /api/available-commuters
     */
```

- [ ] **Step 3: Run AvailableCommutersControllerTest tests**

Run: `php artisan test tests/Feature/Api/AvailableCommutersControllerTest.php --verbose`

Expected: All tests PASS (previously failing due to database isolation)

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Api/AvailableCommutersControllerTest.php
git commit -m "fix: add RefreshDatabase trait to AvailableCommutersControllerTest"
```

---

## Task 3: Add RefreshDatabase trait to RideRequestControllerTest

**Files:**
- Modify: `tests/Feature/Api/RideRequestControllerTest.php:9-11`
- Test: `tests/Feature/Api/RideRequestControllerTest.php`

- [ ] **Step 1: Read the current test file**

The file is at `tests/Feature/Api/RideRequestControllerTest.php`. It lacks the `RefreshDatabase` trait.

- [ ] **Step 2: Add RefreshDatabase use statement and trait**

Add the import:
```php
use Illuminate\Foundation\Testing\RefreshDatabase;
```

Add the trait to the class definition (after line 11):
```php
class RideRequestControllerTest extends TestCase
{
    use RefreshDatabase;
    
    // ... rest of class
}
```

The import section should be:
```php
use App\Models\CommuterRideRequest;
use App\Models\RideRequest;
use App\Models\Terminal;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
```

And the class definition:
```php
class RideRequestControllerTest extends TestCase
{
    use RefreshDatabase;
```

- [ ] **Step 3: Run RideRequestControllerTest tests**

Run: `php artisan test tests/Feature/Api/RideRequestControllerTest.php --verbose`

Expected: All tests PASS (previously failing due to database isolation)

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Api/RideRequestControllerTest.php
git commit -m "fix: add RefreshDatabase trait to RideRequestControllerTest"
```

---

## Task 4: Implement destination/route/terminal validation in RideRequestController

**Files:**
- Modify: `app/Http/Controllers/Api/RideRequestController.php:27-31`
- Test: `tests/Feature/Api/RideRequestControllerTest.php` (already validates this)

- [ ] **Step 1: Read the RideRequestController**

The file is at `app/Http/Controllers/Api/RideRequestController.php`. The `createRideRequest` method validates but allows all three fields to be null.

- [ ] **Step 2: Update validation rule**

Change line 27-31 from:
```php
$validated = $request->validate([
    'route_id' => 'nullable|uuid|exists:routes,id',
    'terminal_id' => 'nullable|uuid|exists:terminals,id',
    'destination' => 'required|string|max:255',
]);
```

To:
```php
$validated = $request->validate([
    'route_id' => 'nullable|uuid|exists:routes,id',
    'terminal_id' => 'nullable|uuid|exists:terminals,id',
    'destination' => 'required_without_all:route_id,terminal_id|string|max:255',
]);
```

This ensures that if both `route_id` and `terminal_id` are null/absent, then `destination` is required.

- [ ] **Step 3: Run RideRequestControllerTest to verify**

Run: `php artisan test tests/Feature/Api/RideRequestControllerTest.php::test_create_ride_request_requires_destination_or_route_or_terminal --verbose`

Expected: PASS

- [ ] **Step 4: Verify all RideRequestControllerTest tests still pass**

Run: `php artisan test tests/Feature/Api/RideRequestControllerTest.php --verbose`

Expected: All tests PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/RideRequestController.php
git commit -m "fix: add required_without_all validation for destination/route/terminal"
```

---

## Task 5: Implement GSC geographic bounds validation in AvailableCommutersController

**Files:**
- Modify: `app/Http/Controllers/Api/AvailableCommutersController.php:21-27`
- Test: `tests/Feature/Api/AvailableCommutersControllerTest.php` (tests will verify the bounds)

- [ ] **Step 1: Read the AvailableCommutersController**

The file is at `app/Http/Controllers/Api/AvailableCommutersController.php`. The `getAvailableCommuters` method validates lat/lon but doesn't check GSC bounds.

- [ ] **Step 2: Update validation with custom rules**

Replace lines 21-27:
```php
$validated = $request->validate([
    'latitude' => 'required|numeric|between:-90,90',
    'longitude' => 'required|numeric|between:-180,180',
    'route_id' => 'nullable|uuid|exists:routes,id',
    'terminal_id' => 'nullable|uuid|exists:terminals,id',
    'radius_meters' => 'nullable|integer|min:100|max:50000',
]);
```

With:
```php
$validated = $request->validate([
    'latitude' => [
        'required',
        'numeric',
        function ($attribute, $value, $fail) {
            if ($value < 5.5 || $value > 6.5) {
                $fail('Latitude must be within General Santos City bounds (5.5° to 6.5°N).');
            }
        },
    ],
    'longitude' => [
        'required',
        'numeric',
        function ($attribute, $value, $fail) {
            if ($value < 124.7 || $value > 125.7) {
                $fail('Longitude must be within General Santos City bounds (124.7° to 125.7°E).');
            }
        },
    ],
    'route_id' => 'nullable|uuid|exists:routes,id',
    'terminal_id' => 'nullable|uuid|exists:terminals,id',
    'radius_meters' => 'nullable|integer|min:100|max:50000',
]);
```

- [ ] **Step 3: Test with valid GSC coordinates**

Run: `php artisan test tests/Feature/Api/AvailableCommutersControllerTest.php::test_driver_sees_active_commuter_requests --verbose`

Expected: PASS (coordinates 6.1184, 125.1774 are within GSC bounds)

- [ ] **Step 4: Run all AvailableCommutersControllerTest tests**

Run: `php artisan test tests/Feature/Api/AvailableCommutersControllerTest.php --verbose`

Expected: All tests PASS (all test coordinates are within GSC bounds)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/AvailableCommutersController.php
git commit -m "fix: add GSC geographic bounds validation for latitude/longitude"
```

---

## Task 6: Run full test suite to verify all fixes

**Files:**
- No files modified; verification only

- [ ] **Step 1: Run all Feature API tests**

Run: `php artisan test tests/Feature/Api/ --verbose`

Expected: All 35+ tests PASS

- [ ] **Step 2: Check test output for any failures**

If any tests fail, review the output and determine if additional fixes are needed. The expected passing tests are:
- LocationControllerTest: 6 tests
- AvailableCommutersControllerTest: 9 tests
- RideRequestControllerTest: 10 tests
- DriverLocationControllerTest: 10 tests

Total: 35+ tests should PASS

- [ ] **Step 3: Record final status**

Capture the final test output showing all tests passing. This confirms the fixes are complete.

---

## Execution Notes

- **Test Isolation:** The `RefreshDatabase` trait ensures each test runs with a fresh database, preventing data from one test affecting another.
- **Validation Rules:** The `required_without_all` rule ensures at least one of destination/route/terminal is provided. The custom validation closures enforce GSC bounds (latitude 5.5-6.5°N, longitude 124.7-125.7°E).
- **Backwards Compatibility:** These changes don't break existing code; they only add safety constraints.
- **GSC Coordinates:** The test coordinates (6.1184°N, 125.1774°E) are within the GSC bounds and will continue to pass.

---

## Expected Outcome

✅ All 9 failing tests now passing
✅ No test isolation issues (RefreshDatabase trait isolates each test)
✅ Validation prevents invalid data (destination/route/terminal requirement, GSC bounds)
✅ All commits follow git best practices (DRY, one feature per commit)
