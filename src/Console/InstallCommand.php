<?php

namespace ChrisHenrique\RequestsMonitor\Console;

use ChrisHenrique\RequestsMonitor\RequestsMonitorServiceProvider;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'requests-monitor:install
                            {--prune : Run prune after migrating}
                            {--force : Skip confirmations (required in production)}';

    protected $description = 'Install RequestsMonitor (publish config + migrations and migrate)';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if (! $force && ! $this->confirm('Run RequestsMonitor installation?', true)) {
            return 0;
        }

        $this->info('Installing RequestsMonitor...');

        // 1. Config (não sobrescreve se já publicada)
        $this->callSilent('vendor:publish', ['--tag' => 'requests-monitor-config']);
        $this->line('Config published (config/requests-monitor.php)');

        // 2. Migrations (não sobrescreve as já publicadas)
        $this->callSilent('vendor:publish', ['--tag' => 'requests-monitor-migrations']);
        $this->line('Migrations published');

        // 3. Migra APENAS as migrations do pacote, nunca o banco inteiro
        $paths = $this->packageMigrationPaths();

        if (empty($paths)) {
            $this->error('Package migrations not found in database/migrations.');

            return 1;
        }

        $exitCode = $this->call('migrate', [
            '--path'     => $paths,
            '--realpath' => true,
            '--force'    => $force,
        ]);

        if ($exitCode !== 0) {
            $this->error('Migration failed.');

            return 1;
        }

        $this->info('Migration completed');

        // 4. Prune opcional
        if ($this->option('prune')) {
            $this->call('requests-monitor:prune');
        }

        $this->line('');
        $this->info('RequestsMonitor installed successfully!');
        $this->line('Next steps:');
        $this->line('  1. Add the "requests-monitor" middleware to your routes or HTTP kernel');
        $this->line('  2. Configure REQUESTS_MONITOR_DOMAIN / REQUESTS_MONITOR_CONNECTION in .env');

        return 0;
    }

    /**
     * Caminhos (em database/migrations) das migrations publicadas pelo pacote.
     *
     * @return array<int, string>
     */
    protected function packageMigrationPaths(): array
    {
        $paths = [];

        foreach (glob(RequestsMonitorServiceProvider::migrationPath() . '/*.php') as $file) {
            $published = database_path('migrations/' . basename($file));

            if (is_file($published)) {
                $paths[] = $published;
            }
        }

        return $paths;
    }
}
