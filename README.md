# Laravel Requests Monitor

[![Latest Version on Packagist](https://img.shields.io/packagist/v/chrishenrique/laravel-requests-monitor.svg)](https://packagist.org/packages/chrishenrique/laravel-requests-monitor)
[![Total Downloads](https://img.shields.io/packagist/dt/chrishenrique/laravel-requests-monitor.svg)](https://packagist.org/packages/chrishenrique/laravel-requests-monitor)
[![Tests](https://github.com/chrishenrique/laravel-requests-monitor/actions/workflows/tests.yml/badge.svg)](https://github.com/chrishenrique/laravel-requests-monitor/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/chrishenrique/laravel-requests-monitor.svg)](LICENSE)

A lightweight Laravel package to monitor, log, and analyze HTTP requests and custom application actions.  
Designed to be simple, extensible, and database-agnostic, it works seamlessly with legacy Laravel versions (7.x) and modern PHP versions.

---

## Features

- Automatic request monitoring via middleware
- Manual action registration for business events
- **Slow request diagnostics** via pluggable collectors (DB activity, slow queries, N+1, Apache/Linux metrics)
- Dedicated database connection support
- Configurable retention and pruning
- Compatible with Laravel 7.x through 13.x (PHP 7.4 and PHP ^8.0)
- Ideal for auditing, analytics, and security tracking

---

## Requirements

- PHP **7.4** or **^8.0**
- Laravel **7.x** to **13.x**
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

Create a database and configure a new connection named **`requests_monitor`** (or set `REQUESTS_MONITOR_CONNECTION`) in `config/database.php`:

```php
'connections' => [

    'requests_monitor' => [
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

The quickest way is the install command. It publishes the config and migrations
(without overwriting existing files) and migrates **only** the package migrations:

```bash
php artisan requests-monitor:install          # asks for confirmation
php artisan requests-monitor:install --force  # no prompt (required in production)
php artisan requests-monitor:install --prune  # also prunes old records afterwards
```

Or do it manually. Publish the migrations:

```bash
php artisan vendor:publish --tag=requests-monitor-migrations
```

Run them:

```bash
php artisan migrate
```

> On Laravel 7, 8 and 9 the migration that changes the `url` column requires `doctrine/dbal`.

---

## Middleware Registration

### Laravel 11+ (`bootstrap/app.php`)

```php
use ChrisHenrique\RequestsMonitor\Middlewares\RequestMonitorMiddleware;

$middleware->appendToGroup('web', [
    RequestMonitorMiddleware::class,
]);
```

### Laravel 7 – 10 (`app/Http/Kernel.php`)

In `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'web' => [
        \ChrisHenrique\RequestsMonitor\Middlewares\RequestMonitorMiddleware::class,
    ],
];
```

Or use the `requests-monitor` alias on specific routes:

```php
Route::middleware('requests-monitor')->group(function () {
    // ...
});
```

The request is timed in `handle()` but stored in `terminate()`, **after the response
has been sent** (with PHP-FPM), so slow-request collectors and the job dispatch don't
delay the client. Failures while logging are reported and never break the response.

---

## Pruning Old Records

The package schedules `requests-monitor:prune` daily at 02:00 automatically (unless
you already scheduled it yourself). Records are deleted in batches
(`prune_chunk_size`, default 1000) to avoid long table locks.

You may also run it manually:

```bash
php artisan requests-monitor:prune
php artisan requests-monitor:prune --days=30 --chunk=5000
```

---

## Testing

```bash
composer install
composer test
```

---

## Manual Action Registration

You can manually register application-specific actions:

```php
registerAction('billet.download', session('customer'));
```

This is useful for tracking business logic events that are not directly related to HTTP requests.

---

## Slow Request Monitoring

When a request takes longer than an acceptable threshold, the package runs a set of
**collectors** to capture diagnostic information about *why* it was slow. The data is
stored under `content['slow']` of the monitored request.

> Collectors run **only when the request exceeds the threshold**. Fast requests pay no
> cost — nothing is collected and nothing is discarded. The single exception is the
> optional N+1 query watcher (see below), which keeps a lightweight per-request counter.

### How it works

1. The middleware measures the request execution time.
2. If `slow_request.enabled` is `true` **and** the time is above `threshold_ms`,
   **all enabled collectors are executed**.
3. Each collector returns an array, stored under its key in `content['slow']`.

### Configuration

```php
// config/requests-monitor.php
'slow_request' => [
    'enabled'      => env('REQUESTS_MONITOR_SLOW_ENABLED', true),
    'threshold_ms' => env('REQUESTS_MONITOR_SLOW_THRESHOLD_MS', 1000),

    // PgStatActivityCollector — live snapshot of pg_stat_activity
    'pg_activity' => [
        'connection'  => env('REQUESTS_MONITOR_PG_ACTIVITY_CONNECTION'),
        'only_active' => true,
        'limit'       => 50,
    ],

    // PostgresSlowQueriesCollector — pg_stat_statements + slow query log
    'pg_slow_queries' => [
        'connection' => env('REQUESTS_MONITOR_PG_ACTIVITY_CONNECTION'),
        'limit'      => 20,
        'log_path'   => env('REQUESTS_MONITOR_PG_LOG_PATH'),
        'log_lines'  => 30,
    ],

    // ApacheCollector — mod_status + log tails
    'apache' => [
        'status_url' => env('REQUESTS_MONITOR_APACHE_STATUS_URL', 'http://127.0.0.1/server-status?auto'),
        'timeout'    => 2,
        'logs'       => [
            // 'error'  => '/var/log/apache2/error.log',
            // 'access' => '/var/log/apache2/access.log',
        ],
        'log_lines'  => 20,
    ],

    // NPlusOneCollector — lightweight query counter (always-on, toggleable)
    'query_watcher' => [
        'enabled' => env('REQUESTS_MONITOR_QUERY_WATCHER', true),
    ],
    'n_plus_one' => [
        'threshold' => 5, // same query repeated N+ times = suspect
    ],

    'collectors' => [
        'server'          => ['class' => \ChrisHenrique\RequestsMonitor\Monitoring\Collectors\ServerMetricsCollector::class,        'enabled' => true],
        'pg_activity'     => ['class' => \ChrisHenrique\RequestsMonitor\Monitoring\Collectors\PgStatActivityCollector::class,        'enabled' => true],
        'pg_slow_queries' => ['class' => \ChrisHenrique\RequestsMonitor\Monitoring\Collectors\PostgresSlowQueriesCollector::class,   'enabled' => true],
        'apache'          => ['class' => \ChrisHenrique\RequestsMonitor\Monitoring\Collectors\ApacheCollector::class,                'enabled' => true],
        'n_plus_1'        => ['class' => \ChrisHenrique\RequestsMonitor\Monitoring\Collectors\NPlusOneCollector::class,              'enabled' => true],
    ],
],
```

Each collector can be turned on/off independently via its `enabled` flag.

### Built-in collectors

| Key | Class | Captures |
|---|---|---|
| `server` | `ServerMetricsCollector` | Process memory, CPU count, **load average** and host memory/swap (`/proc/meminfo`) |
| `pg_activity` | `PgStatActivityCollector` | Live `pg_stat_activity` snapshot, durations, waits and `blocked_by` (`pg_blocking_pids`) |
| `pg_slow_queries` | `PostgresSlowQueriesCollector` | Top queries from **`pg_stat_statements`** + slow lines tailed from the **PostgreSQL log** |
| `apache` | `ApacheCollector` | **`mod_status`** metrics + tails of the Apache error/access logs |
| `n_plus_1` | `NPlusOneCollector` | Repeated identical queries flagged as likely **N+1** |

### Environment activation checklist

Each collector degrades gracefully (returns `supported: false`, `reachable: false`, or
an empty list) when its dependency is missing — it never breaks the request. To get full
data, enable the following on your **Linux / Apache / PostgreSQL** stack:

**1. Apache `mod_status`**

```bash
sudo a2enmod status
sudo systemctl reload apache2
```

```apache
# Expose the machine-readable endpoint to the server itself only
<Location "/server-status">
    SetHandler server-status
    Require ip 127.0.0.1
</Location>
ExtendedStatus On
```

**2. Log file permissions**

The PHP/web user (e.g. `www-data`) must be able to **read** the Apache and PostgreSQL
log files. They are often owned by `root`/`adm`/`postgres`:

```bash
# Example: allow the adm group (which already reads apache logs) to read PG logs
sudo usermod -aG adm www-data
# Or grant read access to the specific files referenced in the config
```

**3. PostgreSQL `pg_stat_statements`**

```ini
# postgresql.conf
shared_preload_libraries = 'pg_stat_statements'
```

```sql
-- after restarting PostgreSQL
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;
```

**4. PostgreSQL slow query log (`log_min_duration_statement`)**

```ini
# postgresql.conf — log every statement slower than 1s
log_min_duration_statement = 1000
```

Then point `pg_slow_queries.log_path` at the active log file, e.g.
`/var/log/postgresql/postgresql-16-main.log`.

**5. (Optional) Compare Apache vs application time**

Add `%D` (microseconds) to your Apache `LogFormat` to record total request time at the
web-server level. Comparing it with the stored `execution_ms` reveals time spent **outside
PHP** (worker queueing, TLS, keep-alive).

### Example stored data

```json
{
    "input": { "...": "..." },
    "headers": { "...": "..." },
    "ip": "203.0.113.10",
    "slow": {
        "server": {
            "process_memory_mb": 64.0,
            "process_memory_peak_mb": 78.5,
            "cpu_count": 4,
            "load_average": { "1m": 3.21, "5m": 2.10, "15m": 1.80 },
            "mem_total_mb": 7820.0,
            "mem_available_mb": 612.4,
            "swap_total_mb": 2048.0,
            "swap_free_mb": 120.0
        },
        "pg_activity": {
            "connection": "pgsql",
            "supported": true,
            "self_pid": 12345,
            "count": 2,
            "activity": [
                {
                    "pid": 678,
                    "state": "active",
                    "duration_ms": 4200.5,
                    "wait_event_type": "Lock",
                    "wait_event": "transactionid",
                    "blocked_by": [12345],
                    "query": "update orders set ..."
                }
            ]
        },
        "pg_slow_queries": {
            "connection": "pgsql",
            "supported": true,
            "pg_stat_statements": {
                "available": true,
                "top": [
                    { "query": "select * from orders where customer_id = $1", "calls": 1500, "total_ms": 92000.5, "mean_ms": 61.3, "rows": 1500 }
                ]
            },
            "slow_query_log": {
                "enabled": true,
                "path": "/var/log/postgresql/postgresql-16-main.log",
                "queries": [
                    "2026-06-24 10:15:02 UTC LOG:  duration: 3120.412 ms  statement: select ..."
                ]
            }
        },
        "apache": {
            "mod_status": {
                "enabled": true,
                "reachable": true,
                "metrics": { "BusyWorkers": 148, "IdleWorkers": 2, "ReqPerSec": 210.4, "Scoreboard": "WWWWWW...." }
            },
            "logs": {
                "error": ["[Wed Jun 24 10:15:01 2026] [error] ..."]
            }
        },
        "n_plus_1": {
            "enabled": true,
            "threshold": 5,
            "total_queries": 142,
            "distinct_queries": 7,
            "suspects": [
                { "query": "select * from addresses where user_id = ?", "count": 120, "total_time_ms": 380.5 }
            ]
        }
    }
}
```

### Writing your own collector

Implement the `SlowRequestCollector` contract and register it under `slow_request.collectors`:

```php
namespace App\Monitoring\Collectors;

use ChrisHenrique\RequestsMonitor\Contracts\SlowRequestCollector;
use Illuminate\Http\Request;

class MyCollector implements SlowRequestCollector
{
    public function handle(Request $request, array $context): array
    {
        // $context['execution_ms'], $context['threshold_ms']
        return ['custom' => 'value'];
    }
}
```

```php
'collectors' => [
    // ...
    'my_collector' => ['class' => \App\Monitoring\Collectors\MyCollector::class, 'enabled' => true],
],
```

The returned array is stored under `content['slow']['my_collector']`. Collectors are
resolved through the container, so you can type-hint dependencies in the constructor.

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
