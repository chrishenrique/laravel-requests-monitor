<?php

namespace ChrisHenrique\RequestsMonitor\Monitoring\Collectors;

use ChrisHenrique\RequestsMonitor\Contracts\SlowRequestCollector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Collector que captura um snapshot do pg_stat_activity do PostgreSQL no
 * momento em que a requisição lenta termina — útil para ver quais conexões/
 * queries estavam ativas, bloqueios (pg_blocking_pids) e tempo no banco.
 *
 * Roda apenas quando a request ultrapassa o threshold (sem custo nas rápidas).
 */
class PgStatActivityCollector implements SlowRequestCollector
{
    public function handle(Request $request, array $context): array
    {
        $connectionName = $this->connectionName();
        $connection = $this->connection();

        if ($connection->getDriverName() !== 'pgsql') {
            return [
                'connection' => $connectionName,
                'supported'  => false,
                'driver'     => $connection->getDriverName(),
            ];
        }

        $config = (array) config('requests-monitor.slow_request.pg_activity', []);
        $onlyActive = $config['only_active'] ?? true;
        $limit = (int) ($config['limit'] ?? 50);

        $sql = "SELECT
                    pid,
                    datname,
                    usename,
                    application_name,
                    client_addr::text AS client_addr,
                    state,
                    wait_event_type,
                    wait_event,
                    round(extract(epoch from (now() - query_start)) * 1000, 2) AS duration_ms,
                    round(extract(epoch from (now() - xact_start)) * 1000, 2) AS xact_duration_ms,
                    array_to_json(pg_blocking_pids(pid))::text AS blocked_by,
                    query_start,
                    xact_start,
                    left(query, 1000) AS query
                FROM pg_stat_activity
                WHERE pid <> pg_backend_pid()";

        if ($onlyActive) {
            $sql .= " AND state IS DISTINCT FROM 'idle'";
        }

        $sql .= ' ORDER BY query_start ASC NULLS LAST';

        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }

        $rows = $connection->select($sql);

        return [
            'connection' => $connectionName,
            'supported'  => true,
            'self_pid'   => $this->backendPid($connection),
            'count'      => count($rows),
            'activity'   => array_map([$this, 'normalizeRow'], $rows),
        ];
    }

    /**
     * PID do backend desta request (mesma conexão usada durante a request),
     * obtido aqui — só quando a request já foi considerada lenta.
     */
    protected function backendPid($connection): ?int
    {
        try {
            $row = $connection->selectOne('SELECT pg_backend_pid() AS pid');

            return $row ? (int) $row->pid : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function normalizeRow($row): array
    {
        $row = (array) $row;

        // pg_blocking_pids vem como JSON ('[123,456]'); decodifica para array.
        $row['blocked_by'] = isset($row['blocked_by'])
            ? (json_decode($row['blocked_by'], true) ?: [])
            : [];

        return $row;
    }

    protected function connectionName(): string
    {
        return config('requests-monitor.slow_request.pg_activity.connection')
            ?? config('database.default');
    }

    protected function connection()
    {
        return DB::connection($this->connectionName());
    }
}
