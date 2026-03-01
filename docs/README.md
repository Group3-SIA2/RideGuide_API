# AdminLTE Installation Guide

## Step-by-step installation of AdminLTE

### 1. Require the package

On the root folder of your Laravel project, require the package using the composer tool:

```bash
composer require jeroennoten/laravel-adminlte
```

### 2. Install the package resources

Install the required package resources using the next command:

```bash
php artisan adminlte:install
```

### 3. Install the legacy authentication scaffolding (optional)

Optionally, and only for Laravel 7+ versions, this package offers a set of AdminLTE styled authentication views that you can use in replacement of the ones provided by the legacy `laravel/ui` authentication scaffolding. If you are planning to use these views, then first require the `laravel/ui` package using composer and install the bootstrap scaffolding:

```bash
composer require laravel/ui
php artisan ui bootstrap --auth
```
