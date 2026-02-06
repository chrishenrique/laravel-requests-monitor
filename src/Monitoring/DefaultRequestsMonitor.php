<?php

namespace ChrisHenrique\RequestsMonitor\Monitoring;

use ChrisHenrique\RequestsMonitor\Contracts\RequestsMonitor;
use ChrisHenrique\RequestsMonitor\Jobs\StoreRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DefaultRequestsMonitor implements RequestsMonitor
{
    public function shouldSkipRequest(\Illuminate\Http\Request $request): bool
    {
        $config = config('requests-monitor.exclude', []);

        if (in_array($request->path(), $config['urls'] ?? [])) {
            return true;
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

         if (str_contains($request->path(), 'favicon') || 
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

    public function logFromRequest(Request $request, ?Model $requester = null): void
    {
        if (!config('requests-monitor.enabled', true)) {
            return;
        }

        if ($this->shouldSkipRequest($request)) {
            return;
        }

        $user = $requester ?? $request->user();
        $route = $request->route();

        $payload = [
            'domain'         => config('requests-monitor.domain'),
            'method'         => $request->method(),
            'requester_type' => $user ? get_class($user) : null,
            'requester_id'   => $user ? $user->getKey() : null,
            'url'            => $request->fullUrl(),
            'route_name'     => $route ? $route->getName() : null,
            'action_name'    => null,
            'content'        => [
                'status_code' => $response->getStatusCode(),
                'duration' => defined('LARAVEL_START') ? microtime(true) - LARAVEL_START : 0,
                'input'    => $this->cleanInput($request->all()),
                'headers'  => $request->headers->all(),
                'ip'       => $request->ip(),
            ],
            'created_at'     => now(),
        ];

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
                $input[$field] = '********'; // unset($input[$field])
            }
        }

        // Feature: Remover campos de input com classes (Ex: Upload de arquivos)
        // Isso previne que o log tente serializar um binário de arquivo
        array_walk_recursive($input, function (&$value, $key) {
            if (is_object($value) && $this->shouldIgnoreType($value)) {
                $value = '[FILTERED TYPE: ' . get_class($value) . ']';
            }
        });

        return $input;
    }

    protected function shouldIgnoreType($object): bool
    {
        $ignoredTypes = config('requests-monitor.ignore_input_types', []);
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
