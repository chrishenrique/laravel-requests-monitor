<?php

namespace ChrisHenrique\RequestsMonitor\Console;

use ChrisHenrique\RequestsMonitor\Models\RequestMonitor;
use Illuminate\Console\Command;

class PruneRequestsMonitorCommand extends Command
{
    protected $signature = 'requests-monitor:prune
                            {--days= : Override prune_after_days}
                            {--chunk= : Override prune_chunk_size}';

    protected $description = 'Prune old request monitor logs';

    public function handle(): int
    {
        $connection = config('requests-monitor.connection')
            ?? config('database.default');

        $days   = (int) ($this->option('days') ?? config('requests-monitor.prune_after_days', 90));
        $chunk  = max(1, (int) ($this->option('chunk') ?? config('requests-monitor.prune_chunk_size', 1000)));
        $domain = config('requests-monitor.domain');
        $cutoff = now()->subDays($days);

        // Apaga em lotes por id para não segurar locks longos em tabelas grandes.
        $deleted = 0;

        do {
            $ids = RequestMonitor::on($connection)
                ->where('created_at', '<', $cutoff)
                ->when($domain, function ($query, $domain) {
                    $query->where('domain', $domain);
                })
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id')
                ->all();

            if (empty($ids)) {
                break;
            }

            $deleted += RequestMonitor::on($connection)->whereIn('id', $ids)->delete();
        } while (count($ids) === $chunk);

        $this->info("Pruned {$deleted} request monitor record(s).");

        return 0;
    }
}
