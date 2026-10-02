<?php

namespace ChrisHenrique\RequestsMonitor;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use ChrisHenrique\RequestsMonitor\Console\InstallCommand;
use ChrisHenrique\RequestsMonitor\Console\PruneRequestsMonitorCommand;
use ChrisHenrique\RequestsMonitor\Monitoring\QueryWatcher;

class RequestsMonitorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        require_once __DIR__ . '/helpers.php';

        if ($this->app->runningInConsole()) 
        {
            $this->publishes([
                __DIR__ . '/../config/requests-monitor.php' => config_path('requests-monitor.php'),
            ], 'requests-monitor-config');

            $this->publishes([
                static::migrationPath() => database_path('migrations'),
            ], 'requests-monitor-migrations');
        }

        $router = $this->app['router'];
         if (method_exists($router, 'aliasMiddleware')) {
            $router->aliasMiddleware('requests-monitor', Middlewares\RequestMonitorMiddleware::class);
        } else {
            $router->middleware('requests-monitor', Middlewares\RequestMonitorMiddleware::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                PruneRequestsMonitorCommand::class,
            ]);
        }

        $this->registerPruneSchedule();

        $this->registerQueryWatcher();
    }

    /**
     * Listener leve para detecção de N+1 — apenas conta repetições do mesmo SQL,
     * sem guardar bindings/payloads. Só é registrado se habilitado na config.
     */
    protected function registerQueryWatcher(): void
    {
        if (! config('requests-monitor.enabled', true)) {
            return;
        }

        if (! config('requests-monitor.slow_request.enabled', false)
            || ! config('requests-monitor.slow_request.query_watcher.enabled', true)) {
            return;
        }

        DB::listen(function ($query) {
            $this->app->make(QueryWatcher::class)->record($query->sql, $query->time);
        });
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/requests-monitor.php', 'requests-monitor');

        $this->app->singleton(QueryWatcher::class);

        $this->app->bind(
            Contracts\RequestsMonitor::class,
            fn ($app) => $app->make(config('requests-monitor.monitor_resolver',  Monitoring\DefaultRequestsMonitor::class))
        );
    }

    /**
     * Agenda o prune quando o Schedule for resolvido (schedule:run, schedule:list...),
     * independente da ordem de boot do Console Kernel entre as versões do Laravel.
     */
    protected function registerPruneSchedule(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
            $this->schedulePruneIfNotExists($schedule);
        });

        if ($this->app->resolved(Schedule::class)) {
            $this->schedulePruneIfNotExists($this->app->make(Schedule::class));
        }
    }

    protected function schedulePruneIfNotExists(Schedule $schedule): void
    {
        $pruneExists = collect($schedule->events())
            ->some(fn ($event) => Str::contains((string) $event->command, 'requests-monitor:prune'));

        if (! $pruneExists) {
            $schedule->command('requests-monitor:prune')
                ->dailyAt('02:00')
                ->name('requests-monitor-prune')
                ->onOneServer()
                ->withoutOverlapping(60); // Máx 1h execução
        }
    }

    /**
     * Pasta de migrations conforme a versão do PHP (php80 usa migrations anônimas,
     * suportadas apenas a partir do Laravel 8.37).
     */
    public static function migrationPath(): string
    {
        if (PHP_VERSION_ID >= 80000 && version_compare(app()->version(), '8.37.0', '>=')) {
            return __DIR__ . '/../database/migrations/php80';
        }

        return __DIR__ . '/../database/migrations/php74';
    }
}
