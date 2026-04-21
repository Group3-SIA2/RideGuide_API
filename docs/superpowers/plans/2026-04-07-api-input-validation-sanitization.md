# API Input Validation and Sanitization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add API-wide sanitization and consistent validation hardening (including names with `Ññ`, password-safe handling, and query/input constraints) across all API controllers.

**Architecture:** Introduce a global API sanitization middleware that normalizes and cleans string inputs before controllers run, while explicitly skipping password fields. Add a shared validation helper to centralize reusable rule sets (names, passwords, safe strings, search terms), then refactor API controllers to use those rules for consistent enforcement and lower drift. Validate adoption with focused middleware/helper tests plus controller-adoption regression tests.

**Tech Stack:** Laravel 12, PHP 8.2, Pest, Eloquent Query Builder, Laravel Validator

---

## File Structure and Responsibilities

- Create: `app/Http/Middleware/SanitizeApiInput.php`  
  Global API request input sanitizer (recursive, password-key exclusions, safe-string normalization).
- Modify: `bootstrap/app.php`  
  Register sanitizer middleware in API middleware stack.
- Create: `app/Support/InputValidation.php`  
  Shared reusable validation rule arrays for names/passwords/search/general strings.
- Modify: `app/Http/Controllers/Api/AuthController.php`  
  Replace inline password/name/email rule duplication with shared rules.
- Modify: `app/Http/Controllers/Api/PhoneController.php`  
  Reuse shared password/string rules.
- Modify: `app/Http/Controllers/Api/SetUpController.php`  
  Use shared name rules (letters/spaces, Unicode-aware including `Ññ`).
- Modify: `app/Http/Controllers/Api/SearchController.php`  
  Validate and constrain `search`, `sort`, and related query inputs before use.
- Modify: `app/Http/Controllers/Api/OrganizationController.php`  
  Validate `search`, `sort_by`, `sort_dir`, and text payload fields via shared rules.
- Modify: `app/Http/Controllers/Api/EmergencyContactController.php`  
  Use shared string/name-like rules.
- Modify: `app/Http/Controllers/Api/UserController.php`  
  Keep status validation and add shared helpers where text input appears.
- Modify: `app/Http/Controllers/Api/DriverController.php`  
  Reuse shared safe string/identifier helpers for license fields where appropriate.
- Modify: `app/Http/Controllers/Api/CommuterController.php`  
  Reuse shared identifier/name-safe helpers where applicable.
- Modify: `app/Http/Controllers/Api/VehicleController.php`  
  Reuse shared safe text rules for `vehicle_type` and `description`.
- Modify: `app/Http/Controllers/Api/FareController.php`  
  Keep numeric validation strict and cleanly scoped.
- Create: `tests/Feature/ApiInputSanitizationMiddlewareTest.php`  
  Verifies sanitizer behavior and password exclusion.
- Create: `tests/Unit/InputValidationRulesTest.php`  
  Verifies shared rule behavior, including `Ññ` support and invalid character rejection.
- Create: `tests/Feature/ApiControllerValidationAdoptionTest.php`  
  Guards that targeted API controllers adopt shared validation helper usage.

### Task 1: Add global API sanitization middleware

**Files:**
- Create: `app/Http/Middleware/SanitizeApiInput.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/ApiInputSanitizationMiddlewareTest.php`

- [ ] **Step 1: Write the failing middleware test**

```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

test('api sanitizer strips html from non-password fields but keeps passwords untouched', function () {
    Route::middleware('api')->post('/_test/sanitize', function (Request $request) {
        return response()->json($request->all());
    });

    $payload = [
        'first_name' => '<script>alert(1)</script>Juan',
        'profile' => ['bio' => '<b>Safe</b> text'],
        'password' => 'P@ss<script>Word',
        'password_confirmation' => 'P@ss<script>Word',
    ];

    $response = $this->postJson('/api/_test/sanitize', $payload);

    $response->assertOk()
        ->assertJsonPath('first_name', 'alert(1)Juan')
        ->assertJsonPath('profile.bio', 'Safe text')
        ->assertJsonPath('password', 'P@ss<script>Word')
        ->assertJsonPath('password_confirmation', 'P@ss<script>Word');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ApiInputSanitizationMiddlewareTest.php -v`  
Expected: FAIL because sanitizer middleware is not registered yet.

- [ ] **Step 3: Implement middleware**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeApiInput
{
    private const PASSWORD_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $request->merge($this->sanitizeValue($request->all()));

        return $next($request);
    }

    private function sanitizeValue(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $k => $v) {
                $clean[$k] = $this->sanitizeValue($v, is_string($k) ? $k : null);
            }
            return $clean;
        }

        if (!is_string($value) || ($key && in_array($key, self::PASSWORD_KEYS, true))) {
            return $value;
        }

        $value = preg_replace('/[\\x00-\\x1F\\x7F]/u', '', $value) ?? $value;
        $value = trim($value);
        $value = strip_tags($value);

        return preg_replace('/\\s+/u', ' ', $value) ?? $value;
    }
}
```

- [ ] **Step 4: Register middleware for API routes**

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->api(prepend: [
        \App\Http\Middleware\SanitizeApiInput::class,
    ]);

    $middleware->alias([
        'permission' => \App\Http\Controllers\Auth\CheckPermission::class,
        'active.user' => \App\Http\Middleware\EnsureUserIsActive::class,
        'panel.role' => \App\Http\Middleware\EnsurePanelRole::class,
    ]);
})
```

- [ ] **Step 5: Re-run middleware test**

Run: `php artisan test tests/Feature/ApiInputSanitizationMiddlewareTest.php -v`  
Expected: PASS.

- [ ] **Step 6: Commit checkpoint**

```bash
git add app/Http/Middleware/SanitizeApiInput.php bootstrap/app.php tests/Feature/ApiInputSanitizationMiddlewareTest.php
git commit -m "feat(api): add global input sanitization middleware"
```

### Task 2: Add shared validation helper and tests

**Files:**
- Create: `app/Support/InputValidation.php`
- Test: `tests/Unit/InputValidationRulesTest.php`

- [ ] **Step 1: Write failing rule tests**

```php
<?php

use App\Support\InputValidation;
use Illuminate\Support\Facades\Validator;

test('name rules accept letters spaces and ñ', function () {
    $rules = ['first_name' => InputValidation::nameRequiredRules()];
    $validator = Validator::make(['first_name' => 'Peña Niño'], $rules);

    expect($validator->fails())->toBeFalse();
});

test('name rules reject symbols and html leftovers', function () {
    $rules = ['first_name' => InputValidation::nameRequiredRules()];
    $validator = Validator::make(['first_name' => 'Juan<script>'], $rules);

    expect($validator->fails())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/InputValidationRulesTest.php -v`  
Expected: FAIL because `InputValidation` does not exist yet.

- [ ] **Step 3: Implement `InputValidation` helper**

```php
<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class InputValidation
{
    public static function nameRequiredRules(): array
    {
        return ['required', 'string', 'max:255', 'regex:/^[\\p{L}\\s]+$/u'];
    }

    public static function nameNullableRules(): array
    {
        return ['nullable', 'string', 'max:255', 'regex:/^[\\p{L}\\s]+$/u'];
    }

    public static function passwordRequiredRules(): array
    {
        return ['required', 'string', Password::min(8)->mixedCase()->symbols()];
    }

    public static function passwordConfirmedRules(): array
    {
        return ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->symbols()];
    }

    public static function safeSearchRules(int $max = 120): array
    {
        return ['nullable', 'string', "max:{$max}", 'regex:/^[\\p{L}\\p{N}\\s@._,\\-]+$/u'];
    }

    public static function safeStringRules(bool $required = false, int $max = 255): array
    {
        $prefix = $required ? ['required'] : ['nullable'];
        return [...$prefix, 'string', "max:{$max}"];
    }
}
```

- [ ] **Step 4: Re-run unit tests**

Run: `php artisan test tests/Unit/InputValidationRulesTest.php -v`  
Expected: PASS.

- [ ] **Step 5: Commit checkpoint**

```bash
git add app/Support/InputValidation.php tests/Unit/InputValidationRulesTest.php
git commit -m "feat(api): add shared input validation helper"
```

### Task 3: Refactor auth/profile controllers to shared rules

**Files:**
- Modify: `app/Http/Controllers/Api/AuthController.php`
- Modify: `app/Http/Controllers/Api/PhoneController.php`
- Modify: `app/Http/Controllers/Api/SetUpController.php`

- [ ] **Step 1: Write/extend failing tests for auth/profile validation paths**

```php
<?php

use App\Support\InputValidation;
use Illuminate\Support\Facades\Validator;

test('setup name rules allow ñ and spaces', function () {
    $validator = Validator::make(
        ['first_name' => 'Niño Peña'],
        ['first_name' => InputValidation::nameRequiredRules()]
    );

    expect($validator->fails())->toBeFalse();
});

test('setup name rules reject numbers and symbols', function () {
    $validator = Validator::make(
        ['first_name' => 'John123!'],
        ['first_name' => InputValidation::nameRequiredRules()]
    );

    expect($validator->fails())->toBeTrue();
});

test('password rules require strong passwords', function () {
    $validator = Validator::make(
        ['password' => 'weakpass'],
        ['password' => InputValidation::passwordRequiredRules()]
    );

    expect($validator->fails())->toBeTrue();
});
```

- [ ] **Step 2: Run targeted tests and verify failure**

Run: `php artisan test tests/Unit/InputValidationRulesTest.php -v`  
Expected: FAIL until controller rules are migrated to shared helper rules.

- [ ] **Step 3: Refactor controller rules to shared helper**

```php
// AuthController.php
use App\Support\InputValidation;

$validated = $request->validate([
    'name' => InputValidation::safeStringRules(required: true, max: 255),
    'email' => ['required', 'string', 'email:rfc,filter', 'max:255', 'unique:users,email'],
    'password' => InputValidation::passwordRequiredRules(),
]);

// PhoneController.php
use App\Support\InputValidation;

$validated = $request->validate([
    'phone_number' => ['required', 'string', 'regex:/^(\\+639|639|09)\\d{9}$/'],
    'password' => InputValidation::passwordRequiredRules(),
]);

// SetUpController.php
use App\Support\InputValidation;

$validated = $request->validate([
    'first_name'  => InputValidation::nameRequiredRules(),
    'last_name'   => InputValidation::nameRequiredRules(),
    'middle_name' => InputValidation::nameNullableRules(),
    'roles'       => ['required', 'array', 'min:1'],
    'roles.*'     => ['string', 'in:driver,commuter,organization', 'distinct'],
]);
```

- [ ] **Step 4: Re-run targeted tests**

Run: `php artisan test tests/Unit/InputValidationRulesTest.php -v`  
Expected: PASS.

- [ ] **Step 5: Commit checkpoint**

```bash
git add app/Http/Controllers/Api/AuthController.php app/Http/Controllers/Api/PhoneController.php app/Http/Controllers/Api/SetUpController.php
git commit -m "refactor(api): centralize auth and profile validation rules"
```

### Task 4: Harden query/input validation in search and organization endpoints

**Files:**
- Modify: `app/Http/Controllers/Api/SearchController.php`
- Modify: `app/Http/Controllers/Api/OrganizationController.php`
- Test: `tests/Feature/ApiControllerValidationAdoptionTest.php`

- [ ] **Step 1: Write failing tests for query validation behavior**

```php
<?php

test('search controllers validate search and sort inputs with shared rules', function () {
    $files = [
        app_path('Http/Controllers/Api/SearchController.php'),
        app_path('Http/Controllers/Api/OrganizationController.php'),
    ];

    foreach ($files as $file) {
        $content = file_get_contents($file);
        expect($content)->toContain('InputValidation::safeSearchRules');
    }
});
```

- [ ] **Step 2: Run test to verify failure**

Run: `php artisan test tests/Feature/ApiControllerValidationAdoptionTest.php -v`  
Expected: FAIL before controller updates.

- [ ] **Step 3: Add explicit query validation in both controllers**

```php
// SearchController: validate query params before query building
$queryParams = $request->validate([
    'search' => InputValidation::safeSearchRules(120),
    'sort_by' => ['nullable', 'string', Rule::in(['license_number', 'franchise_number', 'verification_status', 'created_at'])],
    'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
]);

// OrganizationController index: validate filters and sorting first
$filters = $request->validate([
    'search' => InputValidation::safeSearchRules(120),
    'sort_by' => ['nullable', 'string', Rule::in(['name', 'status', 'created_at', 'updated_at'])],
    'sort_dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
    'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
]);
```

- [ ] **Step 4: Re-run adoption test**

Run: `php artisan test tests/Feature/ApiControllerValidationAdoptionTest.php -v`  
Expected: PASS for these files.

- [ ] **Step 5: Commit checkpoint**

```bash
git add app/Http/Controllers/Api/SearchController.php app/Http/Controllers/Api/OrganizationController.php tests/Feature/ApiControllerValidationAdoptionTest.php
git commit -m "feat(api): validate and sanitize search/filter query inputs"
```

### Task 5: Apply shared validation across remaining API controllers with input payloads

**Files:**
- Modify: `app/Http/Controllers/Api/EmergencyContactController.php`
- Modify: `app/Http/Controllers/Api/UserController.php`
- Modify: `app/Http/Controllers/Api/DriverController.php`
- Modify: `app/Http/Controllers/Api/CommuterController.php`
- Modify: `app/Http/Controllers/Api/VehicleController.php`
- Modify: `app/Http/Controllers/Api/FareController.php`
- Modify: `tests/Feature/ApiControllerValidationAdoptionTest.php`

- [ ] **Step 1: Extend failing adoption test list**

```php
$expectedControllersUsingSharedRules = [
    'AuthController.php',
    'PhoneController.php',
    'SetUpController.php',
    'SearchController.php',
    'OrganizationController.php',
    'EmergencyContactController.php',
    'UserController.php',
    'DriverController.php',
    'CommuterController.php',
    'VehicleController.php',
    'FareController.php',
];
```

- [ ] **Step 2: Run adoption test and verify failure**

Run: `php artisan test tests/Feature/ApiControllerValidationAdoptionTest.php -v`  
Expected: FAIL until all files adopt helper usage where they validate input.

- [ ] **Step 3: Refactor remaining controllers**

```php
// EmergencyContactController
use App\Support\InputValidation;

'contact_name' => InputValidation::nameRequiredRules(),
'contact_phone_number' => ['required', 'string', 'max:20', 'regex:/^[0-9+\\-\\s()]+$/'],
'contact_relationship' => InputValidation::safeStringRules(required: false, max: 255),

// UserController (index filters)
'status' => ['nullable', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED])],

// DriverController
'license_id_number' => InputValidation::safeStringRules(required: true, max: 255),

// CommuterController
'ID_number' => InputValidation::safeStringRules(required: false, max: 255),

// VehicleController
'vehicle_type' => InputValidation::safeStringRules(required: true, max: 255),
'description' => InputValidation::safeStringRules(required: true, max: 500),

// FareController
'distance' => ['required', 'numeric', 'min:0'],
```

- [ ] **Step 4: Re-run adoption test**

Run: `php artisan test tests/Feature/ApiControllerValidationAdoptionTest.php -v`  
Expected: PASS.

- [ ] **Step 5: Commit checkpoint**

```bash
git add app/Http/Controllers/Api/EmergencyContactController.php app/Http/Controllers/Api/UserController.php app/Http/Controllers/Api/DriverController.php app/Http/Controllers/Api/CommuterController.php app/Http/Controllers/Api/VehicleController.php app/Http/Controllers/Api/FareController.php tests/Feature/ApiControllerValidationAdoptionTest.php
git commit -m "refactor(api): standardize validation rules across API controllers"
```

### Task 6: Final regression run

**Files:**
- Modify: `tests/Feature/ApiInputSanitizationMiddlewareTest.php`
- Modify: `tests/Unit/InputValidationRulesTest.php`

- [ ] **Step 1: Run focused suite**

Run:

```bash
php artisan test tests/Feature/ApiInputSanitizationMiddlewareTest.php tests/Feature/ApiControllerValidationAdoptionTest.php tests/Unit/InputValidationRulesTest.php -v
```

Expected: PASS.

- [ ] **Step 2: Run full project suite**

Run: `php artisan test`  
Expected: PASS with no new failures.

- [ ] **Step 3: Commit final integration checkpoint**

```bash
git add app/Http/Middleware/SanitizeApiInput.php app/Support/InputValidation.php app/Http/Controllers/Api tests bootstrap/app.php
git commit -m "feat(api): add global sanitization and consistent input validation hardening"
```

## Self-Review Checklist (Plan vs Spec)

- Spec coverage: middleware, shared rules, all API controllers, password non-mutation, `Ññ` support, and tests are each mapped to explicit tasks above.
- Placeholder scan: no unresolved placeholders; each code-change step includes concrete snippets and commands.
- Type/signature consistency: `InputValidation` method names are consistent across tasks (`nameRequiredRules`, `nameNullableRules`, `passwordRequiredRules`, `passwordConfirmedRules`, `safeSearchRules`, `safeStringRules`).
