<?php

namespace ChrisHenrique\RequestsMonitor\Tests;

use ChrisHenrique\RequestsMonitor\RequestsMonitorServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Processo separado: no PHP 7.4 as migrations são classes nomeadas e as cópias
 * publicadas colidiriam com as do pacote já carregadas por outros testes.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class InstallCommandTest extends TestCase
{
    protected $runPackageMigrations = false;

    protected function tearDown(): void
    {
        // Remove o que foi publicado no skeleton do testbench.
        foreach (glob(RequestsMonitorServiceProvider::migrationPath() . '/*.php') as $file) {
            @unlink(database_path('migrations/' . basename($file)));
        }
        @unlink(config_path('requests-monitor.php'));

        parent::tearDown();
    }

    public function test_install_publishes_and_migrates_only_package_tables(): void
    {
        Schema::create('keep_me', function (Blueprint $table) {
            $table->increments('id');
        });

        $this->artisan('requests-monitor:install', ['--force' => true])
            ->assertExitCode(0);

        $this->assertFileExists(config_path('requests-monitor.php'));

        foreach (glob(RequestsMonitorServiceProvider::migrationPath() . '/*.php') as $file) {
            $this->assertFileExists(database_path('migrations/' . basename($file)));
        }

        $this->assertTrue(Schema::hasTable('requests_monitor'));
        $this->assertTrue(Schema::hasColumn('requests_monitor', 'execution_ms'));
        $this->assertTrue(Schema::hasTable('keep_me'), 'Install must not drop existing tables');

        // Rodar de novo é seguro (idempotente).
        $this->artisan('requests-monitor:install', ['--force' => true])
            ->assertExitCode(0);
    }

    public function test_install_can_be_cancelled(): void
    {
        $this->artisan('requests-monitor:install')
            ->expectsConfirmation('Run RequestsMonitor installation?', 'no')
            ->assertExitCode(0);

        $this->assertFalse(Schema::hasTable('requests_monitor'));
    }
}
