<?php

namespace ChrisHenrique\RequestsMonitor\Console;

use ChrisHenrique\RequestsMonitor\Models\RequestMonitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneRequestsMonitorCommand extends Command
{
    protected $signature = 'requests-monitor:prune 
                                    {--connection : Use requests-monitor connection}
                                    {--days=90 : Days to retain logs}
                                    {--dry-run : Show records that would be deleted}
                                    {--force : Force deletion in production}';

    protected $description = 'Prune old request logs filtered by domain';

    public function handle()
    {
        $connection = $this->option('connection') 
            ? config('requests-monitor.connection') 
            : config('database.default');

        $days = (int) $this->option('days');
        $domain = config('requests-monitor.domain');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force') || $this->laravel->runningInProduction() === false;

        $cutoff = now()->subDays($days);
        
        $query = RequestMonitor::on($connection)
            ->where('created_at', '<', $cutoff);

        if ($domain) {
            $query->where('domain', $domain);
        }

        $count = $query->count();
        
        $this->info("Found {$count} logs older than {$days} days" . ($domain ? " for domain '{$domain}'" : ''));

        if ($dryRun) {
            $this->table(['ID', 'Domain', 'Created At'], $query->limit(10)->get(['id', 'domain', 'created_at'])->toArray());
            $this->warn("Dry run complete. Use --force to delete.");
            return self::SUCCESS;
        }

        if (! $force && $this->laravel->runningInProduction()) {
            if (! $this->confirm('Delete in production?', false)) {
                $this->warn('Aborted.');
                return self::SUCCESS;
            }
        }

        $deleted = $query->delete();
        
        $this->info("✅ Deleted {$deleted} records from {$connection}::requests_monitor_logs");
        
        return self::SUCCESS;
    }
}
