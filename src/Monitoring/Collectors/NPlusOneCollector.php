<?php

namespace ChrisHenrique\RequestsMonitor\Monitoring\Collectors;

use ChrisHenrique\RequestsMonitor\Contracts\SlowRequestCollector;
use ChrisHenrique\RequestsMonitor\Monitoring\QueryWatcher;
use Illuminate\Http\Request;

/**
 * Detecta possíveis N+1: queries idênticas (mesmo SQL parametrizado) repetidas
 * acima de um limite durante a request. Depende do QueryWatcher (DB::listen),
 * habilitado em 'slow_request.query_watcher.enabled'.
 */
class NPlusOneCollector implements SlowRequestCollector
{
    public function handle(Request $request, array $context): array
    {
        if (! config('requests-monitor.slow_request.query_watcher.enabled', true)) {
            return [
                'enabled' => false,
                'note'    => 'Query watcher desligado (slow_request.query_watcher.enabled = false).',
            ];
        }

        $watcher = app(QueryWatcher::class);
        $threshold = (int) config('requests-monitor.slow_request.n_plus_one.threshold', 5);

        $suspects = [];

        foreach ($watcher->all() as $sql => $info) {
            if ($info['count'] >= $threshold) {
                $suspects[] = [
                    'query'         => $sql,
                    'count'         => $info['count'],
                    'total_time_ms' => round($info['total_time_ms'], 2),
                ];
            }
        }

        usort($suspects, fn ($a, $b) => $b['count'] <=> $a['count']);

        return [
            'enabled'          => true,
            'threshold'        => $threshold,
            'total_queries'    => $watcher->totalQueries(),
            'distinct_queries' => count($watcher->all()),
            'suspects'         => $suspects,
        ];
    }
}
