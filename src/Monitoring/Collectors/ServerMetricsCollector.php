<?php

namespace ChrisHenrique\RequestsMonitor\Monitoring\Collectors;

use ChrisHenrique\RequestsMonitor\Contracts\SlowRequestCollector;
use Illuminate\Http\Request;

/**
 * Métricas de saúde do servidor no momento da request lenta:
 *  - memória do processo PHP
 *  - load average e nº de CPUs (Linux)
 *  - memória/swap do host (/proc/meminfo)
 *
 * Ajuda a distinguir "minha request é pesada" de "o servidor estava saturado".
 */
class ServerMetricsCollector implements SlowRequestCollector
{
    public function handle(Request $request, array $context): array
    {
        $data = [
            'process_memory_mb'      => round(memory_get_usage(true) / 1048576, 2),
            'process_memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            'cpu_count'              => $this->cpuCount(),
        ];

        if (function_exists('sys_getloadavg') && ($load = sys_getloadavg()) !== false) {
            $data['load_average'] = [
                '1m'  => round($load[0], 2),
                '5m'  => round($load[1], 2),
                '15m' => round($load[2], 2),
            ];
        }

        return $data + $this->hostMemory();
    }

    protected function cpuCount(): ?int
    {
        if (is_readable('/proc/cpuinfo')) {
            $count = substr_count((string) @file_get_contents('/proc/cpuinfo'), 'processor');

            return $count > 0 ? $count : null;
        }

        return null;
    }

    protected function hostMemory(): array
    {
        if (! is_readable('/proc/meminfo')) {
            return [];
        }

        $meminfo = (string) @file_get_contents('/proc/meminfo');

        $read = function (string $key) use ($meminfo): ?float {
            if (preg_match('/^' . preg_quote($key, '/') . ':\s+(\d+)\s*kB/m', $meminfo, $m)) {
                return round(((float) $m[1]) / 1024, 2); // kB -> MB
            }

            return null;
        };

        return array_filter([
            'mem_total_mb'     => $read('MemTotal'),
            'mem_available_mb' => $read('MemAvailable'),
            'swap_total_mb'    => $read('SwapTotal'),
            'swap_free_mb'     => $read('SwapFree'),
        ], fn ($v) => $v !== null);
    }
}
