<?php

namespace ChrisHenrique\RequestsMonitor\Monitoring;

/**
 * Agregador leve de queries da request atual, alimentado por um DB::listen.
 *
 * NÃO guarda bindings nem payloads — apenas conta repetições da MESMA query
 * (o SQL do Laravel já vem parametrizado com '?'), o que permite detectar N+1
 * com custo mínimo. Registrado como singleton; vive apenas durante a request
 * (em workers de longa duração, reset() deve ser chamado no início da request).
 */
class QueryWatcher
{
    /** @var array<string, array{count: int, total_time_ms: float}> */
    protected array $queries = [];

    public function record(string $sql, ?float $timeMs = null): void
    {
        if (! isset($this->queries[$sql])) {
            $this->queries[$sql] = ['count' => 0, 'total_time_ms' => 0.0];
        }

        $this->queries[$sql]['count']++;
        $this->queries[$sql]['total_time_ms'] += (float) $timeMs;
    }

    public function reset(): void
    {
        $this->queries = [];
    }

    /** @return array<string, array{count: int, total_time_ms: float}> */
    public function all(): array
    {
        return $this->queries;
    }

    public function totalQueries(): int
    {
        return (int) array_sum(array_column($this->queries, 'count'));
    }
}
