<?php

namespace ChrisHenrique\RequestsMonitor\Monitoring\Collectors;

use ChrisHenrique\RequestsMonitor\Contracts\SlowRequestCollector;
use ChrisHenrique\RequestsMonitor\Monitoring\Collectors\Concerns\ReadsFileTail;
use Illuminate\Http\Request;

/**
 * Coleta o estado do Apache no momento da request lenta:
 *  - mod_status (server-status?auto): workers ocupados/ociosos, req/s, scoreboard
 *  - tail dos logs configurados (error/access) para correlacionar
 */
class ApacheCollector implements SlowRequestCollector
{
    use ReadsFileTail;

    public function handle(Request $request, array $context): array
    {
        $config = (array) config('requests-monitor.slow_request.apache', []);

        return [
            'mod_status' => $this->modStatus($config),
            'logs'       => $this->logs($config),
        ];
    }

    protected function modStatus(array $config): array
    {
        $url = $config['status_url'] ?? null;

        if (! $url) {
            return ['enabled' => false];
        }

        $timeout = (float) ($config['timeout'] ?? 2);

        $streamContext = stream_context_create([
            'http' => ['timeout' => $timeout, 'ignore_errors' => true],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $body = @file_get_contents($url, false, $streamContext);

        if ($body === false || trim($body) === '') {
            return ['enabled' => true, 'reachable' => false, 'url' => $url];
        }

        $metrics = [];

        foreach (preg_split('/\r?\n/', trim($body)) as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $metrics[$key] = is_numeric($value) ? $value + 0 : $value;
        }

        return ['enabled' => true, 'reachable' => true, 'metrics' => $metrics];
    }

    protected function logs(array $config): array
    {
        $files = (array) ($config['logs'] ?? []);
        $lines = (int) ($config['log_lines'] ?? 20);

        $output = [];

        foreach ($files as $name => $path) {
            if (! $path) {
                continue;
            }

            $output[$name] = $this->tail($path, $lines);
        }

        return $output;
    }
}
