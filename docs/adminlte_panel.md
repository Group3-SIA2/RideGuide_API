# RideGuide Admin Panel — Component Documentation

> **Package:** `jeroennoten/laravel-adminlte`  
> **Last updated:** March 2, 2026  
> **Base URL:** `http://rideguide.test/admin`

---

## Table of Contents

1. [Overview](#1-overview)
2. [Package & Installation](#2-package--installation)
3. [Configuration — `config/adminlte.php`](#3-configuration--configadminltephp)
4. [Global Theme — `public/css/rideguide-admin.css`](#4-global-theme--publiccssrideguide-admincss)
5. [Routes — `routes/web.php`](#5-routes--routeswebphp)
6. [Controllers](#6-controllers)
7. [Blade Views](#7-blade-views)
8. [Real-Time Search & Filter](#8-real-time-search--filter)
9. [Authentication Views](#9-authentication-views)
10. [Seeders — Default Admin Accounts](#10-seeders--default-admin-accounts)
11. [CSS Component Reference](#11-css-component-reference)
12. [Dark Mode](#12-dark-mode)
13. [File Map](#13-file-map)

---

## 1. Overview

The RideGuide Admin Panel is built on top of **AdminLTE 3** via the `jeroennoten/laravel-adminlte` Laravel package. It provides a secured web interface for managing users, commuters, and drivers registered through the RideGuide mobile/API platform.

**Key characteristics:**

| Feature | Detail |
|---|---|
| Font | Inter (Google Fonts) |
| Primary colour | `#248AFF` |
| Accent light | `#BDDCFF` / `#dbeeff` |
| Sidebar | Deep navy `#0d1b36` — always dark |
| Content area | Light `#eef2f7` / Dark `#0d1117` |
| Dark mode | CSS custom properties + `body.dark-mode` toggle |
| Search | Real-time, server-side via AJAX (350 ms debounce) |
| Auth guard | Laravel `auth` middleware on all admin routes |

---

## 2. Package & Installation

```bash
# Install the package
composer require jeroennoten/laravel-adminlte

# Publish config + assets
php artisan adminlte:install

# Publish pre-built auth views (login, register, password reset)
php artisan adminlte:install --only=auth_views
```

The published config lives at `config/adminlte.php`.  
AdminLTE static assets (CSS, JS, fonts) are published to `public/vendor/adminlte/`.

---

## 3. Configuration — `config/adminlte.php`

### 3.1 Title & Branding

```php
'title'     => 'RideGuide',
'logo'      => '<b>Ride</b>Guide',
'logo_img'  => 'img/rideguide_logo.svg',   // sidebar logo
'logo_img_class' => 'brand-image',
```

The SVG logo is stored at `public/img/rideguide_logo.svg`.

### 3.2 Authentication Logo

```php
'auth_logo' => [
    'enabled' => true,
    'img' => [
        'path'   => 'img/rideguide_logo.svg',
        'alt'    => 'RideGuide Logo',
        'width'  => 60,
        'height' => 60,
    ],
],
```

Shown on the login, register, and password-reset screens.

### 3.3 URLs

```php
'use_route_url'      => true,        // all URL values must be route names
'dashboard_url'      => 'admin.dashboard',
'logout_url'         => 'logout',
'login_url'          => 'login',
'register_url'       => 'register',
'password_reset_url' => 'password.request',
'profile_url'        => 'admin.profile.index',
```

> **Important:** Because `use_route_url` is `true`, every url-related key — including menu items — must use **dot-notation route names**, not slash-separated URL paths.

### 3.4 Sidebar & Layout

```php
'classes_sidebar'     => 'sidebar-dark-primary elevation-2',
'sidebar_mini'        => 'lg',        // collapses to icon-only on large screens
'sidebar_nav_accordion' => true,
'layout_dark_mode'    => null,        // handled by custom CSS toggle instead
```

### 3.5 Navbar Widgets (right side)

Registered inside the `menu` array with `topnav_right => true`:

| Widget | Purpose |
|---|---|
| `navbar-search` | Built-in AdminLTE navbar search box |
| `darkmode-widget` | Light/dark toggle button |
| `fullscreen-widget` | Fullscreen toggle button |

### 3.6 Sidebar Menu

```
MAIN NAVIGATION
  └── Dashboard              → admin.dashboard

USERS LISTS
  ├── All Users              → admin.users.index
  ├── Commuters              → admin.commuters.index
  └── Drivers                → admin.drivers.index

ACCOUNT
  ├── Profile                → admin.profile.index
  └── Logout                 → admin.logout.confirm
```

Each item uses a `route` key and an `active` pattern (e.g. `['admin/users*']`) so the sidebar highlights correctly when on sub-pages.

### 3.7 Custom CSS Plugin

The custom theme stylesheet is injected via the plugins array:

```php
'plugins' => [
    'RideGuideTheme' => [
        'active' => true,
        'files'  => [
            ['type' => 'css', 'asset' => true, 'location' => 'css/rideguide-admin.css'],
        ],
    ],
    // Datatables, Select2, Chartjs, Sweetalert2, Pace — all inactive by default
],
```

---

## 4. Global Theme — `public/css/rideguide-admin.css`

All visual customisation lives in this single file (~525 lines). It uses **CSS custom properties** so light and dark modes are a single token-swap.

### 4.1 Design Tokens

```css
/* Light mode (:root) */
--rg-bg:           #eef2f7;
--rg-card:         #ffffff;
--rg-accent:       #248AFF;
--rg-accent-light: #dbeeff;
--rg-text:         #1a202c;
--rg-text-muted:   #64748b;
--rg-sidebar-bg:   #0d1b36;   /* always dark */

/* Dark mode (body.dark-mode) */
--rg-bg:           #0d1117;
--rg-card:         #161b2e;
--rg-accent:       #60a5fa;   /* lighter blue for readability */
```

### 4.2 Key CSS Classes

| Class | Description |
|---|---|
| `.rg-page-header` | Flex row: page title left, badge right |
| `.rg-page-title` | Page heading (1.35 rem, 700 weight) |
| `.rg-page-subtitle` | Muted sub-heading below title |
| `.rg-badge` | Pill badge (total count, date) |
| `.rg-stat-card` | Dashboard statistic card with icon |
| `.rg-stat-label` | Uppercase label above the number |
| `.rg-stat-value` | Large number (1.7 rem) |
| `.rg-stat-sub` | Small footnote below the number |
| `.rg-card` | Content card container |
| `.rg-card-header` | Card top bar (title + filter bar) |
| `.rg-card-body` | Card content area |
| `.rg-card-footer` | Pagination row |
| `.rg-table` | Styled table |
| `.rg-td-index` | Muted row number column |
| `.rg-td-muted` | De-emphasised cell text |
| `.rg-user-cell` | Avatar + name flex group |
| `.rg-avatar` | Initials circle avatar |
| `.rg-user-name` | Full name text in user cell |
| `.rg-role-badge` | Role label pill |
| `.rg-status-badge` | Status pill (verified/pending) |
| `.rg-status-active` | Green tint — verified/active |
| `.rg-status-pending` | Amber tint — pending |
| `.rg-filter-bar` | Search + filter form row |
| `.rg-search-input` | Text search field |
| `.rg-filter-select` | Dropdown filter |
| `.rg-btn-search` | Primary search button |
| `.rg-btn-clear` | Ghost clear/reset button |
| `.rg-empty` | Empty-state table cell message |

### 4.3 Font Application

Inter is applied to explicit elements only — **not** the `*` selector — to avoid overriding FontAwesome's icon font:

```css
body, .wrapper, h1–h6, p, span, a, td, th,
label, input, button, select, textarea,
.nav-link, .brand-text, .nav-header {
    font-family: var(--rg-font) !important;
}
```

---

## 5. Routes — `routes/web.php`

```
GET  /                       → redirect to admin.dashboard (auth) or login
GET  /login                  → Auth::routes() login
GET  /register               → Auth::routes() register
POST /logout                 → Auth::routes() logout (POST)

GET  /admin/dashboard        → admin.dashboard
GET  /admin/users            → admin.users.index
GET  /admin/commuters        → admin.commuters.index
GET  /admin/drivers          → admin.drivers.index
GET  /admin/profile          → admin.profile.index
GET  /admin/logout           → admin.logout.confirm  (confirmation page)
POST /admin/logout           → admin.logout          (actual logout)
```

All `/admin/*` routes are wrapped in `middleware(['auth'])`. The root `/` redirect is unauthenticated-safe — it checks `auth()->check()` without requiring the middleware.

---

## 6. Controllers

All admin controllers live in `app/Http/Controllers/Admin/` and extend the base `Controller`. Each applies `$this->middleware('auth')` in its constructor.

### 6.1 `DashboardController`

**Route:** `GET /admin/dashboard`  
**View:** `admin.dashboard`

Computes five stat card values from the database and returns the 10 most recent verified users. Supports AJAX for the recent-users table search/filter without a page reload.

| Variable | Source |
|---|---|
| `$totalVerifiedUsers` | `User::whereNotNull('email_verified_at')->count()` |
| `$totalAdmins` | Users with role `admin` |
| `$totalDrivers` | Users with role `driver` |
| `$totalCommuters` | Users with role `commuter` |
| `$totalDriverProfiles` | `Driver::count()` |
| `$recentUsers` | Last 10 verified users (filterable) |

**AJAX response keys:** `rows` (rendered HTML), `total` (int)

---

### 6.2 `UserController`

**Route:** `GET /admin/users`  
**View:** `admin.users.index`

Lists all verified users with role eager-loaded. Paginated at **15 per page**.

**Query parameters:**

| Param | Searches |
|---|---|
| `search` | `first_name`, `last_name`, `email` |
| `role` | Filters by role name (`admin`, `super_admin`, `driver`, `commuter`) |

**AJAX response keys:** `rows`, `pagination`, `total`

---

### 6.3 `CommuterController`

**Route:** `GET /admin/commuters`  
**View:** `admin.commuters.index`

Lists all commuter profiles with `user` and `discount.classificationType` eagerly loaded. Passes `$classifications` (distinct names from `DiscountTypes`) to populate the filter dropdown.

**Query parameters:**

| Param | Searches |
|---|---|
| `search` | Commuter's `first_name`, `last_name`, `email` (via `user` relation) |
| `classification` | Filters by `DiscountTypes.classification_name` |

**AJAX response keys:** `rows`, `pagination`, `total`

---

### 6.4 `DriverController`

**Route:** `GET /admin/drivers`  
**View:** `admin.drivers.index`

Lists all driver profiles with `user` eagerly loaded.

**Query parameters:**

| Param | Searches |
|---|---|
| `search` | `license_number`, `franchise_number`, `first_name`, `last_name`, `email` |
| `status` | Filters by `verification_status` (`verified` or `pending`) |

**AJAX response keys:** `rows`, `pagination`, `total`

---

### 6.5 `ProfileController`

**Route:** `GET /admin/profile`  
**View:** `admin.profile.index`

Passes the currently authenticated user (`auth()->user()`) to the profile view. Read-only display.

---

### 6.6 `LogoutController`

**Routes:**  
- `GET /admin/logout` → shows confirmation page  
- `POST /admin/logout` → performs logout

On POST: calls `Auth::logout()`, invalidates the session, regenerates the CSRF token, then redirects to `login`.

---

## 7. Blade Views

All views extend `adminlte::page` and use the custom `.rg-*` CSS classes.

```
resources/views/admin/
├── dashboard.blade.php          Main dashboard (stats + recent users table)
├── dashboard/
│   └── _recent_rows.blade.php   Partial — recent users tbody rows (AJAX)
├── logout.blade.php             Logout confirmation card
├── profile/
│   └── index.blade.php          Profile page (avatar card + details table)
├── users/
│   ├── index.blade.php          All users listing
│   └── _rows.blade.php          Partial — users tbody rows (AJAX)
├── commuters/
│   ├── index.blade.php          Commuters listing
│   └── _rows.blade.php          Partial — commuters tbody rows (AJAX)
└── drivers/
    ├── index.blade.php          Drivers listing
    └── _rows.blade.php          Partial — drivers tbody rows (AJAX)
```

### View Structure Pattern

Every listing page follows this layout:

```
@section('content_header')
    .rg-page-header
        ├── .rg-page-title + .rg-page-subtitle
        └── .rg-badge  ← total count (updated by AJAX)

@section('content')
    .rg-card
        ├── .rg-card-header
        │   ├── .rg-card-dot + title
        │   └── #rg-filter-form  (search input + filter select + buttons)
        ├── .rg-card-body
        │   └── .rg-table
        │       └── <tbody id="rg-table-body">  ← replaced by AJAX
        └── #rg-pagination .rg-card-footer      ← replaced by AJAX

@section('js')
    <script> ... debounced fetch logic ... </script>
```

### Partial Views (`_rows.blade.php`)

Each page has a companion partial that renders **only the `<tr>` rows**. These are returned as rendered HTML strings in AJAX responses, keeping the server rendering consistent with the initial full-page load.

---

## 8. Real-Time Search & Filter

All listing pages use a unified JavaScript pattern for live server-side search without page refreshes.

### How It Works

1. User types in `#rg-search` or changes `#rg-filter`
2. JS debounces **350 ms**, then calls `load()`
3. `load()` builds a query string from current input values and fires `fetch()` with the `X-Requested-With: XMLHttpRequest` header
4. Controller detects `$request->ajax()` and returns JSON instead of a full view
5. JS replaces `#rg-table-body` innerHTML, updates `#rg-total`, re-renders `#rg-pagination`
6. Pagination links are intercepted with `bindPagin()` so page navigation also stays AJAX

```
User types              JS debounce             Server
─────────────────       ──────────────          ──────────────────────────────
keystroke ──────────── wait 350ms ─────────── GET /admin/users?search=john
                                               X-Requested-With: XMLHttpRequest
                                                     │
                                               $request->ajax() === true
                                                     │
                                         return JSON {rows, pagination, total}
                                                     │
JS updates tbody ◄───────────────────────────────────┘
JS updates badge + pagination
```

### Browser URL Sync

`history.replaceState()` keeps the browser URL bar in sync with the current filters so the state is shareable:

```js
history.replaceState(null, '', barQS ? '?' + barQS : window.location.pathname);
```

### Filter Parameters by Page

| Page | Search fields | Filter dropdown |
|---|---|---|
| Dashboard | first_name, last_name, email | role |
| Users | first_name, last_name, email | role |
| Commuters | first_name, last_name, email (via user) | classification |
| Drivers | first_name, last_name, email, license_number, franchise_number | verification_status |

---

## 9. Authentication Views

Auth views are published by AdminLTE and customised with the Inter font and RideGuide brand colours.

```
resources/views/auth/
├── login.blade.php
├── register.blade.php
└── passwords/
    ├── email.blade.php
    └── reset.blade.php
```

Each view extends its corresponding AdminLTE auth layout (e.g. `adminlte::auth.login`) and injects a `@push('css_auth')` block with:

- Inter font import from Google Fonts
- CSS overrides for the login card border/button to use `#248AFF`

The auth logo (60×60 SVG) is configured in `config/adminlte.php` under `auth_logo`.

---

## 10. Seeders — Default Admin Accounts

Run with:

```bash
php artisan db:seed
```

This calls `RoleSeeder`, `DiscountTypesSeeder`, then creates two admin users if they don't exist:

| Field | Super Admin | Admin |
|---|---|---|
| Email | `superadmin@rideguide.com` | `admin@rideguide.com` |
| Password | `SuperAdmin@2026` | `Admin@2026` |
| Role | `super_admin` | `admin` |
| Verified | Yes (pre-verified) | Yes (pre-verified) |

Both accounts are created with `User::firstOrCreate()` — re-running the seeder is safe and idempotent.

> **Security:** Change these passwords immediately in a production environment.

---

## 11. CSS Component Reference

### Stat Card

```html
<div class="rg-stat-card">
    <div class="rg-stat-icon">
        <i class="fas fa-users"></i>
    </div>
    <div class="rg-stat-body">
        <p class="rg-stat-label">Total Users</p>
        <h3 class="rg-stat-value">1,234</h3>
        <span class="rg-stat-sub">Verified accounts</span>
    </div>
</div>
```

### Card with Table

```html
<div class="rg-card">
    <div class="rg-card-header">
        <div class="d-flex align-items-center">
            <span class="rg-card-dot"></span>
            <h6 class="rg-card-title mb-0">Title</h6>
        </div>
        <!-- filter bar goes here -->
    </div>
    <div class="rg-card-body p-0">
        <table class="rg-table">...</table>
    </div>
    <div class="rg-card-footer">
        {{ $items->links() }}
    </div>
</div>
```

### User Cell (Avatar + Name)

```html
<div class="rg-user-cell">
    <div class="rg-avatar">JD</div>
    <div>
        <p class="rg-user-name mb-0">John Doe</p>
        <span class="rg-td-muted">Middle Name</span>
    </div>
</div>
```

### Status Badges

```html
<span class="rg-status-badge rg-status-active">Verified</span>
<span class="rg-status-badge rg-status-pending">Pending</span>
<span class="rg-role-badge">Admin</span>
```

### Filter Bar

```html
<form id="rg-filter-form" method="GET" action="{{ route('admin.users.index') }}" class="rg-filter-bar mt-2">
    <input id="rg-search" type="text" name="search" class="rg-search-input" placeholder="Search…" value="{{ request('search') }}">
    <select id="rg-filter" name="role" class="rg-filter-select">
        <option value="">All Roles</option>
        <option value="admin">Admin</option>
    </select>
    <button type="submit" class="rg-btn-search"><i class="fas fa-search"></i> Search</button>
    <button type="button" id="rg-clear" class="rg-btn-clear">Clear</button>
</form>
```

---

## 12. Dark Mode

Dark mode is driven entirely by CSS custom properties. AdminLTE's built-in `darkmode-widget` in the navbar toggles the `dark-mode` class on `<body>`.

```css
/* Light — :root defines defaults */
:root { --rg-bg: #eef2f7; --rg-card: #ffffff; ... }

/* Dark — overrides the same tokens */
body.dark-mode { --rg-bg: #0d1117; --rg-card: #161b2e; ... }
```

The sidebar is **always dark** (`#0d1b36`) regardless of the mode toggle — its tokens are not overridden in `body.dark-mode`. This gives the panel a consistent identity in both modes.

Smooth transitions are applied to key elements:

```css
body,
.content-wrapper,
.main-header.navbar,
.rg-stat-card,
.rg-card,
.rg-table thead tr,
.rg-table tbody td {
    transition: background 0.25s ease, border-color 0.25s ease, color 0.2s ease;
}
```

---

## 13. File Map

```
app/
└── Http/
    └── Controllers/
        └── Admin/
            ├── DashboardController.php
            ├── UserController.php
            ├── CommuterController.php
            ├── DriverController.php
            ├── ProfileController.php
            └── LogoutController.php

config/
└── adminlte.php                      ← full AdminLTE configuration

public/
├── css/
│   └── rideguide-admin.css           ← global custom theme
└── img/
    └── rideguide_logo.svg            ← sidebar + auth logo

resources/
└── views/
    ├── auth/
    │   ├── login.blade.php
    │   ├── register.blade.php
    │   └── passwords/
    │       ├── email.blade.php
    │       └── reset.blade.php
    └── admin/
        ├── dashboard.blade.php
        ├── dashboard/
        │   └── _recent_rows.blade.php
        ├── logout.blade.php
        ├── profile/
        │   └── index.blade.php
        ├── users/
        │   ├── index.blade.php
        │   └── _rows.blade.php
        ├── commuters/
        │   ├── index.blade.php
        │   └── _rows.blade.php
        └── drivers/
            ├── index.blade.php
            └── _rows.blade.php

routes/
└── web.php

database/
└── seeders/
    ├── DatabaseSeeder.php            ← creates 2 admin accounts
    ├── RoleSeeder.php
    └── DiscountTypesSeeder.php
```
