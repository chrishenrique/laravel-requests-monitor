<?php

namespace ChrisHenrique\RequestsMonitor\Monitoring;

use ChrisHenrique\RequestsMonitor\Contracts\RequestsMonitor;
use ChrisHenrique\RequestsMonitor\Jobs\StoreRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DefaultRequestsMonitor implements RequestsMonitor
{
    protected function shouldSkipRequest(\Illuminate\Http\Request $request): bool
    {
        $config = config('requests-monitor.exclude', []);

        // 1. URL
        if (in_array($request->path(), $config['urls'] ?? [])) {
            return true;
        }

        // 2. Route name
        $routeName = $request->route()?->getName();
        if ($routeName && $this->matchesAny($routeName, $config['routes'] ?? [])) {
            return true;
        }

        // 3. Regex patterns
        foreach (($config['patterns'] ?? []) as $pattern) {
            if (preg_match($pattern, $request->fullUrl())) {
                return true;
            }
        }

        // 4. HEAD requests
        if ($request->method() === 'HEAD') {
            return true;
        }

        // 5. Favicon
        if (str_contains($request->path(), 'favicon')) {
            return true;
        }

        return false;
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

    public function logFromRequest(Request $request, ?Model $requester = null): void
    {
        if ($this->shouldSkipRequest($request)) {
            return;
        }

        $user = $requester ?? $request->user();

        $route = $request->route();

        $data = [
            'domain'         => config('requests-monitor.domain'),
            'method'         => $request->method(),
            'requester_type' => $user ? get_class($user) : null,
            'requester_id'   => $user ? $user->getKey() : null,
            'url'            => $request->fullUrl(),
            'route_name'     => $route ? $route->getName() : null,
            'action_name'    => null,
            'content'        => [
                'input'    => $request->all(),
                'headers'  => $request->headers->all(),
                'ip'       => $request->ip(),
            ],
            'created_at'     => now(),
        ];

        StoreRequest::dispatch($data);
    }

    public function logManually(array $attributes): void
    {
        $data = array_merge([
            'domain'       => config('requests-monitor.domain'),
            'created_at'   => now(),
        ], $attributes);

        StoreRequest::dispatch($data);
    }
}
