<?php

namespace ChrisHenrique\RequestsMonitor\Console;

use ChrisHenrique\RequestsMonitor\Models\RequestMonitor;
use Illuminate\Console\Command;

class PruneRequestsMonitorCommand extends Command
{
    protected $signature = 'requests-monitor:prune';

    protected $description = 'Prune old request monitor logs';

    public function handle(): int
    {
        $connection = config('requests-monitor.connection')
            ?? config('database.default');

        $days   = config('requests-monitor.retention_days');
        $domain = config('requests-monitor.domain');

        $cutoff = now()->subDays($days);

        $query = RequestMonitor::on($connection)
            ->where('created_at', '<', $cutoff);

        if ($domain) {
            $query->where('domain', $domain);
        }

        $query->delete();

        return self::SUCCESS;
    }
}
