# API Input Validation and Sanitization Design

## Problem Statement
The API currently validates many fields, but rules are not fully consistent across controllers and there is no centralized sanitization pass for request strings. We need defense-in-depth for transactional input data (names, passwords, search text, identifiers, and related user-supplied fields) to reduce XSS and SQL injection risk.

## Scope
- In scope: `app/Http/Controllers/Api/*Controller.php`
- In scope: centralized API sanitization middleware, shared validation helpers, and targeted controller rule updates.
- In scope: tests covering sanitization behavior and representative endpoint validation.
- Out of scope: frontend rendering changes and non-API controllers.

## Requirements (Approved)
1. Apply hardening across all API controllers.
2. Sanitize string inputs globally for API requests (strip tags, normalize, trim, remove unsafe control/null bytes), except password-like fields.
3. Keep passwords unmodified; validate them only.
4. Enforce name fields as letters/spaces only, including `Ññ` support via Unicode-aware validation.
5. Preserve Laravel-safe query patterns (Eloquent/query builder) and avoid raw SQL with user input.

## Approach Options Considered
1. **Hybrid central middleware + shared rules + targeted updates (selected)**  
   Best consistency and maintainability; minimizes drift and duplication while preserving endpoint-specific constraints.
2. Middleware-only  
   Good baseline sanitization, but insufficient for strict field semantics and endpoint-specific constraints.
3. Controller-only validation  
   Strong per endpoint but duplicates logic and is easy to miss on new endpoints.

## Final Design

### 1) Architecture
- Add a global API middleware (`SanitizeApiInput`) executed for all API routes.
- Middleware recursively processes request input values:
  - For strings: trim, normalize whitespace/unicode, strip HTML tags, remove null/control characters.
  - For arrays/objects: recurse.
  - For password keys (`password`, `password_confirmation`, `current_password`, `new_password`): skip mutation.
- Controllers continue to validate requests, but use shared helper rules to standardize constraints.

### 2) Components
1. **`app/Http/Middleware/SanitizeApiInput.php`**
   - Sanitizes request payload in-place before controller execution.
   - Handles nested arrays safely.
   - Keeps password-like fields untouched.
2. **`app/Support/InputValidation.php`** (or equivalent support class)
   - Shared rule builders for:
     - `nameRequiredRules()`, `nameNullableRules()`
     - `passwordRequiredRules()`, `passwordConfirmedRules()`
     - `safeSearchRules()`, `safeStringRules()`, numeric-text helpers where needed.
3. **Controller updates**
   - Replace ad-hoc string regex/rules with helper-based rules in API controllers.
   - Priority endpoints: auth/phone auth, setup/profile, search, organization/contact, and remaining API controllers that accept free text input.

### 3) Data Flow
1. Request enters API route.
2. `SanitizeApiInput` middleware sanitizes non-password string input.
3. Controller runs validation (shared rules + endpoint-specific rules).
4. On success, controller executes business logic and persistence.
5. On failure, Laravel returns 422 JSON errors with field details.

### 4) Error Handling
- Use standard Laravel validation responses for consistency.
- Keep explicit messages on critical fields where existing behavior expects them.
- Do not swallow invalid input; fail fast with 422.

### 5) Security Notes
- XSS mitigation: remove tags and unsafe characters from inbound strings; enforce strict text validation rules.
- SQL injection mitigation: keep parameterized ORM/query-builder usage; avoid raw interpolated SQL for user input.
- Password safety: do not alter password payloads before hash/check; enforce strong validation only.

### 6) Testing Strategy
- Add feature tests to verify:
  1. script-tag input is sanitized before persistence on representative endpoints.
  2. name validation accepts letters/spaces with Unicode (`Ññ`) and rejects disallowed symbols.
  3. password payload is validated but not sanitized/mutated.
  4. search/query inputs are constrained and validated.
  5. representative controllers consume shared validation behavior consistently.

## Rollout Notes
- Changes are backward-compatible for valid payloads.
- Invalid payloads that previously slipped through may now return 422, which is expected hardening behavior.
