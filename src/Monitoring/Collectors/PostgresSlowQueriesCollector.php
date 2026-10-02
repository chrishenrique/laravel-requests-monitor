<?php

namespace ChrisHenrique\RequestsMonitor\Monitoring\Collectors;

use ChrisHenrique\RequestsMonitor\Contracts\SlowRequestCollector;
use ChrisHenrique\RequestsMonitor\Monitoring\Collectors\Concerns\ReadsFileTail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Queries que estão atrapalhando o PostgreSQL:
 *  - pg_stat_statements: top queries por tempo total/médio acumulado
 *  - tail do log do Postgres: linhas de 'duration:' geradas pelo
 *    log_min_duration_statement (queries individuais que estouraram o limite)
 */
class PostgresSlowQueriesCollector implements SlowRequestCollector
{
    use ReadsFileTail;

    public function handle(Request $request, array $context): array
    {
        $config = (array) config('requests-monitor.slow_request.pg_slow_queries', []);
        $connectionName = $config['connection'] ?? config('database.default');
        $connection = DB::connection($connectionName);

        if ($connection->getDriverName() !== 'pgsql') {
            return [
                'connection' => $connectionName,
                'supported'  => false,
                'driver'     => $connection->getDriverName(),
            ];
        }

        return [
            'connection'          => $connectionName,
            'supported'           => true,
            'pg_stat_statements'  => $this->statStatements($connection, $config),
            'slow_query_log'      => $this->slowQueryLog($config),
        ];
    }

    protected function statStatements($connection, array $config): array
    {
        $limit = (int) ($config['limit'] ?? 20);

        // PG 13+ usa total_exec_time/mean_exec_time; versões antigas, total_time/mean_time.
        foreach (['total_exec_time' => 'mean_exec_time', 'total_time' => 'mean_time'] as $total => $mean) {
            try {
                $rows = $connection->select(
                    "SELECT query,
                            calls,
                            round({$total}::numeric, 2) AS total_ms,
                            round({$mean}::numeric, 2)  AS mean_ms,
                            rows
                     FROM pg_stat_statements
                     ORDER BY {$total} DESC
                     LIMIT {$limit}"
                );

                return [
                    'available' => true,
                    'top'       => array_map(fn ($row) => (array) $row, $rows),
                ];
            } catch (\Throwable $e) {
                // Tenta o próximo conjunto de colunas; se nenhum funcionar, cai no return abaixo.
            }
        }

        return [
            'available' => false,
            'note'      => 'Extensão pg_stat_statements indisponível (CREATE EXTENSION pg_stat_statements; e shared_preload_libraries).',
        ];
    }

    protected function slowQueryLog(array $config): array
    {
        $path = $config['log_path'] ?? null;

        if (! $path) {
            return ['enabled' => false];
        }

        $lines = (int) ($config['log_lines'] ?? 30);

        // Tail maior e filtra só as linhas de duração (queries lentas logadas).
        $tail = $this->tail($path, max($lines * 5, $lines));

        $slow = array_values(array_filter($tail, fn ($line) => stripos($line, 'duration:') !== false));

        return [
            'enabled' => true,
            'path'    => $path,
            'queries' => array_slice($slow, -$lines),
        ];
    }
}
