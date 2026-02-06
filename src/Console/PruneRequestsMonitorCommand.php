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

        $days   = config('requests-monitor.prune_after_days');
        $domain = config('requests-monitor.domain');

        $query = RequestMonitor::on($connection)
            ->where('created_at', '<', now()->subDays($days));

        if ($domain) {
            $query->where('domain', $domain);
        }

        $query->delete();

        return self::SUCCESS;
    }
}
