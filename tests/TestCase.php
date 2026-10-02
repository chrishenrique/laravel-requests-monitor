<?php

namespace ChrisHenrique\RequestsMonitor\Tests;

use ChrisHenrique\RequestsMonitor\Models\RequestMonitor;
use ChrisHenrique\RequestsMonitor\RequestsMonitorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Executa as migrations do pacote antes de cada teste.
     */
    protected $runPackageMigrations = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->runPackageMigrations) {
            $this->loadMigrationsFrom(RequestsMonitorServiceProvider::migrationPath());
        }
    }

    protected function getPackageProviders($app)
    {
        return [RequestsMonitorServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('requests-monitor.connection', 'testing');
        $app['config']->set('requests-monitor.domain', 'tests');
        $app['config']->set('requests-monitor.queue.connection', 'sync');

        // Não depende dos defaults do config: liga explicitamente o que é testado.
        $app['config']->set('requests-monitor.slow_request.enabled', true);
        $app['config']->set('requests-monitor.slow_request.query_watcher.enabled', true);
        $app['config']->set('requests-monitor.slow_request.collectors.server.enabled', true);
        $app['config']->set('requests-monitor.slow_request.collectors.n_plus_1.enabled', true);

        // Collectors que dependem de Postgres/Apache ficam desligados nos testes.
        $app['config']->set('requests-monitor.slow_request.collectors.pg_activity.enabled', false);
        $app['config']->set('requests-monitor.slow_request.collectors.pg_slow_queries.enabled', false);
        $app['config']->set('requests-monitor.slow_request.collectors.apache.enabled', false);
        $app['config']->set('requests-monitor.slow_request.threshold_ms', 100000);
    }

    protected function lastRecord(): ?RequestMonitor
    {
        return RequestMonitor::query()->orderByDesc('id')->first();
    }
}
