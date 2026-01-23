<?php

namespace ChrisHenrique\RequestsMonitor;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use ChrisHenrique\RequestsMonitor\Console\InstallCommand;
use ChrisHenrique\RequestsMonitor\Console\PruneRequestsMonitorCommand;

class RequestsMonitorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        require_once __DIR__ . '/helpers.php';

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/requests-monitor.php' => config_path('requests-monitor.php'),
             ], 'requests-monitor-config');
            $this->publishes([__DIR__ . '/../database/migrations' => database_path('migrations')], 'requests-monitor-migrations');
        }

        $router = $this->app['router'];
         if (method_exists($router, 'aliasMiddleware')) {
            $router->aliasMiddleware('requests-monitor', Middlewares\LogRequestMiddleware::class);
        } else {
            $router->middleware('requests-monitor', Middlewares\LogRequestMiddleware::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneRequestsMonitorCommand::class,
                InstallCommand::class,
            ]);
        }

        $this->app->booted(function () {
            $this->schedulePruneIfNotExists();
        });
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/requests-monitor.php', 'requests-monitor');

        $this->app->bind(
            Contracts\RequestsMonitor::class,
            fn ($app) => $app->make(config('requests-monitor.monitor_resolver',  Monitoring\DefaultRequestsMonitor::class))
        );
    }

    protected function schedulePruneIfNotExists(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $schedule = $this->app->make(Schedule::class);
        
        $pruneExists = collect($schedule->events())
            ->some(fn ($event) => str_contains($event->command, 'requests-monitor:prune'));

        if (! $pruneExists) {
            $schedule->command('requests-monitor:prune')
                ->dailyAt('02:00')
                ->name('requests-monitor-prune')
                ->onOneServer()
                ->withoutOverlapping(60); // Máx 1h execução
        }
    }
}
