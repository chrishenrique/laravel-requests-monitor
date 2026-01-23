<?php

namespace ChrisHenrique\RequestsMonitor\Console;

use ChrisHenrique\RequestsMonitor\Models\RequestLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InstallCommand extends Command
{
    protected $signature = 'requests-monitor:install 
                            {--fresh : Drop table and recreate}
                            {--prune-first : Run prune before install}
                            {--force : Skip confirmations}';

    protected $description = 'Install RequestsMonitor (migrate + prune + schedule)';

    public function handle()
    {
        $fresh = $this->option('fresh');
        $pruneFirst = $this->option('prune-first');
        $force = $this->option('force');

        if (! $force && ! $this->confirm('Run RequestsMonitor installation?', 'yes')) {
            return self::SUCCESS;
        }

        $this->info('🚀 Installing RequestsMonitor...');

        // 1. Publish migrations
        if (! file_exists(database_path('migrations/*requests_monitor_logs*'))) {
            $this->callSilent('vendor:publish', ['--tag' => 'requests-monitor-migrations', '--force' => true]);
            $this->info('✅ Published migrations');
        }

        // 2. Prune
        if ($pruneFirst) {
            $this->callSilent('requests-monitor:prune', ['--force' => true]);
            $this->line('🧹 Initial prune completed');
        }

        // 3. Migrate (fresh = drop if exists)
        if ($fresh) {
            Artisan::call('migrate:fresh', ['--path' => 'database/migrations/*requests_monitor_logs*']);
        } else {
            Artisan::call('migrate', ['--path' => 'database/migrations/*requests_monitor_logs*']);
        }
        $this->info('✅ Migration completed');

        // 4. Prune final
        $this->callSilent('requests-monitor:prune', ['--force' => true]);
        $this->line('🧹 Final prune completed');

        // 5. Test insert
        app(\ChrisHenrique\RequestsMonitor\Contracts\RequestLogger::class)
            ->logManually([
                'method' => 'INSTALL',
                'url' => 'requests-monitor:install',
                'content' => ['version' => '1.0', 'status' => 'success']
            ]);
        $this->info('✅ Test log inserted');

        $this->newLine();
        $this->warn([
            '🎉 RequestsMonitor installed successfully!',
            '',
            'Next steps:',
            '  1. Add middleware to bootstrap/app.php or Kernel.php',
            '  2. Configure REQUESTS_MONITOR_DOMAIN in .env (optional)',
            '',
            'Run: php artisan requests-monitor:prune --dry-run'
        ]);

        return self::SUCCESS;
    }
}
