# Laravel Requests Monitor

[![Latest Version on Packagist](https://img.shields.io/packagist/v/chrishenrique/laravel-requests-monitor.svg)](https://packagist.org/packages/chrishenrique/laravel-requests-monitor)
[![Total Downloads](https://img.shields.io/packagist/dt/chrishenrique/laravel-requests-monitor.svg)](https://packagist.org/packages/chrishenrique/laravel-requests-monitor)
[![License](https://img.shields.io/packagist/l/chrishenrique/laravel-requests-monitor.svg)](LICENSE)

A lightweight Laravel package to monitor, log, and analyze HTTP requests and custom application actions.  
Designed to be simple, extensible, and database-agnostic, it works seamlessly with legacy Laravel versions (7.x) and modern PHP versions.

---

## Features

- Automatic request monitoring via middleware
- Manual action registration for business events
- Dedicated database connection support
- Configurable retention and pruning
- Compatible with PHP 7.4 and PHP ^8.0
- Ideal for auditing, analytics, and security tracking

---

## Requirements

- PHP **7.4** or **^8.0**
- Laravel **7.x** or higher
- Any database supported by Laravel

---

## Installation

Install the package via Composer:

```bash
composer require chrishenrique/laravel-requests-monitor
```

---

## Configuration

### Publish Config File

```bash
php artisan vendor:publish --tag=requests-monitor-config
```

The config file will be available at:

```text
config/requests-monitor.php
```

---

## Database Setup

It is recommended to use a dedicated database or schema.

Create a database and configure a new connection named **`requests-monitor`** in `config/database.php`:

```php
'connections' => [

    'requests-monitor' => [
        'driver' => 'mysql',
        'host' => env('DB_MONITOR_HOST', '127.0.0.1'),
        'port' => env('DB_MONITOR_PORT', '3306'),
        'database' => env('DB_MONITOR_DATABASE', 'requests_monitor'),
        'username' => env('DB_MONITOR_USERNAME', 'root'),
        'password' => env('DB_MONITOR_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

],
```

---

## Migrations

Publish the migrations:

```bash
php artisan vendor:publish --tag=requests-monitor-migrations
```

Run them:

```bash
php artisan migrate
```

---

## Middleware Registration

### PHP ^8.0 (Laravel 9+)

```php
use ChrisHenrique\RequestsMonitor\Middlewares\RequestMonitorMiddleware;

$middleware->appendToGroup('web', [
    RequestMonitorMiddleware::class,
]);
```

### PHP 7.4 (Laravel 7 / 8)

In `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'web' => [
        \ChrisHenrique\RequestsMonitor\Middlewares\RequestMonitorMiddleware::class,
    ],
];
```

---

## Pruning Old Records (PHP 7.4)

Register the prune command in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('requests-monitor:prune')->daily();
}
```

You may also run it manually:

```bash
php artisan requests-monitor:prune
```

---

## Manual Action Registration

You can manually register application-specific actions:

```php
registerAction('billet.download', session('customer'));
```

This is useful for tracking business logic events that are not directly related to HTTP requests.

---

## Typical Use Cases

- HTTP request auditing
- API usage monitoring
- Business event tracking
- Security and access logs
- Performance and behavior analysis

---

## Roadmap

- Dashboard UI
- Filters and advanced querying
- Export and reporting tools

---

## Contributing

Contributions are welcome!  
Please open an issue or submit a pull request.

---

## License

The MIT License (MIT). Please see the [LICENSE](LICENSE) file for more information.
