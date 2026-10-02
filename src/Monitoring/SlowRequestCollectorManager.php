<?php

namespace ChrisHenrique\RequestsMonitor\Monitoring;

use ChrisHenrique\RequestsMonitor\Contracts\SlowRequestCollector;
use Illuminate\Http\Request;

class SlowRequestCollectorManager
{
    public function isEnabled(): bool
    {
        return (bool) config('requests-monitor.slow_request.enabled', false);
    }

    /**
     * Tempo aceitável (ms). Acima disso a requisição é considerada lenta.
     */
    public function threshold(): float
    {
        return (float) config('requests-monitor.slow_request.threshold_ms', 0);
    }

    public function exceedsThreshold(float $executionMs): bool
    {
        $threshold = $this->threshold();

        return $threshold > 0 && $executionMs >= $threshold;
    }

    /**
     * Executa os collectors habilitados e agrega os dados retornados por chave.
     */
    public function collect(Request $request, float $executionMs): array
    {
        $context = [
            'execution_ms' => $executionMs,
            'threshold_ms' => $this->threshold(),
        ];

        $data = [];

        foreach ($this->collectors() as $entry) {
            if (! $entry['enabled']) {
                continue;
            }

            try {
                $collector = app($entry['class']);

                if (! $collector instanceof SlowRequestCollector) {
                    continue;
                }

                $data[$entry['key']] = $collector->handle($request, $context);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $data;
    }

    /**
     * Normaliza as entradas de config dos collectors.
     *
     * Aceita tanto o formato curto ('chave' => Classe::class) quanto o
     * detalhado ('chave' => ['class' => ..., 'enabled' => ...]).
     *
     * @return array<int, array{key: string, class: string, enabled: bool}>
     */
    protected function collectors(): array
    {
        $items = (array) config('requests-monitor.slow_request.collectors', []);

        $normalized = [];

        foreach ($items as $key => $config) {
            if (is_string($config)) {
                $config = ['class' => $config];
            }

            if (empty($config['class'])) {
                continue;
            }

            $normalized[] = [
                'key'     => is_string($key) ? $key : class_basename($config['class']),
                'class'   => $config['class'],
                'enabled' => $config['enabled'] ?? true,
            ];
        }

        return $normalized;
    }
}
