<?php

namespace ChrisHenrique\RequestsMonitor\Monitoring;

use ChrisHenrique\RequestsMonitor\Contracts\RequestsMonitor;
use ChrisHenrique\RequestsMonitor\Jobs\StoreRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class DefaultRequestsMonitor implements RequestsMonitor
{
    public function shouldSkipRequest(\Illuminate\Http\Request $request): bool
    {
        $config = config('requests-monitor.ignore', []);

        // $request->path() vem sem a barra inicial ('health'), então normaliza a config.
        $urls = array_map(function ($url) {
            return trim($url, '/') ?: '/';
        }, $config['urls'] ?? []);

        if (in_array($request->path(), $urls, true)) {
            return true;
        }

        foreach (($config['headers'] ?? []) as $header) {
            if ($request->headers->has($header)) {
                return true;
            }
        }

        $routeName = optional($request->route())->getName();
        if ($routeName && $this->matchesAny($routeName, $config['routes'] ?? [])) {
            return true;
        }

        foreach (($config['patterns'] ?? []) as $pattern) {
            if (preg_match($pattern, $request->fullUrl())) {
                return true;
            }
        }

        if ($request->method() === 'HEAD') {
            return true;
        }

        if (in_array($request->method(), $config['methods'] ?? [])) {
            return true;
        }

        if (Str::contains($request->path(), 'favicon') ||
            preg_match('/\.(css|js|png|jpg|gif|svg|ico|woff)/i', $request->path())) {
            return true;
        }

        foreach (($config['paths'] ?? []) as $path) {
            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }

    public function logFromRequest(Request $request, ?Model $requester = null, array $context = []): void
    {
        if (!config('requests-monitor.enabled', true)) {
            return;
        }

        if ($this->shouldSkipRequest($request)) {
            return;
        }

        $user = $requester ?? $request->user();
        $route = $request->route();

        try
        {
            $input = $this->cleanInput($request->all());
        }
        catch(\Exception $e)
        {
            report($e);
            $input = $request->input();
        }

        $executionMs = $context['execution_ms']
            ?? (defined('LARAVEL_START') ? round((microtime(true) - LARAVEL_START) * 1000, 2) : 0);

        $payload = [
            'domain'         => config('requests-monitor.domain'),
            'method'         => $request->method(),
            'requester_type' => $user ? get_class($user) : null,
            'requester_id'   => $user ? $user->getKey() : null,
            'url'            => $request->fullUrl(),
            'route_name'     => $route ? $route->getName() : null,
            'action_name'    => null,
            'execution_ms'   => $executionMs,
            'content'        => [
                'input'    => $input,
                'headers'  => $this->cleanHeaders($request->headers->all()),
                'ip'       => $request->ip(),
            ],
            'created_at'     => now(),
        ];

        if (!empty($context['slow'])) {
            $payload['content']['slow'] = $context['slow'];
        }

        $this->dispatchJob($payload);
    }

    public function logManually(array $attributes): void
    {
        if (!config('requests-monitor.enabled', true)) {
            return;
        }

        $payload = array_merge([
            'domain'       => config('requests-monitor.domain'),
            'created_at'   => now(),
        ], $attributes);

        $this->dispatchJob($payload);
    }

    protected function matchesAny(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $value) || $pattern === $value) {
                return true;
            }
        }
        return false;
    }


    protected function cleanInput(array $input): array
    {
        $maskedFields = config('requests-monitor.mask_fields', []);
        
        foreach ($maskedFields as $field) {
            if (isset($input[$field])) {
                $input[$field] = '********'; 
                // unset($input[$field])
            }
        }

        $transform = function (&$value, $key) use ($maskedFields, &$transform) {

            if (is_array($value)) {
                foreach ($value as $k => &$v) {
                    $transform($v, $k);
                }
                return;
            }

            if (in_array($key, $maskedFields, true)) {
                $value = '********';
                return;
            }

            if ($value instanceof UploadedFile) {
                $value = [
                    'name' => $value->getClientOriginalName(),
                    'size' => $value->getSize(),
                ];
                return;
            }

            if (is_object($value) && $this->shouldIgnoreType($value)) {
                $value = '[FILTERED TYPE: ' . get_class($value) . ']';
            }
        };

        $transform($input, null);

        return $input;
    }

    /**
     * Mascara headers sensíveis listados em mask_fields (ex: Authorization, Cookie).
     * Os nomes de header do Symfony são minúsculos, então a comparação ignora caixa.
     */
    protected function cleanHeaders(array $headers): array
    {
        $masked = array_map('strtolower', config('requests-monitor.mask_fields', []));

        foreach ($headers as $name => $values) {
            if (in_array(strtolower($name), $masked, true)) {
                $headers[$name] = ['********'];
            }
        }

        return $headers;
    }

    protected function shouldIgnoreType($object): bool
    {
        $ignoredTypes = config('requests-monitor.ignore.input_types', []);
        foreach ($ignoredTypes as $type) {
            if ($object instanceof $type) {
                return true;
            }
        }
        return false;
    }

    protected function dispatchJob(array $payload)
    {
        $job = new StoreRequest($payload);
        
        $queueName = config('requests-monitor.queue.name', 'default');
        $connection = config('requests-monitor.queue.connection', 'sync');

        dispatch($job)->onConnection($connection)->onQueue($queueName);
    }
}
