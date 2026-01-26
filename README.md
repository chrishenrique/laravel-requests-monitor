# RequestsMonitor

[![Latest Version on Packagist](https://img.shields.io/packagist/v/chrissenrique/requests_monitor.svg?style=flat-square)](https://packagist.org/packages/chrissenrique/requests_monitor)
[![Total Downloads](https://img.shields.io/packagist/dt/chrissenrique/requests_monitor.svg?style=flat-square)](https://packagist.org/packages/chrissenrique/requests_monitor)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/chrissenrique/RequestsMonitor/Check%20&%20fix%20styling.svg?label=code%20style)](https://github.com/chrissenrique/RequestsMonitor/actions?query=workflow%3A"Check+&+fix+styling")
[![Tests](https://img.shields.io/github/actions/workflow/status/chrissenrique/RequestsMonitor/run-tests.svg?style=flat-square)](https://github.com/chrissenrique/RequestsMonitor/actions?query=workflow%3Arun-tests)

**Centralizes HTTP requests and custom actions from multiple Laravel apps into a single database via queued jobs, with polymorphic requester support, automatic pruning, and customizable logging.**

Compatible with **Laravel 7–12** and **PHP 7.4–8.x+**. Supports Livewire Navigate and manual logging.

## ✨ Features

- **Automatic request logging** via middleware (headers, input, route, requester)
- **Polymorphic requester** (`requester_type`/`requester_id` for `App\User`, `App\Customer`, etc.)
- **Queued inserts** to avoid performance impact
- **Automatic data pruning** based on retention days
- **Manual logging** for CLI/commands/custom events
- **Customizable logger** via dependency injection
- **Multi-app centralization** (per-domain DB connection)
- **Livewire-compatible** middleware

## 📦 Installation

### 1. Install via Composer
```bash
composer require chrissenrique/laravel-requests-monitor
2. Publish Configuration

```bash
php artisan vendor:publish --tag="requests-monitor-config"
3. Publish & Run Migration

```bash
php artisan vendor:publish --tag="requests-monitor-migrations"
php artisan migrate
4. Register Middleware in app/Http/Kernel.php

```php
protected $middlewareAliases = [
    // ... other middleware
    'requests.monitor' => \ChrisHenrique\RequestsMonitor\Middlewares\LogRequestMiddleware::class,
];
5. Add Middleware to Groups

Option A: In AppServiceProvider::boot()

```php
public function boot()
{
    $this->app['router']->pushMiddlewareToGroup('web', 'requests.monitor');
    $this->app['router']->pushMiddlewareToGroup('api', 'requests.monitor');
}
Option B: In app/Http/Kernel.php

```php
protected $middlewareGroups = [
    'web' => [
        // ... other middleware
        \ChrisHenrique\RequestsMonitor\Middlewares\LogRequestMiddleware::class,
    ],
];
6. Configure .env (optional)

```text
REQUESTS_MONITOR_DOMAIN=https://your-app.com
REQUESTS_MONITOR_CONNECTION=mysql_logs
REQUESTS_MONITOR_RETENTION_DAYS=90
7. Schedule Pruning (in app/Console/Kernel.php)

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('model:prune-only ChrisHenrique\RequestsMonitor\Models\RequestLog')->daily();
}
⚙️ Configuration
Edit config/requests-monitor.php:

Key	Description	Default
domain	App domain for filtering logs	config('app.url')
connection	DB connection name	null (uses default)
retention_days	Days before auto-prune	90
logger_resolver	Custom logger class	DefaultRequestLogger
🚀 Usage
Automatic Logging (Middleware)

Logs all requests automatically:

HTTP method, full URL, route name, controller action

Request input, headers, IP address

Authenticated user (or custom model)

Livewire Navigate compatible

Manual Logging

```php
// CLI, Jobs, Events, anywhere!
app(\ChrisHenrique\RequestsMonitor\Contracts\RequestLogger::class)
    ->logManually([
        'method' => 'CLI',
        'url' => 'artisan:backup',
        'content' => ['action' => 'backup-started', 'files' => 150],
        'requester_type' => \App\Models\User::class,
        'requester_id' => 123,
    ]);
Custom Logger

Create your logger:

```php
<?php

namespace App\Logging;

use ChrisHenrique\RequestsMonitor\Contracts\RequestLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class CustomRequestLogger implements RequestLogger
{
    public function logFromRequest(Request $request, ?Model $requester = null): void
    {
        // Skip admin users
        if (auth()->user()?->isAdmin()) {
            return;
        }
        
        // Add custom data
        $data = [
            // ... default data
            'content' => array_merge($request->all(), ['custom' => 'data']),
        ];
        
        // Use default logger or dispatch directly
        app(\ChrisHenrique\RequestsMonitor\Logging\DefaultRequestLogger::class)
            ->logFromRequest($request, $requester);
    }

    public function logManually(array $attributes): void
    {
        // Custom manual logic
    }
}
Bind in AppServiceProvider:

```php
public function register()
{
    $this->app->bind(
        \ChrisHenrique\RequestsMonitor\Contracts\RequestLogger::class,
        \App\Logging\CustomRequestLogger::class
    );
}
🗄️ Database Schema
Table: requests_monitor_logs

Column	Type	Nullable	Description
id	bigIncrements	No	Primary key
domain	string	No	App domain
method	string(10)	No	HTTP method or custom
requester_type	string	Yes	Polymorphic type
requester_id	unsignedBigInteger	Yes	Polymorphic ID
url	string	No	Full URL
route_name	string	Yes	Laravel route name
action_name	string	Yes	Controller@method
content	json	No	Input/headers/IP (array)
created_at	timestamp	No	Creation timestamp
Polymorphic Relations

```php
$log = \ChrisHenrique\RequestsMonitor\Models\RequestLog::find(1);
$user = $log->requester; // App\User, App\Customer, etc.
$log->requester()->first(); // Eloquent relation
🧹 Pruning
Uses Laravel's Prunable trait

Deletes records older than retention_days

Run manually: php artisan model:prune-only ChrisHenrique\RequestsMonitor\Models\RequestLog

Schedule daily via Laravel Scheduler

🧪 Testing
```bash
composer test
Uses Orchestra Testbench for isolated testing.

🔒 Security
Sanitize sensitive data in custom logger (passwords, tokens)

Use separate DB connection for high-volume logs

Rate limiting possible via custom middleware logic

Content logged before response (no response body by default)

📊 Example Log Entry
```json
{
  "domain": "https://myapp.com",
  "method": "POST",
  "requester_type": "App\\Models\\User",
  "requester_id": 123,
  "url": "https://myapp.com/api/users",
  "route_name": "api.users.store",
  "action_name": "App\\Http\\Controllers\\UserController@store",
  "content": {
    "input": {"name": "John", "email": "john@example.com"},
    "headers": {"accept": "application/json", "x-api-key": "..."},
    "ip": "192.168.1.1"
  },
  "created_at": "2026-01-22 10:00:00"
}
🚀 Quick Start Commands
```bash
# Install
composer require chrissenrique/requests_monitor
php artisan vendor:publish --tag="requests-monitor-config"
php artisan vendor:publish --tag="requests-monitor-migrations"
php artisan migrate

# Test logging
php artisan tinker
>>> app(\ChrisHenrique\RequestsMonitor\Contracts\RequestLogger::class)->logManually(['method' => 'TEST', 'url' => 'tinker', 'content' => ['test' => true]]);
📚 Related Packages
Laravel Pulse - Official monitoring

Spatie Laravel Activity Log - Model changes

Laravel Telescope - Debug assistant

🛠️ Changelog
See CHANGELOG.md for detailed changes.

🤝 Contributing
See CONTRIBUTING.md for guidelines.

📄 License
MIT License. See LICENSE.md.

🙌 Support
⭐ Star this project if you found it useful!

Submit issues/feature requests at chrissenrique/RequestsMonitor.
